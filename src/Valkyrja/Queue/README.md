# Queue

## Introduction

The Queue component runs a job outside the request that produced it. A producer
dispatches a `JobContract` through a client. A consumer receives the same job,
runs it through a middleware pipeline, and settles the outcome with the
processor.

The pipeline takes a job in and returns a `JobResult` out. There is no response
envelope, because no caller waits for one.

## The Job

`Valkyrja\Queue\Message\Job\Contract\JobContract` is the one message class, and
it travels in both directions. A job is immutable: the framework builds a new
one through a `with*` method, the same way an HTTP request and a CLI input work.

The envelope is a cross-language contract, so every field is always present. A
millisecond field is authoritative, and the matching `_iso` field is derived
from it. `Valkyrja\Queue\Message\Constant\EnvelopeField` names each wire field.

A job carries a payload and a set of attributes.
`Valkyrja\Queue\Message\Payload\Contract\PayloadContract` holds the data the
handler reads. `Valkyrja\Queue\Message\Attributes\Contract\AttributesContract`
holds the metadata that middleware and the handler read. Both are immutable for
the same reason the job is.

`Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract` builds a job,
and renders one for the wire.

## The Pipeline

`JobHandler` runs one job through seven middleware stages, and each stage has
its own contract in `Valkyrja\Queue\Middleware\Contract`:

| Stage             | Runs when                                |
| ----------------- | ---------------------------------------- |
| `JobReceived`     | the job arrives, before routing          |
| `RouteMatched`    | the router finds a route                 |
| `RouteNotMatched` | the router finds no route                |
| `RouteDispatched` | the handler returns                      |
| `ThrowableCaught` | an earlier stage throws                  |
| `SettlingResult`  | before the outcome reaches the processor |
| `ResultSettled`   | after the outcome reaches the processor  |

Each stage has a matching handler contract in
`Valkyrja\Queue\Middleware\Handler\Contract`, which holds the middleware of
that stage and runs it in order.

`Valkyrja\Queue\Server\Handler\Contract\JobHandlerContract` runs a job through
those stages. An entry calls `run`, which returns the outcome, and calls
`resultSettled` last. Settling the outcome with the processor is the entry's
own work, and it belongs between the two calls. `run` is `handle` plus
`settlingResult`. An entry calls `handle` and `settlingResult` separately when
it has to change the outcome between them.

`JobReceived`, `SettlingResult`, and `ResultSettled` always run. The other four
are conditional. A `JobReceived` middleware that returns an outcome
short-circuits the pipeline, and the router never runs. The router otherwise
chooses `RouteMatched` or `RouteNotMatched`. `RouteDispatched` runs once the
handler returns, and `ThrowableCaught` runs when any earlier stage throws.

The outcome is one of four `JobResult` cases: `ACK`, `RETRY`, `FAIL`, or
`DEAD_LETTER`. A `ThrowableCaught` middleware dead-letters a throwable that
carries `Valkyrja\Queue\Throwable\Contract\QueueNonRetryableThrowable`, rather
than retrying it.

Route middleware is appended and never deduplicated. A middleware registered
twice runs twice.

## Throwables

`Valkyrja\Queue\Throwable\Contract\QueueThrowable` marks every throwable the
component raises, and each sub-component narrows it with its own marker, as
`QueueMessageThrowable` and `QueueServerThrowable` do. Each sub-component
marker has an abstract `*InvalidArgumentException` and `*RuntimeException`
pair. A concrete exception extends one of its own sub-component's pair, so a
caller can catch one sub-component or the whole component.
`QueueNonRetryableThrowable` narrows `QueueThrowable` as well, so
`catch (QueueThrowable)` catches a throwable that carries it. That marker is
cross-cutting rather than per sub-component. It marks a throwable the pipeline
must not retry, whatever raises it.

## The Client

`Valkyrja\Queue\Client\Manager\Contract\ClientContract` is the producer
surface. `push` enqueues a fresh job. `requeue` and `retry` settle a retry, the
first taking the job as dispatched and the second the already incremented copy.
`getPushed` returns the jobs handed to the client during one unit of work, and
`clearPushed` ends that unit of work.

A processor with no retry of its own settles through `requeue`. The client
increments the attempt, stamps the modification time, and reads the hold from
the job as dispatched. `requeue` calls `retry` with that hold already supplied.
The framework reaches neither on a processor that owns redelivery, because that
processor's entry answers the retry from its own settlement.

Both seams record the job in `getPushed`. The framework clears that record, and
application code never does.

## Redelivery

Redis and a database have no native retry, so the framework re-queues the job.
`Requeuer` builds a new job with the attempt incremented and hands it back to
the client. The hold comes from the job the re-queuer dispatched, read before
the increment, so the ramp is keyed to the attempt that just failed.

## Clients

| Client           | Broker | Redelivery |
| ---------------- | ------ | ---------- |
| `SyncClient`     | none   | framework  |
| `DeferredClient` | none   | framework  |
| `InMemoryClient` | none   | framework  |
| `RedisClient`    | Redis  | framework  |

`SyncClient` is the zero-config default. It runs the job inline and blocks, and
it runs the whole retry chain. There is no durable place to hold a retry delay,
so the delay is skipped and the incremented job runs again at once. Only the
timing differs from production; the retry count is identical.

Warning: a `SyncClient` push throws on a terminal `FAIL` or `DEAD_LETTER`. The
caller blocks until the job finishes, so the caller is still there to be told.
Every other client throws only on an enqueue error. This is the one deliberate
difference between the clients.

`DeferredClient` buffers the job and drains it after the response. It is not
durable, and it needs a host runtime that can keep working after the response.

## Entry Points

| Entry       | Runs                                     |
| ----------- | ---------------------------------------- |
| `Queue`     | one job, then exits                      |
| `PullQueue` | a worker that takes jobs from a broker   |
| `PushQueue` | one job that a broker delivers over HTTP |

`Queue` is single-shot, so a host that pushes repeatedly pays a full boot per
push. `WorkerQueue` boots the application once and then gives each job a fresh
child container, which is the shape a real broker worker loops over.

Every job runs through an entry point, never through `JobHandler` directly. The
entry gives the job an isolated container, so an embedded development run
behaves the same as a standalone production worker.

## Routing

A job names a route, and
`Valkyrja\Queue\Routing\Dispatcher\Contract\RouterContract` turns that name
into the handler that runs it.
`Valkyrja\Queue\Routing\Collection\Contract\RouteCollectionContract` holds the
routes. `Valkyrja\Queue\Routing\Collector\Contract\RouteCollectorContract`
gathers them from the classes an application names, and
`Valkyrja\Queue\Routing\Provider\Contract\QueueRouteProviderContract` is how an
application names those classes and contributes routes in code.

A component provider returns its queue route providers from its
`getQueueProviders()` method.

A controller declares a route with `Valkyrja\Queue\Routing\Attribute\Route`
on a method. The attribute takes the job name, a description, the handler, and
a middleware array for each stage a route supports. `JobReceived` runs before
routing and `RouteNotMatched` runs when there is no route, so those two take
their middleware from the application config instead.

Three modifier attributes keep a route with several settings readable, each on
its own line. `Route\RouteHandler` names the handler. `Route\Middleware` adds
a middleware class to every stage whose contract that class implements.
`Route\Name` on the class prefixes the job name, and on the method it
suffixes it.

A route names its handler with `Route\RouteHandler` or with the `handler`
argument of `Route`. A `RouteHandler` wins when a route carries both. The
collector rejects a route that names neither, and the failure names the class
and the method, so a forgotten handler stops the boot rather than acknowledging
each job it receives.

```php
#[Name('acme')]
final class SendWelcomeEmailHandler
{
    public static function handle(ContainerContract $container, RouteContract $route): JobResult
    {
        return JobResult::ACK;
    }

    #[Route(name: 'SendWelcomeEmail', description: 'Send the welcome email')]
    #[RouteHandler([self::class, 'handle'])]
    public function sendWelcomeEmail(): JobResult
    {
        return JobResult::ACK;
    }
}
```

`Valkyrja\Queue\Routing\Data\QueueRoutingData` is the generated cache of that
collection, so a production boot reads the routes from a data class instead of
gathering them again.

The same route runs a job that arrives from an external broker, an in-process
`sync` push, a `deferred` drain, or an `inmemory` test.

## Configuration

`Valkyrja\Application\Data\Contract\QueueConfigContract` is the application
config a queue consumer boots from. It adds the middleware of each of the
seven stages to the properties every application config carries.

`Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract` names the
default client, and an application config may implement it to choose one.
`QueueConfigContract` is a separate thing: it configures a queue application
rather than naming a client.

`QueueConfig` is the framework default. An application config that implements
`QueueConfigContract` replaces it.

An application config implements `QueueConfigProvidedContract` to embed a queue
in an HTTP, CLI, or gRPC application. The contract returns the queue config that
the host application runs jobs against.

## Service Registration

`QueueMessageServiceProvider`, `QueueMiddlewareServiceProvider`,
`QueueRoutingServiceProvider`, `QueueServerServiceProvider`, and
`QueueClientServiceProvider` publish the container bindings.

Each middleware stage handler is a shared singleton, so the `Router` and the
`JobHandler` register and invoke the same instance.

## Optional Dependencies

A broker adapter needs its own package, and the framework does not require one:

| Adapter | Package         |
| ------- | --------------- |
| Redis   | `predis/predis` |

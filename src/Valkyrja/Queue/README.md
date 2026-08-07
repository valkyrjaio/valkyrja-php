# Queue

## Introduction

The Queue component runs a job outside the request that produced it. A producer
dispatches a `JobContract` through a client. A consumer receives the same job,
runs it through a middleware pipeline, and settles the outcome with the broker.

The pipeline takes a job in and returns a `JobResult` out. There is no response
envelope, because no caller waits for one.

## The Job

`Valkyrja\Queue\Message\Job\Contract\JobContract` is the one message class, and
it travels in both directions. A job is immutable: the framework builds a new
one through a `with*` method, the same way an HTTP request and a CLI input work.

The envelope is a cross-language contract, so every field is always present. A
millisecond field is authoritative, and the matching `_iso` field is derived
from it. `Valkyrja\Queue\Message\Constant\EnvelopeField` names each wire field.

`JobFactory` builds a job. The job is a data object, so it holds no static
method: construction belongs to the factory, and a rendering belongs to a
support class.

## The Pipeline

`JobHandler` runs one job through seven middleware stages, and each stage has
its own contract in `Valkyrja\Queue\Middleware\Contract`:

| Stage             | Runs when                             |
| ----------------- | ------------------------------------- |
| `JobReceived`     | the job arrives, before routing       |
| `RouteMatched`    | the router finds a route              |
| `RouteNotMatched` | the router finds no route             |
| `RouteDispatched` | the handler returns                   |
| `ThrowableCaught` | the handler throws                    |
| `SettlingResult`  | before the outcome reaches the broker |
| `ResultSettled`   | after the outcome reaches the broker  |

The handler returns one of four outcomes: `ACK`, `RETRY`, `FAIL`, or
`DEAD_LETTER`. `RetryPolicyThrowableCaughtMiddleware` converts a throwable into
one of them, and it dead-letters at once when the throwable carries
`QueueNonRetryableThrowable`.

Route middleware is appended and never deduplicated. A middleware registered
twice runs twice.

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

Warning: a client scopes `getPushed` to one request, one command, or one job. A
client that keeps a process-global record leaks in a long-running server, and it
gives one request the deferred jobs of the request before it.

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

A job routes by name. `#[Route]` marks a controller method, and
`AttributeRouteCollector` collects the routes at runtime. `sindri` generates the
same routes into `AppQueueRoutingData` for a cached boot.

The same route runs a job that arrives from an external broker, an in-process
`sync` push, a `deferred` drain, or an `inmemory` test.

## Configuration

`Valkyrja\Application\Data\Contract\QueueConfigContract` holds the settings that
apply to the whole component:

| Property                             | Holds                                   |
| ------------------------------------ | --------------------------------------- |
| `applicationName`                    | the producer name stamped on each job   |
| `defaultMaxAttempts`                 | the attempt ceiling                     |
| `defaultRetryDelayMs`                | the base hold between attempts          |
| `defaultRetryDelayMultiplyByAttempt` | whether the hold ramps with the attempt |
| the seven `*Middleware` properties   | the middleware for each pipeline stage  |

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

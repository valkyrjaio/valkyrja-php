# Queue

## Introduction

The Queue component runs a job outside the request that produced it. A producer
dispatches a `JobContract` through a client. A consumer receives the same job,
runs it through a middleware pipeline, and settles the outcome with the
processor.

The pipeline takes a job in and returns a `JobResult` out. There is no response
envelope, because no caller waits for one.

This document describes how the contracts interact.

## The Job

`Valkyrja\Queue\Message\Job\Contract\JobContract` is the one message class, and
it travels in both directions. A job is immutable: the framework builds a new
one through a `with*` method, the same way an HTTP request and a CLI input work.

The envelope is a cross-language contract, so every field is always present. A
millisecond field is authoritative, and the matching `_iso` field is derived
from it.

A job carries a payload and a set of attributes.
`Valkyrja\Queue\Message\Payload\Contract\PayloadContract` holds the data the
handler reads. `Valkyrja\Queue\Message\Attributes\Contract\AttributesContract`
holds the metadata that middleware and the handler read. Both are immutable for
the same reason the job is.

`Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract` builds a job,
and renders one for the wire.

## The Pipeline

A job runs through seven middleware stages, and each stage has its own contract
in `Valkyrja\Queue\Middleware\Contract`:

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
the job as dispatched. `retry` is the lower seam `requeue` calls, and it takes
the hold already supplied. The framework reaches neither on a processor that
owns redelivery, because that processor's entry answers the retry from its own
settlement.

Both seams record the job in `getPushed`. A buffering host drains that record
until it is empty before it calls `clearPushed`, because a job that a handler
pushes during the drain is lost otherwise. Application code only ever calls
`push`.

## Routing

A job names a route, and
`Valkyrja\Queue\Routing\Dispatcher\Contract\RouterContract` turns that name
into the handler that runs it.
`Valkyrja\Queue\Routing\Collection\Contract\RouteCollectionContract` holds the
routes. `Valkyrja\Queue\Routing\Collector\Contract\RouteCollectorContract`
gathers them from the classes an application names, and
`Valkyrja\Queue\Routing\Provider\Contract\QueueRouteProviderContract` is how an
application names those classes and contributes routes in code.

`Valkyrja\Queue\Routing\Data\QueueRoutingData` is the generated cache of that
collection, so a production boot reads the routes from a data class instead of
gathering them again.

## Configuration

`Valkyrja\Application\Data\Contract\QueueConfigContract` is the application
config a queue consumer boots from. It adds the middleware of each of the
seven stages to the properties every application config carries.

`Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract` names the
default client, and an application config may implement it to choose one.
`QueueConfigContract` is a separate thing: it configures a queue application
rather than naming a client.

# Queue

## Introduction

The Queue component runs a job outside the request that produced it. A producer
dispatches a `JobContract` through a client. A consumer receives the same job,
runs it through a middleware pipeline, and settles the outcome with the broker.

The pipeline takes a job in and returns a `JobResult` out. There is no response
envelope, because no caller waits for one.

This document describes how the contracts interact. The classes that implement
them, and the processors they talk to, are documented as each one lands.

## The Job

`Valkyrja\Queue\Message\Job\Contract\JobContract` is the one message class, and
it travels in both directions. A job is immutable: the framework builds a new
one through a `with*` method, the same way an HTTP request and a CLI input work.

The envelope is a cross-language contract, so every field is always present. A
millisecond field is authoritative, and the matching `_iso` field is derived
from it.

A job carries a payload and a set of attributes.
`Valkyrja\Queue\Message\Payload\Contract\PayloadContract` holds the data the
handler reads, and
`Valkyrja\Queue\Message\Attributes\Contract\AttributesContract` holds the
metadata a processor reads. Both are immutable for the same reason the job is.

`Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract` builds a job
and renders one for the wire. The job is a data object, so it holds no static
method: construction belongs to the factory, and a rendering belongs to a
support class.

## The Pipeline

A job runs through seven middleware stages, and each stage has its own contract
in `Valkyrja\Queue\Middleware\Contract`:

| Stage             | Runs when                             |
| ----------------- | ------------------------------------- |
| `JobReceived`     | the job arrives, before routing       |
| `RouteMatched`    | the router finds a route              |
| `RouteNotMatched` | the router finds no route             |
| `RouteDispatched` | the handler returns                   |
| `ThrowableCaught` | the handler throws                    |
| `SettlingResult`  | before the outcome reaches the broker |
| `ResultSettled`   | after the outcome reaches the broker  |

Each stage has a matching handler contract in
`Valkyrja\Queue\Middleware\Handler\Contract`, which holds the middleware of
that stage and runs it in order. `JobReceived`, `SettlingResult`, and
`ResultSettled` always run. The other four are conditional: routing decides
between `RouteMatched` and `RouteNotMatched`, and the handler decides between
`RouteDispatched` and `ThrowableCaught`.

The outcome is one of four `JobResult` cases: `ACK`, `RETRY`, `FAIL`, or
`DEAD_LETTER`. A throwable that carries
`Valkyrja\Queue\Throwable\Contract\QueueNonRetryableThrowable` is dead-lettered
at once rather than retried.

Route middleware is appended and never deduplicated. A middleware registered
twice runs twice, because the generated cache has to match reflection exactly.

## Throwables

`Valkyrja\Queue\Throwable\Contract\QueueThrowable` marks every throwable the
component raises, and each sub-component narrows it: `QueueMessageThrowable`,
`QueueMiddlewareThrowable`, and `QueueRoutingThrowable`. Each marker has an
abstract `*InvalidArgumentException` and `*RuntimeException` pair that a
concrete exception extends, so a caller can catch a whole sub-component or the
whole component.

## Routing

A job names a route, and
`Valkyrja\Queue\Routing\Dispatcher\Contract\RouterContract` turns that name
into the handler that runs it.
`Valkyrja\Queue\Routing\Collection\Contract\RouteCollectionContract` holds the
routes, `Valkyrja\Queue\Routing\Collector\Contract\RouteCollectorContract`
reads them off attributes, and
`Valkyrja\Queue\Routing\Provider\Contract\QueueRouteProviderContract` lets an
application contribute them in code.

`Valkyrja\Queue\Routing\Data\QueueRoutingData` is the generated cache of that
collection, so a production boot reads routes from a data class instead of
scanning attributes.

## Configuration

`Valkyrja\Application\Data\Contract\QueueConfigContract` is the application
config a queue consumer boots from. It names the seven middleware stages, the
route providers, and the default command the consumer runs.

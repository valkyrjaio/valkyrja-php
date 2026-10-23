# Grpc

Implements
[`GRPC.md`](https://github.com/valkyrjaio/architecture/blob/26.x/component/GRPC.md).

## Introduction

The Grpc component answers a remote procedure call. A caller names a service and
a method on it, the component routes that name to a handler, and the handler
answers with a **service response**.

A **service** is a class, and each remote method is a method of that class. The
routing key is the two names together, in the protocol's own form,
`/package.Service/Method`, and the match is exact.

The component owns no transport. An **adapter** runs a gRPC library, takes a
native call apart into the component's own types, and writes the response back.
The adapter is the one place a library is named, so the component speaks no wire
protocol.

**A message is the port's own any-or-object type.** The component adopts the
message type of no gRPC library, and bytes never reach the framework. A handler
decodes the message it was given, and the adapter translates.

This document assumes the framework, where the container resolves the call and a
provider builds the handlers.

## Declaring a service

Declare the service name on the class and the remote method on the method, where
the language has a marker for that. The collector reads each class a route
provider lists.

```php
#[Service(service: 'example.Greeter')]
final class GreeterController
{
    #[Route(name: 'SayHello', handler: [self::class, 'sayHello'])]
    #[Middleware(name: AppAuthMiddleware::class)]
    public static function sayHello(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        $call = $container->getSingleton(ServiceCallContract::class);

        return ServiceResponse::ok(
            $container->getSingleton(GreetingRepository::class)->greet($call->getMessages()),
        );
    }
}
```

| Marker          | On       | States                                           |
| --------------- | -------- | ------------------------------------------------ |
| `#[Service]`    | a class  | the full service name, such as `example.Greeter` |
| `#[Route]`      | a method | every field of the route, less the service name  |
| `#[Middleware]` | a method | one middleware class of this method              |
| `#[Handler]`    | a method | the route's handler                              |

`#[Middleware]` is repeatable, so a method may carry several.

`#[Route]` is repeatable too, and each one is a route of its own. One method
therefore answers several remote methods. A service keeps an older name beside a
new one this way.

Every other attribute on the method applies to all routes that the method
declares. Give a route its own configuration through that route's own arguments.

**Every route names a handler.** `#[Route]` takes it as an optional `handler`
argument, `#[Handler]` is the second spelling of the same field, and the
handler attribute wins when a method carries both. The collector rejects a route
that names neither, and the failure names the route, the class and the method.

An attribute takes the handler as a `[$class, $method]` pair, and PHP accepts
that pair as a `callable` only for a public static method, so a handler named in
an attribute is public and static. The attributed method is not the handler, so
the component imposes nothing on the attributed method.

### Declaring methods in a provider

A route provider returns the classes to scan and any prebuilt route, and the
application lists that provider in its config
([`PROVIDERS.md`](https://github.com/valkyrjaio/architecture/blob/26.x/convention/PROVIDERS.md)).
The component discovers nothing on its own.

```php
final class AppGrpcRouteProvider implements GrpcRouteProviderContract
{
    public function getControllerClasses(): array
    {
        return [GreeterController::class];
    }

    public function getRoutes(): array
    {
        return [
            new Route('/example.Greeter/SayGoodbye', [GoodbyeHandler::class, 'sayGoodbye']),
        ];
    }
}
```

A prebuilt route takes the full method, so a malformed one fails at boot rather
than on the first call. Each list is a literal with no conditional logic, because
the build tool reads the declaration rather than running it.

## Writing a handler

A handler takes the container and the route, and returns a service response. It
resolves the call from the container when it needs the messages, the metadata,
the deadline or the peer.

```php
public static function sayHello(ContainerContract $container, RouteContract $route): ServiceResponseContract
{
    $call = $container->getSingleton(ServiceCallContract::class);

    $name = $container->getSingleton(GreetingRepository::class)
        ->nameFor($call->getPeer()->getAddress());

    return ServiceResponse::ok(['greeting' => 'Hello ' . $name]);
}
```

Warning: a port that invokes the handler reflectively **constructs the
controller with no arguments**. A controller therefore resolves its dependencies
from the container inside the method, and it declares no constructor parameter.

Warning: resolve `ServiceCallContract` from the container rather than keeping a
reference. The router binds a copy of the call that carries the route, so a
reference taken at an earlier stage reads a call with no route.

## The call

| Asks              | Returns                                                |
| ----------------- | ------------------------------------------------------ |
| `getMethod`       | the full method, which is the routing key              |
| `getMetadata`     | the metadata the caller sent                           |
| `getDeadline`     | when the caller stops waiting                          |
| `getCancellation` | whether the caller gave up                             |
| `getPeer`         | who called, and how they authenticated                 |
| `getMessages`     | the inbound messages                                   |
| `isStreaming`     | whether the call runs under the streaming model        |
| `getRoute`        | the route, once the router resolved one                |
| `hasRoute`        | whether the call carries a route yet                   |
| `send`            | pushes one outbound message, under the streaming model |
| `cancellable`     | wraps a sequence, so iteration stops on a cancellation |
| `withRoute`       | returns a copy of the call carrying the route          |

The call is immutable, and `withRoute` is the one change. The response is
immutable too, with a `with` form for each part of it.

## The four shapes, and the two models

A method declares which side streams, and the two flags give the protocol's four
shapes.

| Shape            | Declares                                | Runs under |
| ---------------- | --------------------------------------- | ---------- |
| unary            | neither flag                            | buffered   |
| server streaming | `serverStreaming`                       | buffered   |
| client streaming | `clientStreaming`                       | buffered   |
| bidirectional    | `clientStreaming` and `serverStreaming` | streaming  |

**Under the buffered model the handler runs once.** The adapter reads every
inbound message first, so `getMessages` returns a fixed sequence, and the handler
returns its own messages on the response.

```php
#[Route(name: 'ListGreetings', handler: [self::class, 'listGreetings'], serverStreaming: true)]
public static function listGreetings(ContainerContract $container, RouteContract $route): ServiceResponseContract
{
    return ServiceResponse::ok()
        ->withMessages($container->getSingleton(GreetingRepository::class)->all());
}
```

**Under the streaming model the handler runs while messages arrive.** The adapter
calls the handler at once, `getMessages` yields each message as it arrives, and
the handler replies through `send` rather than by returning its messages.

```php
#[Route(
    name: 'Chat',
    handler: [self::class, 'chat'],
    clientStreaming: true,
    serverStreaming: true,
)]
public static function chat(ContainerContract $container, RouteContract $route): ServiceResponseContract
{
    $call = $container->getSingleton(ServiceCallContract::class);

    foreach ($call->getMessages() as $message) {
        $call->send($message);
    }

    return ServiceResponse::ok();
}
```

**Buffered is the default**, and it is the shape the pipeline is designed
around. Streaming is the deliberate exception, for a caller that waits for a
reply before it sends more. Without it such a caller would deadlock.

Warning: `send` fails under the buffered model. Return the messages on the
response instead.

Warning: `send` fails for a second send that starts while the first one runs. A
streaming handler emits from one thread.

Warning: the inbound sequence ends on a half-close, and it ends on a
cancellation too. Read the cancellation after the loop to tell the two apart.

**Middleware runs once per call, not once per message.** A stage is about the
call, so a streaming call does not multiply the pipeline.

A streaming response reaches the wire through an **outbound stream**, which the
adapter owns. The stream sends the headers, then each message, and it closes
with the terminal response.

**An outbound write respects backpressure.** A send does not hand the transport
more than it can take, and honoring that is the adapter's obligation, because
only the adapter knows the transport's writability.

## Status

A response carries a status, and the status carries a code from the gRPC
standard set: `OK`, `CANCELLED`, `UNKNOWN`, `INVALID_ARGUMENT`,
`DEADLINE_EXCEEDED`, `NOT_FOUND`, `ALREADY_EXISTS`, `PERMISSION_DENIED`,
`RESOURCE_EXHAUSTED`, `FAILED_PRECONDITION`, `ABORTED`, `OUT_OF_RANGE`,
`UNIMPLEMENTED`, `INTERNAL`, `UNAVAILABLE`, `DATA_LOSS` and `UNAUTHENTICATED`.

The set is the protocol's, so a port adds nothing to it and renames nothing in
it. `Status` carries one named constructor per code, and a response carries one
for each case a handler answers with often.

```php
$user = $container->getSingleton(UserRepository::class)->find($id);

// The method failed, and the code names the failure.
if ($user === null) {
    return ServiceResponse::of(Status::notFound('No such user.'));
}

// The method succeeded.
return ServiceResponse::ok($user);
```

| Builds                           | Answers with                         |
| -------------------------------- | ------------------------------------ |
| `ServiceResponse::ok`            | `OK`, with the messages you pass     |
| `ServiceResponse::of`            | the status you pass                  |
| `ServiceResponse::unimplemented` | `UNIMPLEMENTED`                      |
| `ServiceResponse::cancelled`     | `CANCELLED`, for the reason you pass |

| Asks on a status | Answers                                   |
| ---------------- | ----------------------------------------- |
| `getCode`        | the code                                  |
| `getMessage`     | the message that travels with the code    |
| `getDetails`     | the details, where the status carries any |
| `hasDetails`     | whether it carries details                |
| `isOk`           | whether the call succeeded                |
| `isCancellation` | whether it represents a cancellation      |

A caller tests the meaning rather than comparing codes, so `isOk` and
`isCancellation` are what a middleware branches on.

**Prefer returning a status to throwing.** A status is the protocol's own
vocabulary, and a throwable has to be mapped to one.

## Metadata

Metadata is the key and value pairs a call carries. The caller sends it one way,
and the response sends it back the other way.

```php
$traceId = $call->getMetadata()->get('x-trace-id');

return ServiceResponse::ok($user)
    ->withTrailingMetadata(new Metadata()->with('x-trace-id', $traceId));
```

| Asks          | Does                                   |
| ------------- | -------------------------------------- |
| `get`         | reads a key's first value              |
| `getAll`      | reads a key's values                   |
| `has`         | answers whether a key holds a value    |
| `isBinaryKey` | answers whether a key carries binary   |
| `with`        | returns metadata with a key replaced   |
| `withAdded`   | returns metadata with a value appended |
| `without`     | returns metadata with a key removed    |
| `toArray`     | reads every pair as an array           |

Metadata is iterable, so a middleware that copies or records every pair reads
them by iterating.

A key is case insensitive, and the metadata lowercases each one it is given. A
key that ends in `-bin` carries a binary value, and every other key carries a
string.

**Metadata validates on write.** An invalid key and a value whose type does not
match its key's kind each fail where the caller set them, rather than when the
response reaches the wire.

A response carries metadata twice. The **initial** metadata is the headers, and
the **trailing** metadata follows the last message.

Warning: the initial metadata locks once the first message reaches the wire. Set
it before the handler sends anything. The trailing metadata stays open until the
handler returns.

## The deadline and the cancellation

**The deadline and the cancellation are separate.** A deadline passes on its
own. A cancellation is something the caller did. A component that treated them
as one could not tell a slow call from an abandoned one.

| Asks on the deadline | Answers                      |
| -------------------- | ---------------------------- |
| `getAbsoluteTime`    | the instant the call expires |
| `getRemaining`       | how long is left             |
| `isExpired`          | whether the instant passed   |
| `hasDeadline`        | whether the caller set one   |

| Asks on the cancellation | Does                                  |
| ------------------------ | ------------------------------------- |
| `isCancelled`            | answers whether the caller gave up    |
| `getReason`              | reads which of the two causes it was  |
| `throwIfCancelled`       | throws instead of answering           |
| `onCancelled`            | runs a listener when the call cancels |

**Neither one is ever absent.** Each has a sentinel for "none", so no caller
checks for an empty value. The sentinel deadline is a finite far-future instant
rather than an unbounded one, so arithmetic on it cannot overflow.

The reason is where the two meet. A cancellation reports either that the caller
cancelled or that the deadline passed, so code tests the cancellation and reads
the reason where the difference matters.

### Cancellation is cooperative

A caller can give up at any time, and **the framework never interrupts running
handler code**. Forcible interruption is unsafe in every one of these languages,
so the model is the same everywhere.

The framework checks the cancellation between its own steps and stops advancing
when it is set. A handler that runs a long computation, or that calls a library
which knows nothing about cancellation, checks for itself.

```php
public static function chargeEach(ContainerContract $container, RouteContract $route): ServiceResponseContract
{
    $call = $container->getSingleton(ServiceCallContract::class);

    foreach ($call->getMessages() as $order) {
        if ($call->getCancellation()->isCancelled()) {
            return ServiceResponse::cancelled($call->getCancellation()->getReason());
        }

        $container->getSingleton(PaymentGateway::class)->charge($order);
    }

    return ServiceResponse::ok();
}
```

`cancellable` does the same for a sequence the handler iterates, so a loop over
it ends when the call cancels.

**A detected cancellation produces a response, not a throwable.** So
cancellation is never a special case in the `ThrowableCaught` stage, and that
stage handles only real failures.

A handler that does not check runs to completion after the caller has gone.

## Middleware

Seven stages run around a call, and each one has its own contract. An
application implements the contract for the stage it wants, and one class may
implement several.

| Stage             | Runs                   | May change                 |
| ----------------- | ---------------------- | -------------------------- |
| `CallReceived`    | always                 | the call, or the response  |
| `RouteMatched`    | a route was found      | the route, or the response |
| `RouteNotMatched` | no route was found     | the response               |
| `RouteDispatched` | a route was found      | the response               |
| `ThrowableCaught` | a throwable was caught | the response               |
| `SendingResponse` | always                 | the response               |
| `ResponseSent`    | always                 | nothing                    |

Each stage's contract declares one method, named for the stage, and that method
takes the stage's own handler as its last parameter. Calling the handler passes
the work to the next middleware; returning a response instead ends the pipeline
there.

```php
final class AppAuthMiddleware implements RouteMatchedMiddlewareContract
{
    public function routeMatched(
        ServiceCallContract $call,
        RouteContract $route,
        RouteMatchedHandlerContract $handler,
    ): RouteContract|ServiceResponseContract {
        if (! $call->getMetadata()->has('authorization')) {
            return ServiceResponse::of(Status::unauthenticated('No credentials.'));
        }

        return $handler->routeMatched($call, $route);
    }
}
```

`CallReceived` and `RouteMatched` can **short-circuit**: each returns either the
thing it received or a response, and a response skips every remaining stage
before `SendingResponse`.

`SendingResponse` is the last place the response changes, because the adapter
writes it next. Under the streaming model it runs at the stream open, against a
response whose initial metadata becomes the headers. `ResponseSent` runs after
the write, so it holds work the caller never waits for.

### Scheduling middleware

The application's gRPC config holds one list per stage, and the provider gives
each stage handler the list for that stage.

```php
new GrpcConfig(
    dir:                       __DIR__,
    routeMatchedMiddleware:    [AppAuthMiddleware::class],
    throwableCaughtMiddleware: [LogThrowableCaughtMiddleware::class],
);
```

`GrpcConfig` declares every property as a promoted readonly argument, so a
subclass cannot replace one. Pass the named argument, or write your own class
against `GrpcConfigContract`.

Middleware runs in registration order, and the framework removes no repeat.
Scheduling one class twice at a stage runs it twice.

Every middleware class needs a binding, because the stage handler resolves each
one through the container ([Container](../Container/README.md) covers bindings).

### Middleware on one method

Declare it on the method, and the router appends it to the stage handlers for
that call alone. Each route carries a list per stage, and the stage a class lands
in follows from the contracts the class implements.

**Two spellings declare the same lists.** `#[Route]` takes one list argument for
each stage, and `#[Middleware]` names one class the collector appends to every
stage list whose contract the class implements. A method that carries both gets
the argument's classes first.

```php
#[Route(
    name: 'SayHello',
    handler: [self::class, 'sayHello'],
    routeMatchedMiddleware: [AppAuthMiddleware::class],
)]
#[Middleware(name: AppAuditMiddleware::class)]
public static function sayHello(ContainerContract $container, RouteContract $route): ServiceResponseContract
```

`RouteNotMatched` takes no method-level middleware, because no route matched. An
unknown method answers `UNIMPLEMENTED`, and that stage is where an application
replaces the answer.

## The adapter

The component declares the adapter contract and ships no adapter. An
implementation owns both ends of a call: taking a native call apart into the
component's own types, and writing the component's response back out.

```php
interface ServiceAdapterContract
{
    public function start(ServiceHandlerContract $handler): void;

    public function stop(): void;
}
```

An adapter names a gRPC library, so it is the one piece that cannot live in the
component. An application supplies one, or installs a package that does. The
address it binds, the TLS settings and the thread pool are the implementation's
own, because this protocol's server is the transport rather than the language's
own.

The write happens **between** `SendingResponse` and `ResponseSent`, which is
what makes those two stages meaningful. The first can still change the response,
and the second runs once the response is on the wire.

Warning: no provider binds the adapter contract. The entry point of the runtime
starts the adapter, and it hands the service handler over.

## Configuration

The component reads the application's gRPC config for the pipeline and for the
inbound message limit.

Your application has one config class. Where it implements this contract, the
provider binds your class. Where it does not, the provider binds the component's
own default. So implementing the contract is how you override, and implementing
nothing leaves every default in place.

| Property                    | Holds                                   | Default            |
| --------------------------- | --------------------------------------- | ------------------ |
| `maxInboundMessages`        | the inbound messages one call may carry | 1000               |
| `callReceivedMiddleware`    | the middleware at `CallReceived`        | none               |
| `routeMatchedMiddleware`    | the middleware at `RouteMatched`        | none               |
| `routeNotMatchedMiddleware` | the middleware at `RouteNotMatched`     | none               |
| `routeDispatchedMiddleware` | the middleware at `RouteDispatched`     | none               |
| `throwableCaughtMiddleware` | the middleware at `ThrowableCaught`     | the log middleware |
| `sendingResponseMiddleware` | the middleware at `SendingResponse`     | none               |
| `responseSentMiddleware`    | the middleware at `ResponseSent`        | none               |

```php
Grpc::bootstrap(new GrpcConfig(
    dir:                    __DIR__,
    maxInboundMessages:     200,
    routeMatchedMiddleware: [AppAuthMiddleware::class],
));
```

`maxInboundMessages` bounds the inbound side. Under the buffered model the
adapter rejects a call that exceeds the bound with `RESOURCE_EXHAUSTED`. Under
the streaming model the value bounds the flow-control window, and it rejects no
call.

Replacing `throwableCaughtMiddleware` replaces the default, so a config that
sets it names the log middleware again where a failure still has to be recorded
([Log](../Log/README.md)).

## Service registration

The component ships one provider per area, and they bind:

| Binding                     | Is                                                    |
| --------------------------- | ----------------------------------------------------- |
| `RouteCollectionContract`   | the methods, and the lookup the router uses           |
| `RouteCollectorContract`    | the collector for the way this port declares a method |
| `RouterContract`            | the router                                            |
| each stage handler contract | that stage's handler, as a singleton                  |
| `ServiceHandlerContract`    | the handler that runs a call end to end               |
| each shipped middleware     | bound under its own name                              |

```php
final class GrpcServerServiceProvider implements ServiceProviderContract
{
    public function publishers(): array
    {
        return [
            ServiceHandlerContract::class => [self::class, 'publishServiceHandler'],
        ];
    }

    public static function publishServiceHandler(ContainerContract $container): void
    {
        $container->setSingleton(ServiceHandlerContract::class, new ServiceHandler(
            container:              $container,
            router:                 $container->getSingleton(RouterContract::class),
            callReceivedHandler:    $container->getSingleton(CallReceivedHandlerContract::class),
            throwableCaughtHandler: $container->getSingleton(ThrowableCaughtHandlerContract::class),
            sendingResponseHandler: $container->getSingleton(SendingResponseHandlerContract::class),
            responseSentHandler:    $container->getSingleton(ResponseSentHandlerContract::class),
        ));
    }
}
```

**The router and the stage handlers resolve the same instances.** Each stage
handler is a singleton, so the middleware a method appends at a stage reaches the
handler that runs it. Two instances means that middleware never runs, and
nothing reports it.

The service handler declares `run` as the entry point an adapter calls. `run`
runs `handle`, which produces the response from the first five stages, and then
`sending`. The adapter writes the response `run` returns, then calls
`terminate`.

Warning: a port that invokes the handler reflectively **rethrows the handler's
own throwable**. A reflective call wraps whatever the target threw, so
unwrapping it is required. Without it the `ThrowableCaught` stage sees the
reflection wrapper rather than the failure.

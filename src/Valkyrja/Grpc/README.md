# gRPC

The gRPC component matches an inbound call to a handler and runs middleware
around every phase of the dispatch. A gRPC method name is a fixed string, so
the router performs a map lookup rather than a pattern match. There is no
dynamic segment and no regular expression.

The call and response classes carry an agnostic message payload, typed `mixed`.
The component imports no protobuf library and no gRPC library. A worker adapter
translates between the native types and these classes at its own boundary.

The component has five parts:

- **`Grpc\Message`** — the call, the response, the status, the metadata, the
  deadline, the cancellation token, and the peer.
- **`Grpc\Routing`** — the service attributes, the collector, the service map,
  and the router.
- **`Grpc\Middleware`** — the seven stage contracts and their handlers.
- **`Grpc\Server`** — the service handler, the adapter contract, and the
  built-in middleware.
- **`Grpc\Support`** — the cancellation check that every orchestrator boundary
  calls.

## Configuration and entry point

`Valkyrja\Application\Data\GrpcConfig` holds the configuration as constructor
arguments, and every argument has a default. It repeats the base properties
that `Config` carries, and it adds one cap and seven middleware lists:

| Property                    | Default                                 |
| --------------------------- | --------------------------------------- |
| `maxInboundMessages`        | `1000`                                  |
| `providers`                 | `[GrpcApplicationComponentProvider]`    |
| `callReceivedMiddleware`    | `[]`                                    |
| `routeMatchedMiddleware`    | `[]`                                    |
| `routeNotMatchedMiddleware` | `[]`                                    |
| `routeDispatchedMiddleware` | `[]`                                    |
| `throwableCaughtMiddleware` | `[LogThrowableCaughtMiddleware::class]` |
| `sendingResponseMiddleware` | `[]`                                    |
| `responseSentMiddleware`    | `[]`                                    |

The config holds no port. Port binding is an adapter setting, so it lives on
the adapter implementation.

`maxInboundMessages` bounds the inbound direction, and the adapter enforces it.
Under the buffered model the adapter caps the messages it buffers before
dispatch. Under the streaming model the adapter reads the same number as the
high-water mark of the live inbound queue. The framework puts no bound on the
outbound direction. A worker adapter applies its own backpressure there, which
[Worker adapters](#worker-adapters) describes.

`Grpc::handle()` is the entry point. It takes a config and one call, and it
returns the response:

```php
// app/bin/grpc
use App\Grpc\Config;
use Valkyrja\Application\Entry\Grpc;
use Valkyrja\Grpc\Message\Call\ServiceCall;

require __DIR__ . '/../vendor/autoload.php';

$response = Grpc::handle(
    new Config(),
    ServiceCall::unary('/pkg.Greeter/SayHello', 'world')
);
```

`Grpc::handle()` bootstraps the application for one call, so it suits a test
and a single-call script. A server runs many calls from one bootstrap, which
[Persistent workers](#persistent-workers) describes.

## Service registration

A route key is a fully-qualified method of the form `/package.Service/Method`.
Each segment is a protobuf identifier, so each segment holds letters, digits
and an underscore. The package part also holds a dot. `Route` rejects any
other method and throws `GrpcRoutingInvalidMethodException`.

### Route providers

A route provider names the controllers the scan reads, and it returns any route
the application builds itself:

```php
namespace App\Grpc\Provider;

use Valkyrja\Grpc\Routing\Provider\Contract\GrpcRouteProviderContract;

class AppGrpcRouteProvider implements GrpcRouteProviderContract
{
    public function getControllerClasses(): array
    {
        return [GreeterController::class];
    }

    public function getRoutes(): array
    {
        return [];
    }
}
```

A component provider returns the route provider from `getGrpcProviders()`, the
same way it returns an HTTP route provider from `getHttpProviders()`.

### Attribute routes

`#[Service]` names the service on the controller class, and `#[Method]` names
the method on each handler:

```php
namespace App\Grpc\Controller;

use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Routing\Attribute\Method;
use Valkyrja\Grpc\Routing\Attribute\Service;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

#[Service(service: 'pkg.Greeter')]
final class GreeterController
{
    #[Method(name: 'SayHello')]
    public static function sayHello(
        ContainerContract $container,
        RouteContract $route
    ): ServiceResponseContract {
        $call = $container->getSingleton(ServiceCallContract::class);

        return ServiceResponse::ok('hello');
    }
}
```

The two attributes above register `/pkg.Greeter/SayHello`. `#[Method]` also
takes `clientStreaming`, `serverStreaming`, `requestType` and `responseType`.
The two streaming flags choose the call shape, which
[Call shapes](#call-shapes) describes. The framework reads neither message
type, and carries both for the adapter.

The scan runs in debug mode. Outside debug mode the service map loads from the
`GrpcRoutingData` class that `sindri` generates.

### Route handlers

A handler is a public static method, and it takes the container and the route.
The container holds the call, so the signature names no call parameter. The
scan rejects a method that is not public or not static, and it throws
`GrpcRoutingInvalidHandlerException`. The scan is where the developer can still
act on the fault.

`#[MethodHandler]` supplies a handler explicitly. The attributed method then
runs nothing, so the scan checks it no further:

```php
#[Method(name: 'SayHello')]
#[MethodHandler([GreeterService::class, 'sayHello'])]
public function sayHello(
    ContainerContract $container,
    RouteContract $route
): ServiceResponseContract {
    return ServiceResponse::ok('never reached');
}
```

### Per-route middleware

`#[Middleware]` schedules one middleware for the route. The attribute is
repeatable, so a method takes several:

```php
#[Method(name: 'SayHello')]
#[Middleware(name: AuditMiddleware::class)]
#[Middleware(name: RateLimitMiddleware::class)]
public static function sayHello(/* … */): ServiceResponseContract
```

The collector reads the contracts each middleware implements, and it adds the
middleware to every matching stage. A middleware that serves two stages runs at
both. Middleware is appended and never deduplicated, so a class scheduled twice
runs twice.

An application binds each middleware it schedules. The container resolves a
middleware by class name, and it throws for a name that nothing bound.

## Calls and responses

### The call

`ServiceCallContract` carries one inbound call. The router binds it in the
container under `ServiceCallContract`, so a handler and a middleware read it
from there:

| Method              | Returns                     |
| ------------------- | --------------------------- |
| `getMethod()`       | the fully-qualified method  |
| `getMetadata()`     | the inbound metadata        |
| `getDeadline()`     | the deadline                |
| `getCancellation()` | the cancellation token      |
| `getPeer()`         | the peer                    |
| `getMessages()`     | the inbound messages        |
| `isStreaming()`     | whether a push sink exists  |
| `send()`            | pushes one outbound message |
| `getRoute()`        | the resolved route, or null |
| `hasRoute()`        | whether a route resolved    |
| `withRoute()`       | a clone carrying a route    |
| `cancellable()`     | a cancellation-aware source |

`getRoute()` answers the route the pipeline resolved. The router sets the route
again whenever RouteMatched middleware returns a replacement, so every later
stage reads the route the middleware chose.

### The response

`ServiceResponseContract` carries the status, the messages, and two metadata
maps. The initial metadata becomes the response headers, and the trailing
metadata becomes the trailers. `ServiceResponse` holds three named
constructors: `of()`, `ok()` and `unimplemented()`. It also holds
`cancelled()`, which builds the response for a cancellation reason.

### Status

`Status` carries a code, a message, and the optional `google.rpc.Status`
protobuf bytes. `StatusCode` holds the seventeen wire codes, and each code
carries a default message through `getDefaultMessage()`. `StatusText` holds
those messages, and each of its case names matches a `StatusCode` case name.

`Status` holds one named constructor for each code, so a handler returns a
domain outcome without naming a code:

```php
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Message\Status\Status;

return ServiceResponse::of(Status::notFound('No user holds that id'));
```

The framework maps only cancellation and the catch-all. A handler returns every
other outcome on the response, the same way an HTTP controller returns a status
code. `withCode()` resets the message to the new code's default, so a status
never reports a message that describes another code.

### Metadata

`Metadata` keys are case-insensitive, and the class lower-cases each one. A key
holds lowercase letters, digits, `-`, `_` and `.`. A key that ends in `-bin`
carries arbitrary bytes. Every other key carries printable ASCII.

`Metadata` validates a key and a value on construction, which every `with*`
operation flows through. An invalid key raises
`GrpcMetadataInvalidKeyException`. An invalid value raises
`GrpcMetadataInvalidValueException`. The adapter can therefore trust the values it
writes to the wire.

### Deadline

`Deadline` reports an absolute expiry time and the remaining budget, both in
seconds. `fromTimeout()` builds a deadline from a relative timeout, and
`fromAbsolute()` builds one from a unix timestamp. `none()` builds the sentinel
for a call that carries no deadline.

The sentinel is finite. `none()` reports `INFINITE_REMAINING` from
`getRemaining()`, which is one hundred years in seconds. Arithmetic on the
sentinel therefore cannot overflow. Every port reproduces the same value.

### Peer

`Peer` reports the address, the address type, and the auth context.
`AddressType` holds `IPV4`, `IPV6`, `UNIX` and `UNKNOWN`. `AuthContext`
reports the transport security type, the peer subject, and the peer
certificates.

## Cancellation

A cancelled call returns a response, and it raises no throwable. Every
orchestrator boundary therefore handles a cancelled call the same way.

`Cancellation::checkAndFinalize()` runs the two-question check:

1. Has cancellation fired on the call, or has the deadline elapsed?
2. Does the response in hand already carry a cancellation status?

A positive answer returns the cancellation response, which fast-exits up the
stack. A negative answer returns null, which continues the dispatch.

The two questions are not symmetric. Before delegation the check overlays the
cancellation status on an existing response, which keeps the metadata that
earlier middleware accumulated. It builds a fresh response when none exists
yet. After delegation the check passes an already-cancelled response through
unchanged.

A handler reads the token directly for its own checks:

```php
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;

$call = $container->getSingleton(ServiceCallContract::class);

// Raises GrpcCancelledException, which the framework maps to CANCELLED.
$call->getCancellation()->throwIfCancelled();
```

`cancellable()` wraps a source so iteration stops once the call is cancelled.
It checks before the first pull, and again after each item. A server-streaming
handler wraps its own generator with it:

```php
return ServiceResponse::ok()->withMessages($call->cancellable($messages));
```

`cancellable()` stops yielding rather than throw. A cancelled call therefore
closes normally instead of raising through the transport listener.

## The middleware pipeline

Each stage has one contract and one handler. A middleware returns the next
value, or it short-circuits by returning a response:

| Stage             | Receives                  | Returns                |
| ----------------- | ------------------------- | ---------------------- |
| `CallReceived`    | the call                  | a call, or a response  |
| `RouteMatched`    | the call and the route    | a route, or a response |
| `RouteNotMatched` | the call and the response | a response             |
| `RouteDispatched` | the call, response, route | a response             |
| `ThrowableCaught` | the call, response, error | a response             |
| `SendingResponse` | the call and the response | a response             |
| `ResponseSent`    | the call and the response | nothing                |

Five stage handlers run the two-question check before and after they delegate.
`SendingResponse` and `ResponseSent` run no check, because both stages always
run. A cancelled call still sends a status and still terminates.

`ServiceHandler` orchestrates the stages. `handle()` runs the call through
`CallReceived` and the router. `sending()` runs `SendingResponse`.
`terminate()` runs `ResponseSent`. `run()` calls `handle()` and then
`sending()`, so an adapter writes the wire between `sending()` and
`terminate()`.

`ThrowableCaught` runs a middleware, so `handle()` guards that stage
separately. A stage that throws leaves the mapped status standing, so the
client receives a status rather than nothing.

`LogThrowableCaughtMiddleware` logs a caught throwable against the call's
method. `GrpcConfig` schedules it by default, so errors reach the log the way
they do in HTTP and CLI.

## Call shapes

A method's declared flags choose the shape, and the client's behavior does not.

**The buffered model** serves a unary, a server-streaming and a
client-streaming method. The adapter buffers the inbound messages, and on
half-close it builds the call once. The handler returns one response, and the
adapter drains its messages to the wire.

**The streaming model** serves a bidirectional method. The adapter invokes the
handler at once rather than after half-close. `getMessages()` is then a live
stream, and `send()` pushes an outbound message while the handler still reads
inbound.

`send()` serializes the sends. A send issued while another is in flight raises
`GrpcConcurrentSendException`, because the transport is not re-entrant. The
guard lives with the sink, so a clone from `withRoute()` shares it. `send()` on
a buffered call raises `GrpcNonStreamingSendException`.

## Persistent workers

`Valkyrja\Application\Entry\Abstract\WorkerGrpc` bootstraps once and serves
many calls. It freezes the parent application, and it gives every call its own
`ChildContainer` and `ChildApplication`. The worker discards both when the
call ends.

`dispatch()` serves one buffered call, and it hands the response to a writer.
`dispatchStreaming()` serves one streaming call against an
`OutboundStreamContract`. Each method runs the whole pipeline under one guard,
so a throw still runs `terminate()`.

`dispatchStreaming()` opens the stream once, at the first emit, or at the close
when the handler emits nothing. `SendingResponse` runs at that open, against an
OK shell whose initial metadata becomes the response headers. A throwable that
leaves `dispatchStreaming()` leaves the stream closed whenever the stream had
been opened. An adapter that catches one therefore sends no status of its own.

## Worker adapters

`ServiceAdapterContract` declares `start()` and `stop()`. An adapter binds the
port, configures TLS, and translates the native types. None of that work is
part of the agnostic surface.

An adapter carries two transport obligations:

- It checks whether the transport can accept a message before it writes one.
  When the transport cannot, the adapter pauses the drain and resumes it later.
  A paused drain is not a cancelled call.
- It keeps the handler off the path that delivers the transport's callbacks.
  That path carries the resume signal the paused drain waits for, so a handler
  that occupies the path blocks its own resume.

## Container bindings

Three service providers publish the component's services:

| Provider                        | Binds                                                                                                                                                                                                                               |
| ------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GrpcMiddlewareServiceProvider` | `CallReceivedHandlerContract`, `RouteMatchedHandlerContract`, `RouteNotMatchedHandlerContract`, `RouteDispatchedHandlerContract`, `ThrowableCaughtHandlerContract`, `SendingResponseHandlerContract`, `ResponseSentHandlerContract` |
| `GrpcRoutingServiceProvider`    | `RouteCollectorContract`, `RouterContract`, `RouteCollectionContract`, `GrpcRoutingData`                                                                                                                                            |
| `GrpcServerServiceProvider`     | `ServiceHandlerContract`, `LogThrowableCaughtMiddleware`                                                                                                                                                                            |

`GrpcApplicationComponentProvider` composes the component providers a gRPC
application needs. It declares the Container and Event components through
`ApplicationComponentProvider`, and it adds the gRPC Middleware, Routing and
Server components plus Log. It omits every HTTP and CLI component, and it omits
View, because a gRPC call renders no template.

The router and the service handler share the stage-handler singletons. A route
registers its `SendingResponse` and `ResponseSent` middleware on the router,
and the service handler consumes both stages. Sharing the singletons is what
makes that middleware run.

## Exceptions

Every exception this component throws implements
`Valkyrja\Grpc\Throwable\Contract\GrpcThrowable`, which extends the
framework-wide `ValkyrjaThrowable`. Two abstract bases hold the component's
exceptions: `GrpcRuntimeException` and `GrpcInvalidArgumentException` in
`Valkyrja\Grpc\Throwable\Exception\Abstract`.

Each subcomponent adds its own throwable contract and its own two abstract
bases. `GrpcRoutingThrowable`, `GrpcMiddlewareThrowable` and
`GrpcServerThrowable` each extend `GrpcThrowable`.

| Exception                            | Raised when                                                        |
| ------------------------------------ | ------------------------------------------------------------------ |
| `GrpcCancelledException`                 | a cooperative check finds the call cancelled                       |
| `GrpcConcurrentSendException`        | a send runs while another send is in flight                        |
| `GrpcNonStreamingSendException`      | `send()` runs on a buffered call                                   |
| `GrpcMetadataInvalidKeyException`        | a metadata key is not a valid header name                          |
| `GrpcMetadataInvalidValueException`      | an ASCII key carries a non-printable value                         |
| `GrpcRoutingInvalidMethodException`  | a method is not `/package.Service/Method`                          |
| `GrpcRoutingInvalidHandlerException` | an attributed handler is not public static, or returns no response |

Catch `GrpcThrowable` to handle every exception from this component. Catch
`ValkyrjaThrowable` to handle every framework exception. Neither catch masks an
unrelated error.

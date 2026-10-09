# Queue

## Introduction

The Queue component runs a job outside the request that produced it. A consumer
receives a job, runs the job through a middleware pipeline, and returns one of
four outcomes. The outcome tells the processor what to do with the job.

## Writing a Job Handler

A job names a route, and the route holds the handler that runs the job. Every
handler has this signature:

```php
callable(ContainerContract $container, RouteContract $route): JobResult
```

The router calls the handler with the container and the matched route. The
handler reads the job from the container, does the work, and returns the
outcome:

```php
use App\Notification\Contract\NotifierContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;

final class SendWelcomeNotificationHandler
{
    public static function handle(ContainerContract $container, RouteContract $route): JobResult
    {
        $payload = $container->getSingleton(JobContract::class)->getPayload();
        $message = $payload->get('message');

        if (!is_string($message)) {
            return JobResult::FAIL;
        }

        $notifier = $container->getSingleton(NotifierContract::class);
        $notifier->notify($message);

        return JobResult::ACK;
    }
}
```

The payload holds the data the producer sent. A retry cannot fix a bad payload,
so the handler returns `FAIL`.

The attributes hold the metadata that middleware and the handler read. The
middleware example below reads an attribute.

## Registering a Route

A route provider tells the application where the routes are. Implement
`QueueRouteProviderContract`. `getControllerClasses()` names the handler
classes that the collector reads routes from, and `getRoutes()` returns the
routes the application builds in code:

```php
use Valkyrja\Queue\Routing\Provider\Contract\QueueRouteProviderContract;

final class AppQueueRouteProvider implements QueueRouteProviderContract
{
    public function getControllerClasses(): array
    {
        return [SendWelcomeNotificationHandler::class];
    }

    public function getRoutes(): array
    {
        return [];
    }
}
```

## Returning an Outcome

`Valkyrja\Queue\Message\Enum\JobResult` holds the four outcomes:

| Outcome       | What it means                                                       |
| ------------- | ------------------------------------------------------------------- |
| `ACK`         | The job is done. The processor removes the job.                     |
| `RETRY`       | The processor redelivers the job after its retry delay.             |
| `FAIL`        | The handler gives up. The job goes to the dead-letter destination.  |
| `DEAD_LETTER` | The retry chain ended. The job goes to the dead-letter destination. |

A handler returns `ACK`, `RETRY`, or `FAIL`. The framework returns
`DEAD_LETTER`. `FAIL` is the handler's own decision, and `DEAD_LETTER` is a
retry chain that reached the job's max attempts.

Two methods read an outcome. `JobResult::isTerminal()` reports that the job's
life is over, and every outcome but `RETRY` is terminal.
`JobResult::isDeadLettered()` reports that the job goes to the dead-letter
destination.

## Marking a Throwable Non-Retryable

The pipeline must not retry a throwable that carries
`Valkyrja\Queue\Throwable\Contract\QueueNonRetryableThrowable`. Add the
contract to an application throwable that a retry cannot fix:

```php
use Valkyrja\Queue\Throwable\Contract\QueueNonRetryableThrowable;
use Valkyrja\Throwable\Exception\Abstract\ValkyrjaRuntimeException;

final class InvalidRecipientException extends ValkyrjaRuntimeException implements QueueNonRetryableThrowable
{
}
```

`Valkyrja\Queue\Throwable\Contract\QueueThrowable` marks every throwable the
component raises. `QueueNonRetryableThrowable` extends it, so
`catch (QueueThrowable)` also catches an application throwable that carries the
marker.

## Writing Middleware

Each stage has its own contract in `Valkyrja\Queue\Middleware\Contract`. A
middleware class implements the contract of each stage it runs in:

| Contract                            | Runs when                                |
| ----------------------------------- | ---------------------------------------- |
| `JobReceivedMiddlewareContract`     | the job arrives, before routing          |
| `RouteMatchedMiddlewareContract`    | the router finds a route                 |
| `RouteNotMatchedMiddlewareContract` | the router finds no route                |
| `RouteDispatchedMiddlewareContract` | the handler returns                      |
| `ThrowableCaughtMiddlewareContract` | an earlier stage throws                  |
| `SettlingResultMiddlewareContract`  | before the outcome reaches the processor |
| `ResultSettledMiddlewareContract`   | after the outcome reaches the processor  |

Each middleware calls its handler to continue, and returns the type its own
stage declares. A `JobReceived` or a `RouteMatched` middleware returns a
`JobResult` instead, to stop the job before the handler runs:

```php
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Middleware\Contract\JobReceivedMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Contract\JobReceivedHandlerContract;

final class RequireTenantAttributeMiddleware implements JobReceivedMiddlewareContract
{
    public function jobReceived(
        JobContract $job,
        JobReceivedHandlerContract $handler
    ): JobContract|JobResult {
        if ($job->getAttributes()->getFirst('tenant') === null) {
            return JobResult::FAIL;
        }

        return $handler->jobReceived($job);
    }
}
```

## Configuration

A queue consumer boots from an application config that implements
`Valkyrja\Application\Data\Contract\QueueConfigContract`. The contract adds one
middleware array for each stage to the properties every application config
carries. Each array holds the class name of a middleware for that stage:

```php
use Valkyrja\Application\Data\Config;
use Valkyrja\Application\Data\Contract\QueueConfigContract;

final class AppQueueConfig extends Config implements QueueConfigContract
{
    public array $jobReceivedMiddleware = [RequireTenantAttributeMiddleware::class];

    public array $routeMatchedMiddleware = [];

    public array $routeNotMatchedMiddleware = [];

    public array $routeDispatchedMiddleware = [];

    public array $throwableCaughtMiddleware = [];

    public array $settlingResultMiddleware = [];

    public array $resultSettledMiddleware = [];
}
```

`Config` carries the shared properties, and the
[Application README](../Application/README.md#configuration) covers each one.

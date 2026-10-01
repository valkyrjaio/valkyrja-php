# Event Dispatching

## Introduction

The Event component implements [PSR-14](https://www.php-fig.org/psr/psr-14/).
Any PSR-14 event object works with the dispatcher, and the dispatcher serves
anywhere a `Psr\EventDispatcher\EventDispatcherInterface` is expected. That
includes stoppable events: the dispatcher honors
`Psr\EventDispatcher\StoppableEventInterface`, as the specification requires.
On top of PSR-14, the component adds attribute-based listener registration,
dispatch by class name with argument passing, and the collection of listener
return values.

## Core Concepts

An **event** is a plain PHP object with no required base class. It carries the
data for one occurrence in the application.

A **listener** connects one event class to one **handler**. A handler is a
callable that the dispatcher invokes when the event is dispatched.

The **dispatcher** finds the listeners for an event and invokes each handler
in order. The dispatcher contract is
`Valkyrja\Event\Dispatcher\Contract\EventDispatcherContract`.

The **listener collection** holds every registered listener. The collection
matches listeners by the event's exact class name, so a listener registered
for a parent class does not run for a subclass.

## Defining an Event

An event is a class that you write. Give it a constructor that takes the data,
and expose the data through readonly properties or getters:

```php
class UserRegistered
{
    public function __construct(
        public readonly User $user,
    ) {
    }
}
```

Dispatch the event where the occurrence happens, and read the data in each
handler through `$event->user`.

Two optional contracts and one PSR interface change how the dispatcher treats
an event:

- [`ArgumentsCapableEventContract`](#passing-arguments-to-events) — the
  dispatcher passes the call-site arguments into the event on a dispatch by
  class name.
- [`DispatchCollectableEventContract`](#collecting-listener-return-values) —
  the event collects each handler's return value.
- [`StoppableEventInterface`](#stoppable-events) — the event stops the
  remaining listeners.

## Dispatching Events

The dispatcher contract declares six methods:

```php
public function dispatch(object $event): object;
public function dispatchIfHasListeners(object $event): object;
public function dispatchById(string $eventId, array $arguments = []): object;
public function dispatchByIdIfHasListeners(string $eventId, array $arguments = []): object;
public function dispatchListeners(object $event, ListenerContract ...$listeners): object;
public function dispatchListener(object $event, ListenerContract $listener): object;
```

**`dispatch()`** — the PSR-14 method. Pass a constructed event object. The
dispatcher invokes every registered handler in order and returns the event.

```php
$dispatcher->dispatch(new UserRegistered($user));
```

**`dispatchIfHasListeners()`** — the same as `dispatch()`, but the dispatcher
invokes the listeners only when at least one listener is registered for the
event. The method otherwise returns the event unchanged. Use this variant for
an optional hook: the application dispatches the hook at a fixed point, and an
installation that registers no listener pays one collection check.

```php
$dispatcher->dispatchIfHasListeners(new UserRegistered($user));
```

**`dispatchById()`** — dispatch by class name. The container resolves the
class name into the event, and the dispatcher then invokes the listeners. See
[Resolving Events From the Container](#resolving-events-from-the-container).
The dispatcher throws an `EventInvalidEventException` when the container
returns a different type.

```php
$dispatcher->dispatchById(UserRegistered::class, [$user]);
```

**`dispatchByIdIfHasListeners()`** — the same as `dispatchById()`, but the
dispatcher invokes the listeners only when at least one listener is registered
for the class name. The dispatcher resolves the event from the container
either way, and returns it.

```php
$dispatcher->dispatchByIdIfHasListeners(UserRegistered::class, [$user]);
```

**`dispatchListeners()`** — dispatch an event against the given listeners
only. The dispatcher does not consult the registered collection. Use this for
a targeted, ad hoc dispatch.

```php
$dispatcher->dispatchListeners($event, $listenerOne, $listenerTwo);
```

**`dispatchListener()`** — invoke one listener's handler against an event.
The return value collects here too. The stoppable check belongs to
`dispatchListeners()`, so a direct `dispatchListener()` call invokes the
handler even when propagation is stopped.

```php
$dispatcher->dispatchListener($event, $listener);
```

## Resolving Events From the Container

`dispatchById()` and `dispatchByIdIfHasListeners()` take a class name, not an
event object. The dispatcher asks the container for that class name and passes
the call-site arguments along. The dispatcher constructs nothing itself.

Warning: a binding is required. The container builds nothing that a binding
does not describe, so the dispatch throws a
`ContainerInvalidReferenceException` when the container resolves nothing for
the class name.

Bind each event that you dispatch by class name. The binding is a callable,
and the container gives the call-site arguments to it, so the binding
constructs the event from them:

```php
use Valkyrja\Container\Manager\Contract\ContainerContract;

$container->bind(
    UserRegistered::class,
    static fn (ContainerContract $container, array $arguments): UserRegistered => new UserRegistered($arguments[0]),
);
```

Two exceptions report the two failures:

- `Valkyrja\Container\Throwable\Exception\ContainerInvalidReferenceException` —
  the container resolves nothing for the class name.
- `Valkyrja\Event\Throwable\Exception\EventInvalidEventException` — the
  container resolves the class name to a different type.

## Listener Handlers

A handler has the signature:

```php
callable(ContainerContract $container, array<string, mixed> $arguments): mixed
```

The dispatcher invokes the handler as:

```php
$handler($container, ['event' => $event]);
```

The `$arguments` array holds the dispatched event under the `event` key.

### Resolving Dependencies

The dispatcher passes the container into every handler, so a handler resolves
its dependencies at invocation time. No dependency is constructed before the
event is dispatched:

```php
use Valkyrja\Container\Manager\Contract\ContainerContract;

class WelcomeMailListener
{
    /** @param array<string, mixed> $arguments */
    public static function handle(ContainerContract $container, array $arguments): void
    {
        /** @var UserRegistered $event */
        $event = $arguments['event'];

        $container->get(Mailer::class)->sendWelcome($event->user);
    }
}
```

Warning: the container has no autowiring. Bind each service that a handler
resolves. `get()` throws a `ContainerInvalidReferenceException` for an unbound
class. Register the binding in a service provider, the same way the
[complete example](#a-complete-example) does.

### Return Values

A handler can return a value. When the event implements
`DispatchCollectableEventContract`, the dispatcher passes the return value to
the event's `addDispatch()` method. See
[Collecting Listener Return Values](#collecting-listener-return-values). The
dispatcher otherwise discards the value.

### Caching Trade-off

How you express a handler decides whether the handler participates in the data
file cache. The cache captures the full listener set in a generated PHP class,
so a production boot pays no registration overhead.

**An array callable can be cached.** A handler expressed as
`[NotificationService::class, 'handle']` is a plain array, so the framework
writes it to a generated data file without loss.

**A closure cannot be cached.** A handler expressed as a `static fn (...)` is
an anonymous function, so the framework cannot write it to a generated file.
Use a closure during development or when an inline definition is clearer, but
prefer an array callable in production code, so the listener set stays
cacheable.

## Passing Arguments to Events

On a dispatch by class name, the dispatcher can populate the event with the
call-site data before it invokes the listeners. Implement
`Valkyrja\Event\Contract\ArgumentsCapableEventContract`, which declares one
method:

```php
public function setArguments(array $arguments): static;
```

When `dispatchById()` or `dispatchByIdIfHasListeners()` resolves an event that
implements this contract, the dispatcher calls `setArguments()` with the
arguments from the call site. Store the arguments as typed properties, and
expose each one through a getter for the listeners to consume:

```php
use Valkyrja\Event\Contract\ArgumentsCapableEventContract;

class UserRegistered implements ArgumentsCapableEventContract
{
    private User $user;

    /** @param array<array-key, mixed> $arguments */
    public function setArguments(array $arguments): static
    {
        $this->user = $arguments[0];

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
```

With the contract in place, the event's binding stays a bare constructor call,
because `setArguments()` carries the data:

```php
use Valkyrja\Container\Manager\Contract\ContainerContract;

$container->bind(
    UserRegistered::class,
    static fn (ContainerContract $container, array $arguments): UserRegistered => new UserRegistered(),
);

$event = $dispatcher->dispatchById(UserRegistered::class, [$user]);

$event->getUser(); // $user
```

The dispatcher also gives the arguments to the container, so an event that
does not implement the contract can still read them through its binding. See
[Resolving Events From the Container](#resolving-events-from-the-container).

## Collecting Listener Return Values

By default, the dispatcher discards each handler's return value. A pipeline
where each listener contributes one part of a result needs those values. To
collect the return values, implement
`Valkyrja\Event\Contract\DispatchCollectableEventContract`:

```php
public function addDispatch(mixed $dispatch): void;
public function getDispatches(): array;
```

After each handler runs, the dispatcher passes the handler's return value to
`addDispatch()`. `getDispatches()` returns every collected value in invocation
order. The dispatcher collects every handler's return value, including the
`null` from a handler that returns nothing.

A pipeline example: a health check dispatches one event, and each subsystem
registers a listener that returns its status.

```php
use Valkyrja\Event\Contract\DispatchCollectableEventContract;

class HealthChecking implements DispatchCollectableEventContract
{
    /** @var array<int, mixed> */
    private array $dispatches = [];

    public function addDispatch(mixed $dispatch): void
    {
        $this->dispatches[] = $dispatch;
    }

    public function getDispatches(): array
    {
        return $this->dispatches;
    }
}
```

Each subsystem's handler returns a status value:

```php
use Valkyrja\Container\Manager\Contract\ContainerContract;

class DatabaseHealthCheck
{
    /** @param array<string, mixed> $arguments */
    public static function handle(ContainerContract $container, array $arguments): string
    {
        return 'database: ok';
    }
}
```

After the dispatch, read the collected pipeline result from the event:

```php
$event = $dispatcher->dispatch(new HealthChecking());

$statuses = $event->getDispatches(); // ['database: ok', 'cache: ok', ...]
```

## Stoppable Events

An event may implement `Psr\EventDispatcher\StoppableEventInterface`. After
each handler runs, the dispatcher checks `isPropagationStopped()`. When the
check returns `true`, the dispatcher returns the event and does not invoke the
remaining listeners.

The interface declares only `isPropagationStopped()`, so the event decides how
propagation stops. A common shape is a flag with a method that a handler
calls:

```php
use Psr\EventDispatcher\StoppableEventInterface;

class OrderShipping implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function __construct(
        public readonly Order $order,
    ) {
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
```

A handler stops the remaining listeners by calling `stopPropagation()`:

```php
use Valkyrja\Container\Manager\Contract\ContainerContract;

class FraudCheck
{
    /** @param array<string, mixed> $arguments */
    public static function handle(ContainerContract $container, array $arguments): void
    {
        /** @var OrderShipping $event */
        $event = $arguments['event'];

        if ($event->order->isFlagged()) {
            $event->stopPropagation();
        }
    }
}
```

Register the `FraudCheck` listener first, and no later listener runs for a
flagged order.

## Registering Listeners

Listeners register through a listener provider. A listener provider is a class
that implements `Valkyrja\Event\Provider\Contract\ListenerProviderContract`:

```php
public function getListenerClasses(): array;
public function getListeners(): array;
```

`getListenerClasses()` returns class names that the framework scans for
`#[Listener]` attributes. `getListeners()` returns constructed
`ListenerContract` instances. The framework collects every listener provider
from the component providers and registers each listener into the listener
collection. A handler runs only when its event is dispatched.

The listener name keys the listener in the collection, so a second listener
with the same name replaces the first. Give each listener a unique name.

### Attribute Registration

Return class names from `getListenerClasses()`. The framework scans each class
for `#[Valkyrja\Event\Attribute\Listener]` on the class and on its methods.
The attribute is repeatable, so one class or method can listen to more than
one event.

`#[Listener]` takes the event class name, a unique listener name, and an
optional handler. A companion `#[ListenerHandler]` attribute on the same class
or method can supply the handler instead.

Warning: a `#[Listener]` with no handler registers a listener whose handler
does nothing and returns `null`. A `#[Listener]` has no handler when neither
the attribute nor a companion `#[ListenerHandler]` supplies one. The dispatch
reports no error. Always supply a handler.

PHP permits a closure in an attribute argument only from PHP 8.5. This
package supports PHP 8.4, so an attribute handler is an array callable, and
the named method must be static.

**Class-level attributes** — place `#[Listener]` and `#[ListenerHandler]` on
the class:

```php
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Event\Attribute\Listener;
use Valkyrja\Event\Attribute\ListenerHandler;

#[Listener(UserRegistered::class, 'welcome_email.user_registered')]
#[ListenerHandler([self::class, 'handle'])]
class SendWelcomeEmail
{
    /** @param array<string, mixed> $arguments */
    public static function handle(ContainerContract $container, array $arguments): void
    {
        /** @var UserRegistered $event */
        $event = $arguments['event'];

        // Send the email.
    }
}
```

**Method-level attributes** — place `#[Listener]` and `#[ListenerHandler]` on
the method. A static handler that resolves the class from the container keeps
the instance method as the unit of work:

```php
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Event\Attribute\Listener;
use Valkyrja\Event\Attribute\ListenerHandler;

class NotificationService
{
    #[Listener(UserRegistered::class, 'notification.user_registered')]
    #[ListenerHandler([self::class, 'handle'])]
    public function onUserRegistered(UserRegistered $event): void
    {
        // Send the notification.
    }

    /** @param array<string, mixed> $arguments */
    public static function handle(ContainerContract $container, array $arguments): void
    {
        $container->get(self::class)->onUserRegistered($arguments['event']);
    }
}
```

Warning: the `get(self::class)` call resolves `NotificationService` from the
container, so `NotificationService` needs a binding of its own. The container
has no autowiring. See [Resolving Dependencies](#resolving-dependencies).

**The handler as the third argument** — pass the handler on the `#[Listener]`
attribute in place of a `#[ListenerHandler]`:

```php
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Event\Attribute\Listener;

#[Listener(UserRegistered::class, 'audit.user_registered', handler: [self::class, 'record'])]
class AuditLog
{
    /** @param array<string, mixed> $arguments */
    public static function record(ContainerContract $container, array $arguments): void
    {
        // Write the audit row.
    }
}
```

When a `#[Listener]` carries a handler and a `#[ListenerHandler]` targets the
same class or method, the `#[ListenerHandler]` handler wins. The collector
reads the first `#[ListenerHandler]` on the target, and that one handler
serves every `#[Listener]` on the same target. A class-level `#[Listener]`
pairs with a class-level `#[ListenerHandler]`, and a method-level
`#[Listener]` pairs with a `#[ListenerHandler]` on the same method.

The listener provider names each attributed class:

```php
use Valkyrja\Event\Provider\Contract\ListenerProviderContract;

class AppListenerProvider implements ListenerProviderContract
{
    public function getListenerClasses(): array
    {
        return [
            SendWelcomeEmail::class,
            NotificationService::class,
            AuditLog::class,
        ];
    }

    public function getListeners(): array
    {
        return [];
    }
}
```

### Manual Registration

Return constructed listeners from `getListeners()`. Each one is a
`Valkyrja\Event\Data\Listener`, built from an event class name, a unique
listener name, and a handler. Any `ListenerContract` implementation serves in
place of `Listener`. The handler may be any callable here, including a
closure. A closure makes the listener set uncacheable, as
[Caching Trade-off](#caching-trade-off) describes:

```php
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Event\Data\Listener;
use Valkyrja\Event\Provider\Contract\ListenerProviderContract;

class AppListenerProvider implements ListenerProviderContract
{
    public function getListenerClasses(): array
    {
        return [];
    }

    public function getListeners(): array
    {
        return [
            new Listener(
                eventId: UserRegistered::class,
                name: 'notification.user_registered',
                handler: [NotificationService::class, 'handle'],
            ),
            new Listener(
                eventId: UserRegistered::class,
                name: 'debug.user_registered',
                handler: static fn (ContainerContract $container, array $arguments) => error_log('User registered'),
            ),
        ];
    }
}
```

One provider can use both mechanisms: return the attributed classes from
`getListenerClasses()` and the constructed listeners from `getListeners()`.

## Wiring Through a Component Provider

The application reads listener providers from each component provider's
`getEventProviders()` method. The method belongs to
`Valkyrja\Application\Provider\Contract\ComponentProviderContract`, which the
Application component's README documents in full:

```php
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\Contract\ComponentProviderContract;

class AppComponentProvider implements ComponentProviderContract
{
    public function getComponentProviders(ApplicationContract $app): array
    {
        return [];
    }

    public function getContainerProviders(ApplicationContract $app): array
    {
        return [
            new AppServiceProvider(),
        ];
    }

    public function getEventProviders(ApplicationContract $app): array
    {
        return [
            new AppListenerProvider(),
        ];
    }

    public function getCliProviders(ApplicationContract $app): array
    {
        return [];
    }

    public function getHttpProviders(ApplicationContract $app): array
    {
        return [];
    }

    public function getGrpcProviders(ApplicationContract $app): array
    {
        return [];
    }
}
```

The component provider registers in the application config's `providers`
array, next to a framework bundle provider:

```php
use Valkyrja\Application\Data\Config;
use Valkyrja\Application\Provider\ApplicationComponentProvider;

new Config(
    providers: [
        new ApplicationComponentProvider(),
        new AppComponentProvider(),
    ],
);
```

The chain, end to end: the config lists the component providers. The
application calls `getEventProviders()` on each component provider and
collects the listener providers. The framework scans each class from
`getListenerClasses()` for attributes, adds each listener from
`getListeners()`, and registers everything into the listener collection. The
dispatcher reads the collection when an event is dispatched, and each matched
handler runs.

## A Complete Example

The pieces together: an event that receives arguments and collects results, a
listener registered by attribute, the bindings, the providers, and the
dispatch.

```php
use Valkyrja\Application\Data\Config;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\ApplicationComponentProvider;
use Valkyrja\Application\Provider\Contract\ComponentProviderContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Event\Attribute\Listener;
use Valkyrja\Event\Attribute\ListenerHandler;
use Valkyrja\Event\Contract\ArgumentsCapableEventContract;
use Valkyrja\Event\Contract\DispatchCollectableEventContract;
use Valkyrja\Event\Provider\Contract\ListenerProviderContract;

// 1. The event.
class UserRegistered implements ArgumentsCapableEventContract, DispatchCollectableEventContract
{
    private User $user;

    /** @var array<int, mixed> */
    private array $dispatches = [];

    /** @param array<array-key, mixed> $arguments */
    public function setArguments(array $arguments): static
    {
        $this->user = $arguments[0];

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function addDispatch(mixed $dispatch): void
    {
        $this->dispatches[] = $dispatch;
    }

    public function getDispatches(): array
    {
        return $this->dispatches;
    }
}

// 2. The listener.
class NotificationService
{
    #[Listener(UserRegistered::class, 'notification.user_registered')]
    #[ListenerHandler([self::class, 'handle'])]
    public function onUserRegistered(UserRegistered $event): string
    {
        // Send the notification.

        return 'notification_sent';
    }

    /** @param array<string, mixed> $arguments */
    public static function handle(ContainerContract $container, array $arguments): string
    {
        return $container->get(self::class)->onUserRegistered($arguments['event']);
    }
}

// 3. The service provider — the event binding for dispatchById(), and the
//    listener's own service, because the container has no autowiring.
class AppServiceProvider implements ServiceProviderContract
{
    public static function publishUserRegistered(ContainerContract $container): void
    {
        $container->bind(
            UserRegistered::class,
            static fn (ContainerContract $container, array $arguments): UserRegistered => new UserRegistered(),
        );
    }

    public static function publishNotificationService(ContainerContract $container): void
    {
        $container->setSingleton(NotificationService::class, new NotificationService());
    }

    public function publishers(): array
    {
        return [
            UserRegistered::class      => [self::class, 'publishUserRegistered'],
            NotificationService::class => [self::class, 'publishNotificationService'],
        ];
    }
}

// 4. The listener provider.
class AppListenerProvider implements ListenerProviderContract
{
    public function getListenerClasses(): array
    {
        return [NotificationService::class];
    }

    public function getListeners(): array
    {
        return [];
    }
}

// 5. The component provider.
class AppComponentProvider implements ComponentProviderContract
{
    public function getComponentProviders(ApplicationContract $app): array
    {
        return [];
    }

    public function getContainerProviders(ApplicationContract $app): array
    {
        return [new AppServiceProvider()];
    }

    public function getEventProviders(ApplicationContract $app): array
    {
        return [new AppListenerProvider()];
    }

    public function getCliProviders(ApplicationContract $app): array
    {
        return [];
    }

    public function getHttpProviders(ApplicationContract $app): array
    {
        return [];
    }

    public function getGrpcProviders(ApplicationContract $app): array
    {
        return [];
    }
}

// 6. The config.
new Config(
    providers: [
        new ApplicationComponentProvider(),
        new AppComponentProvider(),
    ],
);

// 7. The dispatch.
$event = $dispatcher->dispatchById(UserRegistered::class, [$user]);

$results = $event->getDispatches(); // ['notification_sent']
```

The dispatch resolves `UserRegistered` through its binding, populates it
through `setArguments()`, invokes the `NotificationService` handler, and
collects the handler's return value on the event.

## The Listener Collection

The listener collection holds every registered listener. The collection
contract is `Valkyrja\Event\Collection\Contract\ListenerCollectionContract`.
The providers fill the collection at boot, and its full API is open at
runtime. Resolve the collection from the container:

```php
use Valkyrja\Event\Collection\Contract\ListenerCollectionContract;

$collection = $container->getSingleton(ListenerCollectionContract::class);
```

The collection also implements PSR-14's
`Psr\EventDispatcher\ListenerProviderInterface` through
`getListenersForEvent()`.

Each `*ForEvent()` method takes an event object and matches by its class.
Each `*ForEventById()` method takes the class name.

### Adding and Removing at Runtime

```php
use Valkyrja\Event\Data\Listener;

// Add one listener.
$collection->addListener(
    new Listener(
        eventId: UserRegistered::class,
        name: 'audit.user_registered',
        handler: [AuditLog::class, 'record'],
    )
);

// Remove one listener, by object or by name.
$collection->removeListener($listener);
$collection->removeListenerById('audit.user_registered');

// Remove every listener for an event.
$collection->removeListenersForEventById(UserRegistered::class);
```

`setListenersForEventById()` re-keys each given listener to the event id, and
adds the listener. The re-key goes through `withEventId()`. Warning: the
method does not remove the listeners already registered for that event. To
replace them, remove first:

```php
$collection->removeListenersForEventById(UserRegistered::class);
$collection->setListenersForEventById(UserRegistered::class, $listenerOne, $listenerTwo);
```

### Inspecting the Collection

```php
$collection->hasListenerById('audit.user_registered');        // bool
$collection->hasListenersForEventById(UserRegistered::class); // bool
$collection->getListenersForEventById(UserRegistered::class); // ListenerContract[]
$collection->getListeners();                                  // every ListenerContract, keyed by name
$collection->getEvents();                                     // every registered event class name
$collection->getEventsWithListeners();                        // array<class-string, ListenerContract[]>
```

### Snapshots

`getData()` snapshots the collection as an `EventData` object, and
`setFromData()` restores one. The data cache uses this pair. See
[Service Registration](#service-registration).

## The Listener Data Object

`Valkyrja\Event\Data\Listener` implements
`Valkyrja\Event\Data\Contract\ListenerContract`. The getters are
`getEventId()`, `getName()`, and `getHandler()`. The object is immutable
through its withers: `withEventId()`, `withName()`, and `withHandler()` each
return a clone with the one change, and the original does not change:

```php
use Valkyrja\Event\Data\Listener;

$listener = new Listener(
    eventId: UserRegistered::class,
    name: 'notification.user_registered',
    handler: [NotificationService::class, 'handle'],
);

$forUpdates = $listener
    ->withEventId(UserUpdated::class)
    ->withName('notification.user_updated');

$listener->getEventId(); // Still UserRegistered::class.
```

The `#[Listener]` attribute extends this data class, so an attribute instance
is itself a `ListenerContract`.

## Scanning Classes for Listeners

The attribute collector turns attributed classes into listeners. The
collector contract is
`Valkyrja\Event\Collector\Contract\ListenerCollectorContract`, and
`AttributeListenerCollector` implements the contract. The framework calls the
collector when the framework builds the collection. Call the collector
yourself to scan classes programmatically:

```php
use Valkyrja\Event\Collector\Contract\ListenerCollectorContract;

$collector = $container->getSingleton(ListenerCollectorContract::class);

foreach ($collector->getListeners(NotificationService::class, AuditLog::class) as $listener) {
    $collection->addListener($listener);
}
```

## Standalone Usage

The dispatcher works without the full framework. Both constructor parameters
carry defaults. The defaults are an empty `ListenerCollection` and a bare
`Container`:

```php
use Valkyrja\Event\Collection\ListenerCollection;
use Valkyrja\Event\Data\Listener;
use Valkyrja\Event\Dispatcher\EventDispatcher;

$collection = new ListenerCollection();

$collection->addListener(
    new Listener(
        eventId: UserRegistered::class,
        name: 'notification.user_registered',
        handler: [NotificationService::class, 'handle'],
    )
);

$dispatcher = new EventDispatcher($collection);
```

Pass the application's container when a handler resolves services or when you
dispatch by class name. The default bare `Container` holds no bindings, so
both operations throw against the default container.

## Exceptions

Every exception the component throws implements
`Valkyrja\Event\Throwable\Contract\EventThrowable`, which extends the
framework-wide `ValkyrjaThrowable`. Two abstract bases group the kinds. The
bases are `EventInvalidArgumentException` and `EventRuntimeException`. One
concrete exception exists:

- `Valkyrja\Event\Throwable\Exception\EventInvalidEventException` — a dispatch
  by class name resolved to a different type.

Warning: the missing-binding failure is a container failure.
`ContainerInvalidReferenceException` implements the Container component's
`ContainerThrowable`, not `EventThrowable`, so a catch of `EventThrowable`
does not take it. Catch `Valkyrja\Throwable\Contract\ValkyrjaThrowable` to
take both.

Catch `EventThrowable` to handle the component's own throwables:

```php
use Valkyrja\Event\Throwable\Contract\EventThrowable;

try {
    $event = $dispatcher->dispatchById(UserRegistered::class, [$user]);
} catch (EventThrowable $throwable) {
    // Handle the failure.
}
```

## Service Registration

The Event service provider registers the following singletons:

| Contract / Class             | Description                                            |
| :--------------------------- | :----------------------------------------------------- |
| `EventDispatcherContract`    | The dispatcher (`EventDispatcher`)                     |
| `ListenerCollectionContract` | The listener collection                                |
| `ListenerCollectorContract`  | The attribute collector (`AttributeListenerCollector`) |
| `EventData`                  | The generated listener data                            |

The component's own `EventComponentProvider` wires the `EventServiceProvider`
into the container providers. Every framework bundle provider declares the
Event component, so an application built from a bundle needs no extra wiring
for these services. `ApplicationComponentProvider` is one bundle provider.

In debug mode, the framework builds the listener collection fresh on every
boot: it collects each listener provider from the application, scans the
classes from `getListenerClasses()` for attributes, and adds the listeners
from `getListeners()`. Outside debug mode, the collection loads from the
`EventData` service instead, so the boot pays no scan. A generated data
provider supplies `EventData` in production; without one, the framework
builds the data fresh through the same provider scan. The Application
component's README documents the data cache in full.

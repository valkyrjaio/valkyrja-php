# The Container

## Introduction

The container registers and resolves every service in a Valkyrja application.
It is **PSR-11 compliant**: any library that accepts
`Psr\Container\ContainerInterface` can use it. Beyond PSR-11, the container
adds an explicit binding model, three service types, and deferred loading.

This document covers each feature with a worked example:

- [Binding Services](#binding-services) — the four binding methods and the
  callable signature.
- [Resolving Services](#resolving-services) — the five resolution methods.
- [Inspecting the Container](#inspecting-the-container) — the state methods.
- [Service Providers](#service-providers) — the registration convention, from
  the publish callback to the application config.
- [A Complete Example](#a-complete-example) — the full chain in one place.
- [Container Data](#container-data) — capturing, seeding, and merging
  bindings.
- [Child Containers](#child-containers) — per-request isolation for the
  persistent worker runtimes.
- [Exceptions](#exceptions) — what the container throws, and when.

## Contracts

Valkyrja calls an interface a **contract**; a class or file name that ends in
`Contract` is an interface. Bind against a contract rather than a concrete
class where you can. The framework itself does.

## Deferred Loading

Services are **deferred by default**. At boot the container instantiates
nothing. It stores a map of service ids and the callbacks that build them.
The container creates a singleton on its first request and caches it. The
container creates a service on every request. Boot cost therefore does not
grow with the number of registered services.

## Service Types

The container has three registration types.

**Singleton** — The container creates one instance on the first resolution and
reuses it on every later call. Use this for shared stateful services: a
database connection, a logger, the event dispatcher.

**Service** — The container creates a new instance on every resolution. Use
this for stateless objects, or where each caller must own a fresh instance.

**Alias** — A service id that maps to another registered service id. A
resolution of the alias resolves the target service.

## Binding Services

In application code, a service provider registers most services;
[Service Providers](#service-providers) shows the pattern. The methods below
are what publish callbacks and boot code call.

### The Callable Signature

`bind()` and `bindSingleton()` accept any `callable` with the signature
`(ContainerContract $container, array $arguments): object`. The container
passes itself as the first argument, so the callable resolves its own
dependencies from the container. The second argument carries the arguments
that the caller passes to `getService()` or `get()`. Any callable with that
signature works: a closure, an invokable object, or an array callable that
points to a static method.

### bind()

`bind(string $id, callable $callable): static` binds a service id to a
callable factory. Every call to `getService($id)` invokes the callable and
returns a fresh instance. Choose `bind()` when each caller must own its
instance. A builder that accumulates state is one example. An object built
from per-call arguments is another:

```php
use Valkyrja\Container\Manager\Contract\ContainerContract;

$container->bind(
    QueryBuilderContract::class,
    static function (ContainerContract $container, array $arguments): QueryBuilder {
        [$table] = $arguments;

        return new QueryBuilder(
            connection: $container->getSingleton(ConnectionContract::class),
            table: $table,
        );
    },
);

$users  = $container->getService(QueryBuilderContract::class, ['users']);
$orders = $container->getService(QueryBuilderContract::class, ['orders']);
// Two calls, two fresh instances.
```

### bindSingleton()

`bindSingleton(string $id, callable $callable): static` is the same as
`bind()`, but singleton-scoped. The container invokes the callable once on
the first resolution and caches the result. Choose it for a shared service
whose construction should wait until first use:

```php
use Valkyrja\Container\Manager\Contract\ContainerContract;

$container->bindSingleton(
    LoggerContract::class,
    static fn (ContainerContract $container): FileLogger => new FileLogger(
        path: '/storage/logs/app.log',
    ),
);

$logger = $container->getSingleton(LoggerContract::class); // The first call builds and caches.
$same   = $container->getSingleton(LoggerContract::class); // Later calls return the cached instance.
```

### bindAlias()

`bindAlias(string $alias, string $id): static` maps one service id to another
id. A resolution of the alias resolves the target. Use it to publish one
implementation under two ids. The two ids are the concrete class and the
contract:

```php
$container->bindSingleton(
    FileLogger::class,
    static fn (ContainerContract $container): FileLogger => new FileLogger(
        path: '/storage/logs/app.log',
    ),
);

$container->bindAlias(LoggerContract::class, FileLogger::class);

// Both ids resolve the same cached instance:
$logger = $container->get(LoggerContract::class);
$same   = $container->get(FileLogger::class);
```

`bindAlias()` stores the mapping only. The target id needs its own binding, and
the container checks that target when the alias resolves, not when you bind the
alias.

The alias itself is checked at once. `bindAlias()` throws
`ContainerCyclicAliasException` when the target already resolves back to the
alias, and when the two are the same id, because such a chain has no end:

```php
$container->bindAlias(NotifierContract::class, SlackNotifier::class);

// Throws: SlackNotifier already resolves to NotifierContract.
$container->bindAlias(SlackNotifier::class, NotifierContract::class);
```

### setSingleton()

`setSingleton(string $id, object $singleton): static` registers an
already-constructed object. Use it when the instance exists before the
container needs it. The config at boot, the request in a worker loop, and an
instance that a publish callback builds inline are three examples:

```php
$config = new AppConfig(environment: 'production');

$container->setSingleton(AppConfig::class, $config);
```

A later `setSingleton()` for the same id replaces the instance.

### The Static Factory Alternative

A static factory method on the service class satisfies
[the callable signature](#the-callable-signature), so an array callable can
point to one:

```php
use Valkyrja\Container\Manager\Contract\ContainerContract;

class UserRepository implements UserRepositoryContract
{
    public static function make(ContainerContract $container, array $arguments = []): static
    {
        return new static(
            $container->getSingleton(DatabaseContract::class)
        );
    }
}

$container->bind(UserRepositoryContract::class, [UserRepository::class, 'make']);
```

This is a valid alternative, not the default. It moves registration into the
service class, so the class now carries construction knowledge that a
provider would otherwise own. Prefer a service provider unless the class owns
a construction step that callers must reuse.

Neither approach uses reflection-based autowiring. Every dependency is
declared in code.

### Every Service Needs a Binding

The container resolves a service id through a cached instance, a bound
factory, or an alias. It builds nothing that a binding does not describe.

Warning: `get()` throws `ContainerInvalidReferenceException` for a service id
that none of the three resolves. The container does not construct the class
that the id names.

```php
// Wrong — nothing binds the middleware, so the container throws.
$handler->add(AuthMiddleware::class);
```

```php
// Right — the binding describes the middleware, and the handler resolves it.
$container->bindSingleton(
    AuthMiddleware::class,
    static fn (ContainerContract $container): AuthMiddleware => new AuthMiddleware(
        $container->getSingleton(AuthContract::class)
    ),
);

$handler->add(AuthMiddleware::class);
```

This rule holds for every class that a config names by class string. The rule
covers a middleware, an event, and a view replacement. One explicit place
states how each service is built.

## Resolving Services

### get()

`get(string $id, array $arguments = []): object` is the PSR-11 resolution
method. It checks singletons first, then services, then aliases, so the
caller does not need to know the registration type. `$arguments` go to the
service callable when the id resolves to a service, and forward through an
alias to its target. A singleton resolution ignores `$arguments`. Use
`get()` where the registration type is unknown. A class string read from a
config is one example:

```php
$middleware = $container->get($middlewareClass);
```

### getSingleton()

`getSingleton(string $id): object` resolves a singleton. The first access
invokes the binding or the publish callback and caches the result. Later
calls return the cached instance. Use it for every shared service:

```php
$logger = $container->getSingleton(LoggerContract::class);
```

### getService()

`getService(string $id, array $arguments = []): object` resolves a service.
Every call invokes the registered callable with `$arguments` and returns a
fresh instance:

```php
$query = $container->getService(QueryBuilderContract::class, ['users']);
```

Warning: `bindSingleton()` also stores its factory in the service map, so
`getService()` accepts a singleton-bound id. `getService()` then returns a
fresh instance that never reaches the singleton cache. Resolve a singleton
with `getSingleton()` or `get()`.

### getAliased()

`getAliased(string $id, array $arguments = []): object` resolves the service
that the alias points to. Use it when the caller knows the id is an alias and
wants to skip the singleton and service lookups:

```php
$logger = $container->getAliased(LoggerContract::class);
```

`getAliased()` publishes and resolves the target as `get()` does. It does
not run a deferred publish callback registered under the alias id itself.
Resolve an alias that a callback creates through `get()`, which runs the
callback first.

### getAliasedId()

`getAliasedId(string $alias): string|null` returns the id that the alias
points to, and returns `null` when the id is not an alias. The method reads
one hop. It does not follow a chain of aliases, so a caller that must reach
the end of a chain calls the method again.

### Prefer the Specific Method

The type-specific methods throw `ContainerInvalidReferenceException` when the
id is not registered as that type. When you know the registration type,
prefer the specific method over `get()`. The saved lookup is small per call
but adds up in a hot path such as route dispatch.

## Inspecting the Container

Before a resolution, you can inspect what is registered:

- `has(string $id): bool` — PSR-11; true when the id is registered in any
  form, a deferred publish callback included.
- `isSingleton(string $id): bool` — a singleton binding or a resolved
  instance exists.
- `isSingletonBinding(string $id): bool` — a singleton binding exists. The
  binding does not clear on resolution, so the method can return `true` for a
  resolved singleton.
- `isSingletonInstance(string $id): bool` — the singleton is resolved and
  cached.
- `isService(string $id): bool` — a service binding exists. A
  `bindSingleton()` id also stores its factory in the service map, so the
  method returns `true` for a singleton binding too.
- `isAlias(string $id): bool` — the id is an alias.
- `isDeferred(string $id): bool` — a publish callback is registered for the id.
  The method reports the registration, not whether the callback ran; pair it
  with `isPublished()` to find a service that is registered and still unrun.
- `isPublished(string $id): bool` — the id's publish callback ran, or a
  boot-time `bind()`, `bindSingleton()`, or `setSingleton()` marked the id.
  `bindAlias()` does not mark the id.

`isSingleton($id)` equals `isSingletonBinding($id) || isSingletonInstance($id)`.

No state method runs a deferred publish callback. Each method reports the
current maps only, so `isAlias()` and `getAliasedId()` answer `false` and
`null` for an alias that an unrun callback would create.

### Conditional Registration

Boot code uses `has()` to register a fallback only when no other binding
exists:

```php
if (! $container->has(MetricsContract::class)) {
    $container->setSingleton(MetricsContract::class, new NullMetrics());
}
```

### Resolving Only a Built Instance

A resolution triggers construction, so a caller that must not pay that cost
checks `isSingletonInstance()` first. A shutdown hook is one example. The
hook logs only when the logger is already built:

```php
if ($container->isSingletonInstance(LoggerContract::class)) {
    $container->getSingleton(LoggerContract::class)->info('Shutting down.');
}
```

The binding-versus-instance distinction is also what
[child containers](#child-containers) use to decide when a parent instance is
safe to reuse.

### Classifying an Alias

Warning: the state methods answer for the id that you give them, never for
the alias target. To classify an alias, read the target with
`getAliasedId()` first, then classify the target:

```php
// Wrong — the state method answers for the alias, which holds no instance.
// This reports false while getAliased() returns a resolved singleton.
if ($container->isSingletonInstance($alias)) {
}
```

```php
// Right — read the target first, then classify the target.
$aliasedId = $container->getAliasedId($alias);

if ($aliasedId !== null && $container->isSingletonInstance($aliasedId)) {
    // The container holds a resolved instance for the target.
}
```

```php
// Right — classify the id itself, and exclude an alias.
if ($container->isSingleton($id) && ! $container->isAlias($id)) {
    // The id is a singleton in its own right.
}
```

## Service Providers

A **service provider** registers a service. This is the convention the
framework follows everywhere. The provider owns the registration logic; the
service class implements its contract and carries no registration code.

A service provider implements
`Valkyrja\Container\Provider\Contract\ServiceProviderContract`, which has one
instance method, `publishers()`.

### The publishers() Map

`publishers()` returns a map from service ids to static publish callbacks.
One provider can publish many services. Each id takes one map entry and one
callback:

```php
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;

class NotifierServiceProvider implements ServiceProviderContract
{
    public function publishers(): array
    {
        return [
            NotifierContract::class     => [self::class, 'publishNotifier'],
            NotifierLogContract::class  => [self::class, 'publishNotifierLog'],
        ];
    }

    public static function publishNotifier(ContainerContract $container): void
    {
        $container->setSingleton(
            NotifierContract::class,
            new SlackNotifier()
        );
    }

    public static function publishNotifierLog(ContainerContract $container): void
    {
        $container->setSingleton(
            NotifierLogContract::class,
            new NotifierLog(
                $container->getSingleton(LoggerContract::class)
            )
        );
    }
}
```

To swap an implementation, change the publish callback. The contract and the
callers do not change.

A publish callback can resolve other services from the container, as
`publishNotifierLog()` does. Those services are themselves deferred; the
resolution triggers their own publish callbacks when needed.

### Deferred Publication

The container stores the `publishers()` map and defers each callback. The
first resolution of an id runs the callback for that id and marks the id
published. The resolution can come through `get()`, `getSingleton()`, or
`getService()`. A callback runs at most once.

A publish callback usually builds the instance inline with `setSingleton()`.
A callback can instead call `bind()` when the service must stay fresh per
resolution:

```php
public static function publishQueryBuilder(ContainerContract $container): void
{
    $container->bind(QueryBuilderContract::class, [self::class, 'createQueryBuilder']);
}

public static function createQueryBuilder(ContainerContract $container, array $arguments): QueryBuilder
{
    [$table] = $arguments;

    return new QueryBuilder(
        connection: $container->getSingleton(ConnectionContract::class),
        table: $table,
    );
}
```

### Wiring Providers into the Application

A component provider hands the service providers to the application through
`getContainerProviders()`:

```php
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\Contract\ComponentProviderContract;

class AppComponentProvider implements ComponentProviderContract
{
    public function getContainerProviders(ApplicationContract $app): array
    {
        return [new NotifierServiceProvider()];
    }

    // The other ComponentProviderContract methods are omitted here.
}
```

The app config's `providers` parameter lists the component providers. The
default value holds the framework's own component provider, so a config that
sets the parameter lists the framework provider again, then the
application's:

```php
use Valkyrja\Application\Data\HttpConfig;
use Valkyrja\Application\Provider\HttpApplicationComponentProvider;

$config = new HttpConfig(
    dir: __DIR__,
    providers: [
        new HttpApplicationComponentProvider(),
        new AppComponentProvider(),
    ],
);
```

At boot the application walks each component provider's
`getComponentProviders()` depth-first, so a component provider can pull in
the component providers it depends on. The framework then registers every
collected service provider in the container. Registration stores the
`publishers()` maps only; no callback runs until its id is first resolved.

### Manual Registration and Publication

The container exposes the provider machinery directly.

**`register(ServiceProviderContract $provider): void`** — Stores the
provider's `publishers()` map. The wiring above calls this for you; call it
yourself to register a provider outside the config chain. A test is one
example. It throws `ContainerInvalidPublishCallbackException` when a map
entry is not callable.

The last registered callback for an id wins. A provider registered later
replaces an earlier provider's callback for the same unpublished id, so an
application provider can replace a framework service by publishing under the
framework's id:

```php
class AppOrmServiceProvider implements ServiceProviderContract
{
    public function publishers(): array
    {
        // The framework provider publishes a default registry under this id.
        // This provider registers later, so this callback replaces it.
        return [
            EntityRegistryContract::class => [self::class, 'publishEntityRegistry'],
        ];
    }

    public static function publishEntityRegistry(ContainerContract $container): void
    {
        $container->setSingleton(
            EntityRegistryContract::class,
            new EntityRegistry(entities: [User::class]),
        );
    }
}
```

**`publish(string $id): void`** — Runs the id's publish callback now and
marks the id published. When no callback exists, the method does nothing.
Use it to publish a service eagerly instead of on first resolution. A
publish before a worker's request loop is one example.

**`isPublished(string $id): bool`** — Reports whether the id is published
([Inspecting the Container](#inspecting-the-container)).

### Binding Without a Provider

A component provider can also bind a service directly, paired with a static
factory ([The Static Factory Alternative](#the-static-factory-alternative)):

```php
public function getContainerProviders(ApplicationContract $app): array
{
    $app->getContainer()->bindSingleton(
        NotifierContract::class,
        [SlackNotifier::class, 'make']
    );

    return [];
}
```

The two approaches combine in one application, but not for one id: a
boot-time `bind()`, `bindSingleton()`, or `setSingleton()` marks the id as
published, so a publish callback registered for the same id never runs.
Register each id through one approach only.

## A Complete Example

The chain from a contract to a resolved service has five parts: the contract,
an implementation, a service provider, a component provider, and the app
config.

```php
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\Contract\ComponentProviderContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;

// 1. The contract.
interface NotifierContract
{
    public function notify(string $message): void;
}

// 2. One implementation.
class SlackNotifier implements NotifierContract
{
    public function __construct(
        protected string $webhookUrl,
    ) {
    }

    public function notify(string $message): void
    {
        // Send the message to the Slack webhook.
    }
}

// 3. Another implementation.
class LogNotifier implements NotifierContract
{
    public function __construct(
        protected LoggerContract $logger,
    ) {
    }

    public function notify(string $message): void
    {
        $this->logger->info($message);
    }
}

// 4. The service provider owns the registration.
class NotifierServiceProvider implements ServiceProviderContract
{
    public function publishers(): array
    {
        return [
            NotifierContract::class => [self::class, 'publishNotifier'],
        ];
    }

    public static function publishNotifier(ContainerContract $container): void
    {
        $container->setSingleton(
            NotifierContract::class,
            new SlackNotifier(
                webhookUrl: 'https://hooks.example.com/notify',
            )
        );
    }
}

// 5. The component provider hands the service provider to the application.
class AppComponentProvider implements ComponentProviderContract
{
    public function getComponentProviders(ApplicationContract $app): array
    {
        return [];
    }

    public function getContainerProviders(ApplicationContract $app): array
    {
        return [new NotifierServiceProvider()];
    }

    public function getEventProviders(ApplicationContract $app): array
    {
        return [];
    }

    public function getCliProviders(ApplicationContract $app): array
    {
        return [];
    }

    public function getHttpProviders(ApplicationContract $app): array
    {
        return [];
    }
}
```

The app config lists the component provider, as
[Wiring Providers into the Application](#wiring-providers-into-the-application)
shows. After boot, any caller resolves the notifier through its contract:

```php
$notifier = $app->getContainer()->getSingleton(NotifierContract::class);

$notifier->notify('The deploy is complete.');
```

The first `getSingleton()` call runs `publishNotifier()` and caches the
instance. Nothing constructs `SlackNotifier` before that call. To swap to
`LogNotifier`, change `publishNotifier()` to build a `LogNotifier` and
resolve `LoggerContract` from the container. The contract, the callers, and
both notifier classes stay as they are.

## Container Data

`ContainerData` is a readonly value object with four maps: `aliases`,
`callbacks`, `services`, and `singletons`. Three operations move it in and
out of a container:

```php
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Container\Manager\Container;

// Capture a container's bindings:
$data = $container->getData();

// Seed a new container; new Container() starts empty:
$seeded = new Container($data);

// Merge into an existing container:
$other->setFromData($data);
```

Warning: the data holds bindings, not instances. The `singletons` map holds
singleton markers, so a resolved instance is not captured, and an id
registered only with `setSingleton()` has no entry at all.

`setFromData()` merges: an incoming entry replaces an existing entry with
the same id, and an entry the data does not carry stays as it is.
`ChildContainer` takes a `ContainerData` on construction;
`NativeChildContainer` reads the parent directly and takes none (see
[Child Containers](#child-containers)).

## Child Containers

A child container is a per-request container. It inherits the parent's state
at no cost and writes only to its own local maps. This is the isolation
mechanism that Valkyrja's persistent worker entry points (FrankenPHP,
OpenSwoole, RoadRunner) use to keep request-scoped state out of the parent.

### The Parent/Child Invariant

The parent container bootstraps once when the worker process starts. The parent
is then **frozen**: its registrations do not change again. Each incoming request
receives a fresh child container built from one snapshot of the parent, so the
child holds the parent's singleton markers and publish callbacks and answers
almost everything itself.

The child checks its own maps first, so it answers a deferred id and an unbuilt
singleton itself, in its own scope. An id the child cannot answer at all goes to
the parent, and the parent answers it as it would for any caller. That is a
shared service resolving once, not a leak. What the child never does is rebuild something the
parent already holds, and what it never leaks is its own state: a registration
made during a request stays in the child, and the child is discarded when the
request ends.

Deferred services stay available in a child. The child receives the parent's
publish callbacks through `ContainerData`, so the first lookup of an
unpublished service runs its callback with the child as the container. The
service publishes into the child's own scope; the parent's maps do not change.

`WorkerHttp::bootstrapParentServices()` is about cost for an id a child can
answer itself. A child answers an id when the child holds the publish callback,
or the singleton binding from the data. An id that the method force-resolves
before the request loop is cached in the frozen parent once, and every child
reuses that instance. An id left unresolved is built again in each child that
requests it.

### The Child's Copy of the Data

Before the request loop begins, capture the parent's state once with
`getData()` ([Container Data](#container-data)). Because PHP arrays are
copy-on-write, each child gets its own logical copy at no cost until it
writes to one.

`ChildContainer` copies `callbacks` and `singletons` from the data. Alias and
service lookups need no copy; they fall back through the parent's methods.

A parent instance registered with `setSingleton()` has no entry in the data,
and the child still reaches it: the singleton fallback reads the parent's
cached instances directly.

### Resolution Order

Each lookup checks the child's own maps first, then falls back to the parent.
For a singleton the child applies a three-step strategy:

1. **The child has a cached instance.** The child returns that instance.
2. **The parent has a cached instance.** The child reuses that instance. The
   parent is frozen, so the instance does not change.
3. **The child has a singleton binding.** The child builds the instance and
   caches it in the child's own instance map. The parent's maps do not
   change. [Where a Factory Runs](#where-a-factory-runs) states which
   container the factory receives.

`isPublished` follows the same child-first, parent-fallback pattern. When the
parent has already published a service, the child does not publish it again.

### Where a Factory Runs

The container that a callable receives depends on the callable's kind.

**A publish callback runs in the container that publishes it.** A deferred
service that a child resolves first runs its callback with the child as the
`$container` argument. The callback resolves its dependencies child-first, so
it sees request-scoped services registered on the child.

**Under `ChildContainer`, a bound factory runs in the container that owns
the binding.** `ChildContainer` copies the singleton markers and the publish
callbacks from `ContainerData`, but not the service factories. A factory
bound on the parent stays in the parent, and the child delegates the build
to the parent, so the factory receives the parent as its `$container`
argument. A factory bound on the child itself runs with the child. Either
way, a built singleton caches in the child's own instance map, and a
`bind()` factory caches nowhere.

Warning: under `ChildContainer`, a factory bound on the parent resolves its
dependencies from the parent. The factory cannot see a service that exists
only on the child. A request-scoped `setSingleton()` registers such a
service. When a service needs a request-scoped dependency, register it
through a provider's publish callback, which runs with the child.
`NativeChildContainer` behaves differently on this path
([Available Implementations](#available-implementations)).

### Available Implementations

The component provides two implementations.

**`Valkyrja\Container\Manager\ChildContainer`** — The default. It reads the
parent through `ContainerContract` methods, so any contract implementation can
be the parent. This is the portable, cross-language implementation. It
delegates a parent-bound factory to the parent, so the factory receives the
parent ([Where a Factory Runs](#where-a-factory-runs)).

**`Valkyrja\Container\Manager\NativeChildContainer`** — PHP-specific. It reads
the parent's protected maps directly, the publish callbacks included. Its
constructor takes only a concrete `Container` parent and no `ContainerData`.

Warning: the two implementations differ in behavior on the factory path, not
only in speed. `NativeChildContainer` reads a parent-bound factory out of the
parent's map and invokes the factory itself, so the factory receives the
child and resolves its dependencies child-first. `ChildContainer` hands the
same call to the parent, so the same factory receives the parent. A
parent-declared alias is the exception, and
[Where an Alias Resolves](#where-an-alias-resolves) states it. Choose the
implementation whose factory receiver your services need; the direct map
access also removes the method-call overhead on the fallback path.

### Where an Alias Resolves

An alias resolves in the container that declares it, so **where you declare an
alias selects the resolution scope.** A child lookup of an alias that only the
parent declares resolves in the parent. That is the way to reach the parent's
copy of a **service** that the child also binds:

```php
// Once, at bootstrap. The child never declares this alias.
$parent->bind(SlackNotifier::class, [SlackNotifier::class, 'make']);
$parent->bindAlias(NotifierContract::class, SlackNotifier::class);

// Per request, the child binds its own.
$child->bind(SlackNotifier::class, [SlackNotifier::class, 'make']);

$child->get(SlackNotifier::class);     // built by the child's binding
$child->get(NotifierContract::class);  // built by the parent's binding
```

The example binds a service. The three-step strategy above takes precedence over
an alias. When the parent holds a resolved instance and the child holds none, a
direct child lookup reuses the parent's instance.

The parent answers the target as it would for any caller, with one exception.
When the parent would resolve the target for the first time — a singleton it
registered and never built, or a publisher it has not run — the child resolves
it instead. The child holds the same registration, so letting the parent do it
would leave the request with one copy for the alias and another for the id.
Anything the parent has already built or published is reused as it stands.

Warning: that exception also decides which binding the alias reaches. Give the
parent a singleton the parent never builds, and a child that shadows the target
gets its **own** binding through the alias, because the child resolves the
target itself.

Warning: a **parent-declared** alias onto a target the parent has already
resolved hands the call to the parent in both implementations, so a parent-bound
factory receives the parent. This is the one path where `NativeChildContainer`
gives the parent for a lookup it could have answered itself. On the exception
path above the child resolves the target, so the factory receives the child in
both implementations.

Off that path the receiver follows the implementation, not the alias.
`NativeChildContainer` invokes a parent-bound factory itself and gives it the
child. `ChildContainer` hands the same call to the parent and gives it the
parent.

The two answer `isDeferred()` about **themselves** differently, because they
hold different state. `ChildContainer` copies the callbacks, so it answers for
its own map. `NativeChildContainer` copies nothing, so it answers for the child
and the parent.

### Using a Child Container

```php
use Valkyrja\Container\Manager\ChildContainer;
use Valkyrja\Http\Message\Request\Contract\ServerRequestContract;
use Valkyrja\Http\Server\Handler\Contract\RequestHandlerContract;

// Once, before the request loop:
$parent = $app->getContainer();
$data   = $parent->getData();

// Per request, inside the loop:
$child = new ChildContainer($parent, $data);

// Register request-scoped services on the child only:
$child->setSingleton(ServerRequestContract::class, $request);

// Resolve as normal; the child falls back to the parent:
$handler = $child->getSingleton(RequestHandlerContract::class);
```

In practice you do not construct child containers directly. The worker entry
classes (`WorkerHttp` and its subclasses) do this for every request. See the
[Application README](../Application/README.md#persistent-worker-lifecycle) for
the full lifecycle.

## Exceptions

The container throws three exceptions, all under
`Valkyrja\Container\Throwable\Exception`.

**`ContainerInvalidReferenceException`** — A resolution method received an id
that it cannot resolve: `get()` found no cached instance, bound factory, or
alias for the id, or a type-specific method found no registration of its
type. It extends the SPL `InvalidArgumentException`.

**`ContainerInvalidPublishCallbackException`** — `register()` received a
`publishers()` map entry that is not callable. It extends the SPL
`RuntimeException`.

**`ContainerCyclicAliasException`** — an alias points at a chain that returns
to it, so the chain has no end. Every entry point checks: `bindAlias()` for the
pair it is asked to store, and the constructor and `setFromData()` for the map
they receive. The check runs at registration, not at resolution. It extends the
SPL `InvalidArgumentException`.

All three implement `Valkyrja\Container\Throwable\Contract\ContainerThrowable`,
so one catch covers everything the container throws:

```php
use Valkyrja\Container\Throwable\Contract\ContainerThrowable;

try {
    $service = $container->get($id);
} catch (ContainerThrowable $throwable) {
    // Handle any container failure.
}
```

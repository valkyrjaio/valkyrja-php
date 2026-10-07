# Valkyrja — Application Structure

This document describes the files required to run a Valkyrja application,
how they relate to one another, and how to extend the structure with new routes
and services. Files from the skeleton that are not listed here (models, ORM
entities, test helpers, asset files, etc.) are optional additions.

---

## Prerequisites

- PHP >= 8.4
- Composer

**`composer.json`** — defines dependencies and the PSR-4 autoload map.

```json
{
   "require"  : {
      "php"               : ">=8.4",
      "monolog/monolog"   : "^3.10.0",
      "valkyrja/valkyrja" : "^26.0.0"
   },
   "autoload" : {
      "psr-4" : {
         "App\\" : "app/src/App/"
      }
   },
   "scripts"  : {
   }
}
```

Run `composer install` to install dependencies and generate
`vendor/autoload.php`.

> `filp/whoops` is an optional dev dependency that powers the
> `ThrowableHandler`. Remove it and simplify `ThrowableHandler` if you do not
> need rich error pages.

---

## File Tree

```
your-project/
├── composer.json
└── app/
    ├── public/
    │   └── index.php                               # HTTP entry point
    ├── bin/
    │   └── cli                                     # CLI entry point
    └── src/App/
        ├── Throwable/Handler/
        │   └── ThrowableHandler.php                # Error/exception handler
        ├── Http/
        │   ├── App.php                             # HTTP application bootstrap
        │   ├── Config.php                          # HTTP configuration (gitignored)
        │   ├── Config.example.php                  # Config template (committed)
        │   ├── Controller/
        │   │   └── Controller.php                  # Base HTTP controller
        │   ├── Data/
        │   │   ├── AppContainerData.php            # Pre-generated DI config (gitignored)
        │   │   ├── AppContainerData.example.php    # Empty baseline (committed)
        │   │   ├── AppEventData.php                # Pre-generated event config (gitignored)
        │   │   ├── AppEventData.example.php        # Empty baseline (committed)
        │   │   ├── AppHttpRoutingData.php          # Pre-generated route config (gitignored)
        │   │   └── AppHttpRoutingData.example.php  # Empty baseline (committed)
        │   └── Provider/
        │       ├── ComponentProvider.php           # Wires providers together
        │       ├── DataProvider.php                # Registers pre-generated data
        │       ├── ServiceProvider.php             # Registers app services
        │       └── RouteProvider.php               # Declares controller classes
        └── Cli/
            ├── App.php                             # CLI application bootstrap
            ├── Config.php                          # CLI configuration (gitignored)
            ├── Config.example.php                  # Config template (committed)
            ├── Controller/
            │   └── Controller.php                  # Base CLI controller
            ├── Data/
            │   ├── AppContainerData.php            # Pre-generated DI config (gitignored)
            │   ├── AppContainerData.example.php    # Empty baseline (committed)
            │   ├── AppEventData.php                # Pre-generated event config (gitignored)
            │   ├── AppEventData.example.php        # Empty baseline (committed)
            │   ├── AppCliRoutingData.php           # Pre-generated CLI route config (gitignored)
            │   ├── AppCliRoutingData.example.php   # Empty baseline (committed)
            │   ├── AppHttpRoutingData.php          # Pre-generated HTTP route config (gitignored)
            │   └── AppHttpRoutingData.example.php  # Empty baseline (committed)
            └── Provider/
                ├── ComponentProvider.php
                ├── DataProvider.php
                ├── ServiceProvider.php
                └── RouteProvider.php
```

---

## Entry Points

### HTTP — `app/public/index.php`

The web server document root must point to `app/public/`. Every request is
forwarded to this file.

```php
<?php
declare(strict_types=1);

use App\Http\App;
use App\Http\Config;

define('INDEX_START', microtime(true));

require_once __DIR__ . '/../../vendor/autoload.php';

App::run(config: new Config());
```

### CLI — `app/bin/cli`

The CLI entry point. Make it executable (`chmod +x app/bin/cli`).

```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

use App\Cli\App;
use App\Cli\Config;

require_once __DIR__ . '/../../vendor/autoload.php';

App::run(config: new Config());
```

---

## Error Handling

### `app/src/App/Throwable/Handler/ThrowableHandler.php`

Both `Http\App` and `Cli\App` reference this class. It extends the framework's
`WhoopsThrowableHandler` — a `filp/whoops`-backed implementation — and is the
single place to customize error display and reporting level.

```php
<?php
declare(strict_types=1);

namespace App\Throwable\Handler;

use Valkyrja\Throwable\Handler\WhoopsThrowableHandler as ValkyrjaExceptionHandler;
use const E_ALL;

class ThrowableHandler extends ValkyrjaExceptionHandler
{
    public static function enable(int $errorReportingLevel = E_ALL, bool $displayErrors = false): void
    {
        parent::enable($errorReportingLevel, $displayErrors);
    }
}
```

---

## HTTP Application

### `app/src/App/Http/App.php`

Extends the framework's `Http` entry class. Wires in the app-level
`ThrowableHandler`.

```php
<?php
declare(strict_types=1);

namespace App\Http;

use App\Throwable\Handler\ThrowableHandler;
use Override;
use Valkyrja\Application\Entry\Http;
use Valkyrja\Throwable\Handler\Contract\ThrowableHandlerContract;

final class App extends Http
{
    #[Override]
    protected static function defaultExceptionHandler(): void
    {
        ThrowableHandler::enable(displayErrors: true);
    }

    #[Override]
    protected static function getThrowableHandler(): ThrowableHandlerContract
    {
        return new ThrowableHandler();
    }
}
```

### `app/src/App/Http/Config.example.php` (committed) /

`Config.php` (gitignored)

`Config.example.php` is the template committed to version control.
`Config.php` is generated from it on first `composer install` via the
`post-root-package-install` script, and is gitignored so environment-specific
values are not committed.

`Config.php` must extend `HttpConfig` and implement `ConfigContract`.

Key constructor parameters:

| Parameter                    | Purpose                                                                                                                                       |
| ---------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `$namespace`                 | Application namespace (default `'App'`)                                                                                                       |
| `$dir`                       | Application root directory                                                                                                                    |
| `$version`                   | Application version string                                                                                                                    |
| `$environment`               | `'production'` or `'development'`                                                                                                             |
| `$debugMode`                 | Enables debug mode — attributes are scanned live, data files ignored                                                                          |
| `$key`                       | Application secret key used for signing/encryption; generate with a cryptographically secure random string (e.g. `bin2hex(random_bytes(32))`) |
| `$dataPath`                  | Relative path to the `Data/` directory                                                                                                        |
| `$dataNamespace`             | PHP namespace of the `Data/` classes (e.g. `App\Http\Data`)                                                                                   |
| `$applicationName`           | The application's name; only a CLI application reads it, as the binary name in help and version output                                        |
| `$providers`                 | Ordered list of `ComponentClass` constants + `ComponentProvider::class`                                                                       |
| `$callbacks`                 | Callbacks run after providers are booted (e.g. `ComponentProvider::publish`)                                                                  |
| `$requestReceivedMiddleware` | Middleware run on every incoming request                                                                                                      |

The `$providers` array uses `ComponentClass` constants to declare which
framework components to load, and must end with your app's own
`ComponentProvider::class`:

```php
use Valkyrja\Application\Constant\ComponentClass;

array $providers = [
    ComponentClass::CONTAINER,
    ComponentClass::EVENT,
    ComponentClass::HTTP_MESSAGE,
    ComponentClass::HTTP_MIDDLEWARE,
    ComponentClass::HTTP_ROUTING,
    ComponentClass::HTTP_SERVER,
    ComponentClass::LOG,
    ComponentClass::VIEW,
    ComponentProvider::class,  // your app's own component provider — always last
],
```

### `app/src/App/Http/Controller/Controller.php`

Abstract base controller all HTTP controllers must extend. Extends the
framework's routing controller. Add shared methods here.

```php
<?php
declare(strict_types=1);

namespace App\Http\Controller;

use Valkyrja\Http\Routing\Controller\Controller as ValkyrjaController;

abstract class Controller extends ValkyrjaController
{
}
```

### `app/src/App/Http/Provider/ComponentProvider.php`

The single wiring point that tells the framework which application-level
providers to register for each concern: container services, events, HTTP routes,
and CLI routes. Also declares the `publish()` callback that selects between
debug and production boot modes.

```php
<?php
declare(strict_types=1);

namespace App\Http\Provider;

use Override;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\Contract\ComponentProviderContract;
use Valkyrja\Container\Provider\ServiceProvider as ContainerServiceProvider;

final class ComponentProvider implements ComponentProviderContract
{
    #[Override]
    public static function getContainerProviders(ApplicationContract $app): array
    {
        return [
            DataProvider::class,
            ServiceProvider::class,
        ];
    }

    #[Override]
    public static function getEventProviders(ApplicationContract $app): array
    {
        return [];
    }

    #[Override]
    public static function getCliProviders(ApplicationContract $app): array
    {
        return [];
    }

    #[Override]
    public static function getHttpProviders(ApplicationContract $app): array
    {
        return [
            RouteProvider::class,
        ];
    }

    public static function publish(ApplicationContract $app): void
    {
        $container = $app->getContainer();

        if ($app->getDebugMode()) {
            // Debug mode: scan attributes at runtime (no pre-generated data needed)
            ContainerServiceProvider::publishData(container: $container);
            return;
        }

        // Production mode: load pre-generated data files for performance
        DataProvider::publishContainerData(container: $container);
    }
}
```

> `ComponentProvider::publish` is registered as a `$callbacks` entry in
> `Config.php` and is called by the framework after all providers are booted.

### `app/src/App/Http/Provider/RouteProvider.php`

Declares which controller classes the framework should scan for `#[Route]`
attributes. Optionally accepts manually defined routes via `getRoutes()`.

```php
<?php
declare(strict_types=1);

namespace App\Http\Provider;

use App\Http\Controller\HomeController;
use Override;
use Valkyrja\Http\Routing\Provider\Contract\HttpRouteProviderContract;

final class RouteProvider implements HttpRouteProviderContract
{
    #[Override]
    public static function getControllerClasses(): array
    {
        return [
            HomeController::class,
        ];
    }

    #[Override]
    public static function getRoutes(): array
    {
        return [];
    }
}
```

### `app/src/App/Http/Provider/DataProvider.php`

Registers the three pre-generated data classes from `App\Http\Data` into the DI
container. Used in production mode to skip runtime attribute scanning.

```php
<?php
declare(strict_types=1);

namespace App\Http\Provider;

use App\Http\Data\AppContainerData;
use App\Http\Data\AppEventData;
use App\Http\Data\AppHttpRoutingData;
use Override;
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Event\Data\EventData;
use Valkyrja\Http\Routing\Data\HttpRoutingData;

final class DataProvider implements ServiceProviderContract
{
    #[Override]
    public static function publishers(): array
    {
        return [
            ContainerData::class   => [self::class, 'publishContainerData'],
            EventData::class       => [self::class, 'publishEventData'],
            HttpRoutingData::class => [self::class, 'publishHttpRoutingData'],
        ];
    }

    public static function publishContainerData(ContainerContract $container): void
    {
        $container->setSingleton(ContainerData::class, new AppContainerData());
    }

    public static function publishEventData(ContainerContract $container): void
    {
        $container->setSingleton(EventData::class, new AppEventData());
    }

    public static function publishHttpRoutingData(ContainerContract $container): void
    {
        $container->setSingleton(HttpRoutingData::class, new AppHttpRoutingData());
    }
}
```

### `app/src/App/Http/Provider/ServiceProvider.php`

Registers application-specific services (controllers, etc.) into the DI
container. Add a `publishers()` entry and a corresponding `publish*` method for
each service your app registers manually.

```php
<?php
declare(strict_types=1);

namespace App\Http\Provider;

use App\Http\Controller\HomeController;
use Override;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Http\Message\Request\Contract\ServerRequestContract;
use Valkyrja\Http\Message\Response\Factory\Contract\ResponseFactoryContract;

final class ServiceProvider implements ServiceProviderContract
{
    #[Override]
    public static function publishers(): array
    {
        return [
            HomeController::class => [self::class, 'publishHomeController'],
        ];
    }

    public static function publishHomeController(ContainerContract $container): void
    {
        $container->setSingleton(
            HomeController::class,
            new HomeController(
                $container->getSingleton(ServerRequestContract::class),
                $container->getSingleton(ResponseFactoryContract::class)
            )
        );
    }
}
```

---

## HTTP Data Classes (`App\Http\Data`)

The `App\Http\Data` namespace holds the three pre-generated data classes for the
HTTP application. Each has a committed `.example.php` counterpart containing a
minimal empty baseline, and a gitignored `.php` counterpart populated by
`generate:data` at build/deploy time.

**`AppContainerData.example.php`**

```php
<?php
declare(strict_types=1);

namespace App\Http\Data;

use Valkyrja\Container\Data\ContainerData;

readonly class AppContainerData extends ContainerData
{
}
```

**`AppEventData.example.php`**

```php
<?php
declare(strict_types=1);

namespace App\Http\Data;

use Valkyrja\Event\Data\EventData;

readonly class AppEventData extends EventData
{
}
```

**`AppHttpRoutingData.example.php`**

```php
<?php
declare(strict_types=1);

namespace App\Http\Data;

use Valkyrja\Http\Routing\Data\HttpRoutingData;

readonly class AppHttpRoutingData extends HttpRoutingData
{
}
```

---

## CLI Application

The CLI application follows the same structure as the HTTP application — the
same four provider files (`ComponentProvider`, `RouteProvider`, `DataProvider`,
`ServiceProvider`) exist under the `App\Cli` namespace — with the following
differences.

### `App\Cli\App.php`

Identical in shape to `Http\App` but extends the framework's `Cli` entry class.
Wires in the same `ThrowableHandler`.

### `App\Cli\Config`

Extends `CliConfig` (not `HttpConfig`) and includes an `HttpConfig $http`
property that composes the HTTP config. This gives CLI commands access to HTTP
routing data (for URL generation, etc.).

```php
use App\Http\Config as AppHttpConfig;
use Valkyrja\Application\Data\CliConfig;
use Valkyrja\Cli\Server\Constant\CommandName;

// Additional providers beyond the HTTP set:
ComponentClass::CLI_INTERACTION,
ComponentClass::CLI_MIDDLEWARE,
ComponentClass::CLI_ROUTING,
ComponentClass::CLI_SERVER,
ComponentClass::HTTP_ROUTING_CLI,  // HTTP route access from CLI

// Additional constructor parameters:
string $defaultCommandName = CommandName::LIST,
HttpConfig $http           = new AppHttpConfig(),
```

The `$dataNamespace` for the CLI config should point to `App\Cli\Data`.

### `App\Cli\Provider\DataProvider.php`

Same structure as the HTTP `DataProvider` but imports from `App\Cli\Data` and
registers four data classes instead of three — adding `AppCliRoutingData`.

```php
<?php
declare(strict_types=1);

namespace App\Cli\Provider;

use App\Cli\Data\AppCliRoutingData;
use App\Cli\Data\AppContainerData;
use App\Cli\Data\AppEventData;
use App\Cli\Data\AppHttpRoutingData;
use Override;
use Valkyrja\Cli\Routing\Data\CliRoutingData;
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Event\Data\EventData;
use Valkyrja\Http\Routing\Data\HttpRoutingData;

final class DataProvider implements ServiceProviderContract
{
    #[Override]
    public static function publishers(): array
    {
        return [
            ContainerData::class   => [self::class, 'publishContainerData'],
            EventData::class       => [self::class, 'publishEventData'],
            CliRoutingData::class  => [self::class, 'publishCliRoutingData'],
            HttpRoutingData::class => [self::class, 'publishHttpRoutingData'],
        ];
    }

    public static function publishContainerData(ContainerContract $container): void
    {
        $container->setSingleton(ContainerData::class, new AppContainerData());
    }

    public static function publishEventData(ContainerContract $container): void
    {
        $container->setSingleton(EventData::class, new AppEventData());
    }

    public static function publishCliRoutingData(ContainerContract $container): void
    {
        $container->setSingleton(CliRoutingData::class, new AppCliRoutingData());
    }

    public static function publishHttpRoutingData(ContainerContract $container): void
    {
        $container->setSingleton(HttpRoutingData::class, new AppHttpRoutingData());
    }
}
```

### `App\Cli\Provider\ServiceProvider.php`

Same structure as the HTTP `ServiceProvider`. Register CLI controllers and any
CLI-specific services here.

---

## CLI Data Classes (`App\Cli\Data`)

The `App\Cli\Data` namespace holds four pre-generated data classes — the same
three as HTTP plus `AppCliRoutingData`. Each follows the same
`.example.php` / `.php` pattern.

**`AppContainerData.example.php`**

```php
<?php
declare(strict_types=1);

namespace App\Cli\Data;

use Valkyrja\Container\Data\ContainerData;

readonly class AppContainerData extends ContainerData
{
}
```

**`AppEventData.example.php`**

```php
<?php
declare(strict_types=1);

namespace App\Cli\Data;

use Valkyrja\Event\Data\EventData;

readonly class AppEventData extends EventData
{
}
```

**`AppCliRoutingData.example.php`**

```php
<?php
declare(strict_types=1);

namespace App\Cli\Data;

use Valkyrja\Cli\Routing\Data\CliRoutingData;

readonly class AppCliRoutingData extends CliRoutingData
{
}
```

**`AppHttpRoutingData.example.php`**

```php
<?php
declare(strict_types=1);

namespace App\Cli\Data;

use Valkyrja\Http\Routing\Data\HttpRoutingData;

readonly class AppHttpRoutingData extends HttpRoutingData
{
}
```

---

## Pre-generated Data Files

The `.php` data classes in `App\Http\Data` and `App\Cli\Data` are
**auto-generated** by running:

```bash
php app/bin/cli generate:data
```

> `generate:data` scans PHP attributes across all registered providers. All
> providers must be correctly registered in `ComponentProvider` before running
> this command, or the generated files will be incomplete.

The generated `.php` files are gitignored and must exist at deploy time —
either committed for that environment or generated as part of the deploy
pipeline. In production mode (`$debugMode = false`) the framework loads them
instead of scanning PHP attributes at runtime, which is significantly faster.

In debug mode (`$debugMode = true`) these files are ignored entirely and
attributes are scanned live on every request — so you never need to regenerate
during development.

---

## Adding a Route

1. Create a controller that extends `App\Http\Controller\Controller`.
2. Add a method annotated with a `#[Route]` attribute.
3. Register the controller class in `RouteProvider::getControllerClasses()`.
4. Register the controller in `ServiceProvider::publishers()`.
5. In debug mode the route is live immediately. For production, run
   `php app/bin/cli generate:data` to regenerate the data files.

```php
use Valkyrja\Http\Message\Enum\RequestMethod;
use Valkyrja\Http\Message\Response\Contract\TextResponseContract;
use Valkyrja\Http\Message\Response\TextResponse;
use Valkyrja\Http\Routing\Attribute\Route;

class HomeController extends Controller
{
    #[Route(path: '/', name: 'welcome', requestMethods: [RequestMethod::GET])]
    public function welcome(): TextResponseContract
    {
        return new TextResponse('Hello World!');
    }
}
```

---

## Bootstrap Flow

```
HTTP request
└── app/public/index.php
    └── App::run(config: new Config())
        └── framework loads providers from $config->providers
            └── ComponentProvider::publish() (registered in $config->callbacks)
                ├── debug mode  → scan attributes at runtime
                └── production → load pre-generated Data files from App\Http\Data
                    └── router matches request → controller method → response
```

```
CLI invocation
└── app/bin/cli
    └── App::run(config: new Config())
        └── same provider boot as HTTP
            └── pre-generated Data files loaded from App\Cli\Data
                └── CLI router matches command → controller method → output
```

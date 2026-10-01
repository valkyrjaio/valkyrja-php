<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Functional\Application\Entry;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Valkyrja\Application\Data\CliConfig;
use Valkyrja\Application\Data\Contract\CliConfigContract;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Application\Entry\Cli;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\CliApplicationComponentProvider;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Interaction\Output\Output;
use Valkyrja\Cli\Routing\Attribute\Route;
use Valkyrja\Cli\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Cli\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Cli\Server\Support\Exiter;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Tests\Fixtures\Application\Provider\CliComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\CliRouteProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\CliRoutingDataProviderFixture;
use Valkyrja\Tests\Functional\Abstract\TestCase;

use function ob_get_clean;
use function ob_start;
use function restore_error_handler;
use function restore_exception_handler;

/**
 * Test the Cli service.
 */
#[RunTestsInSeparateProcesses]
final class CliTest extends TestCase
{
    protected static bool $handlerCalled = false;
    protected static bool $runCalled     = false;

    public static function routeHandler(ContainerContract $container, RouteContract $route): OutputContract
    {
        self::$handlerCalled = true;

        return self::routeCallback();
    }

    #[Route('version', 'test')]
    #[RouteHandler([self::class, 'routeHandler'])]
    public static function routeCallback(): Output
    {
        self::$runCalled = true;

        return new Output();
    }

    public function testRun(): void
    {
        Cli::directory(Directory::$basePath);

        self::$handlerCalled = false;
        self::$runCalled     = false;

        CliComponentProviderFixture::$publishedContainerData = false;

        CliRoutingDataProviderFixture::$published = false;

        Exiter::freeze();

        $_SERVER['argv'] = [
            'cli',
            'version',
        ];

        $dir = Directory::$basePath;

        $config = new class(dir: $dir) extends CliConfig {
            public function __construct(
                string $dir,
            ) {
                parent::__construct(
                    dir: $dir,
                    debugMode: true,
                    providers: [
                        new CliApplicationComponentProvider(),
                        new CliComponentProviderFixture(),
                    ],
                );
            }
        };

        $application = Cli::app($config);
        $container   = $application->getContainer();

        $container->getSingleton(RouteCollectionContract::class);

        self::assertTrue($container->has(CliConfigContract::class));
        self::assertTrue($container->has(ContainerContract::class));
        self::assertTrue($container->has(ApplicationContract::class));

        // With debug mode on we expect the data service providers to NOT provide the data and routes
        self::assertTrue(CliRouteProviderFixture::$called);
        CliRouteProviderFixture::$called = false;
        // With debug mode on we expect the component publish method to bypass
        self::assertFalse(CliComponentProviderFixture::$publishedContainerData);
        CliComponentProviderFixture::$publishedContainerData = false;
        // With debug mode on we expect the route data publisher publish method to bypass
        self::assertFalse(CliRoutingDataProviderFixture::$published);
        CliRoutingDataProviderFixture::$published = false;

        $config = new class(dir: $dir) extends CliConfig {
            public function __construct(
                string $dir,
            ) {
                parent::__construct(
                    dir: $dir,
                    debugMode: false,
                    providers: [
                        new CliApplicationComponentProvider(),
                        new CliComponentProviderFixture(),
                    ],
                    callbacks: [
                        [CliComponentProviderFixture::class, 'publish'],
                    ],
                );
            }
        };

        ob_start();
        Cli::run(config: $config);
        ob_get_clean();

        self::assertTrue(self::$runCalled);
        self::$runCalled = false;

        self::assertTrue(self::$handlerCalled);
        self::$handlerCalled = false;

        // With debug mode off we expect the data service providers to provide the data and routes
        self::assertFalse(CliRouteProviderFixture::$called);
        CliRouteProviderFixture::$called = false;
        // With debug mode off we expect the component publish method to NOT bypass
        self::assertTrue(CliComponentProviderFixture::$publishedContainerData);
        CliComponentProviderFixture::$publishedContainerData = false;
        // With debug mode off we expect the route data publisher publish method to NOT bypass
        self::assertTrue(CliRoutingDataProviderFixture::$published);
        CliRoutingDataProviderFixture::$published = false;

        $config = new class(dir: $dir) extends CliConfig {
            public function __construct(
                string $dir,
            ) {
                parent::__construct(
                    dir: $dir,
                    debugMode: true,
                    providers: [
                        new CliApplicationComponentProvider(),
                        new CliComponentProviderFixture(),
                    ],
                    callbacks: [
                        [CliComponentProviderFixture::class, 'publish'],
                    ],
                );
            }
        };

        ob_start();
        Cli::run(config: $config);
        ob_get_clean();

        restore_error_handler();
        restore_exception_handler();

        self::assertTrue(self::$runCalled);
        self::$runCalled = false;

        self::assertTrue(self::$handlerCalled);
        self::$handlerCalled = false;

        // With debug mode on we expect the data service providers to NOT provide the data and routes
        self::assertTrue(CliRouteProviderFixture::$called);
        CliRouteProviderFixture::$called = false;
        // With debug mode on we expect the component publish method to bypass
        self::assertFalse(CliComponentProviderFixture::$publishedContainerData);
        CliComponentProviderFixture::$publishedContainerData = false;
        // With debug mode on we expect the route data publisher publish method to bypass
        self::assertFalse(CliRoutingDataProviderFixture::$published);
        CliRoutingDataProviderFixture::$published = false;

        Exiter::unfreeze();
    }
}

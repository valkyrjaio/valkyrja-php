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
use Valkyrja\Application\Data\HttpConfig;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Application\Entry\Http;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\HttpApplicationComponentProvider;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Http\Message\Response\Contract\ResponseContract;
use Valkyrja\Http\Message\Response\Response;
use Valkyrja\Http\Routing\Attribute\Route;
use Valkyrja\Http\Routing\Attribute\Route\Handler;
use Valkyrja\Http\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Http\Routing\Data\Contract\RouteContract;
use Valkyrja\Tests\Fixtures\Application\Provider\HttpComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\HttpRouteProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\HttpRoutingDataProviderFixture;
use Valkyrja\Tests\Functional\Abstract\TestCase;

use function ob_get_clean;
use function ob_start;

/**
 * Test the Http service.
 */
#[RunTestsInSeparateProcesses]
final class HttpTest extends TestCase
{
    protected static bool $runCalled = false;

    public static function routeHandler(ContainerContract $container, RouteContract $route): ResponseContract
    {
        return self::routeCallback();
    }

    #[Route('/version', 'version')]
    #[Handler([self::class, 'routeHandler'])]
    public static function routeCallback(): Response
    {
        self::$runCalled = true;

        return new Response();
    }

    public function testHttp(): void
    {
        Http::directory(Directory::$basePath);

        self::$runCalled = false;

        HttpComponentProviderFixture::$publishedContainerData = false;

        HttpRoutingDataProviderFixture::$published = false;

        $_SERVER['REQUEST_URI'] = '/version';

        $dir = Directory::$basePath;

        $config = new HttpConfig(
            dir: $dir,
            debugMode: true,
            providers: [
                new HttpApplicationComponentProvider(),
                new HttpComponentProviderFixture(),
            ],
        );

        $application = Http::app($config);
        $container   = $application->getContainer();

        $container->getSingleton(RouteCollectionContract::class);

        self::assertFalse($container->has(CliConfig::class));
        self::assertTrue($container->has(HttpConfig::class));
        self::assertTrue($container->has(ContainerContract::class));
        self::assertTrue($container->has(ApplicationContract::class));

        // With debug mode on we expect the data service providers to NOT provide the data and routes
        self::assertTrue(HttpRouteProviderFixture::$called);
        HttpRouteProviderFixture::$called = false;
        // With debug mode on we expect the component publish method to bypass
        self::assertFalse(HttpComponentProviderFixture::$publishedContainerData);
        HttpComponentProviderFixture::$publishedContainerData = false;
        // With debug mode on we expect the route data publisher publish method to bypass
        self::assertFalse(HttpRoutingDataProviderFixture::$published);
        HttpRoutingDataProviderFixture::$published = false;

        $config = new HttpConfig(
            dir: $dir,
            debugMode: false,
            providers: [
                new HttpApplicationComponentProvider(),
                new HttpComponentProviderFixture(),
            ],
            callbacks: [
                [HttpComponentProviderFixture::class, 'publish'],
            ],
        );

        ob_start();
        Http::run(config: $config);
        ob_get_clean();

        self::assertTrue(self::$runCalled);
        self::$runCalled = false;

        // With debug mode off we expect the data service providers to provide the data and routes
        self::assertFalse(HttpRouteProviderFixture::$called);
        HttpRouteProviderFixture::$called = false;
        // With debug mode off we expect the component publish method to NOT bypass
        self::assertTrue(HttpComponentProviderFixture::$publishedContainerData);
        HttpComponentProviderFixture::$publishedContainerData = false;
        // With debug mode on we expect the route data publisher publish method to NOT bypass
        self::assertTrue(HttpRoutingDataProviderFixture::$published);
        HttpRoutingDataProviderFixture::$published = false;

        $config = new HttpConfig(
            dir: $dir,
            debugMode: true,
            providers: [
                new HttpApplicationComponentProvider(),
                new HttpComponentProviderFixture(),
            ],
            callbacks: [
                [HttpComponentProviderFixture::class, 'publish'],
            ],
        );

        ob_start();
        Http::run(config: $config);
        ob_get_clean();

        self::assertTrue(self::$runCalled);
        self::$runCalled = false;

        // With debug mode on we expect the data service providers to NOT provide the data and routes
        self::assertTrue(HttpRouteProviderFixture::$called);
        HttpRouteProviderFixture::$called = false;
        // With debug mode on we expect the component publish method to bypass
        self::assertFalse(HttpComponentProviderFixture::$publishedContainerData);
        HttpComponentProviderFixture::$publishedContainerData = false;
        // With debug mode on we expect the route data publisher publish method to bypass
        self::assertFalse(HttpRoutingDataProviderFixture::$published);
        HttpRoutingDataProviderFixture::$published = false;
    }
}

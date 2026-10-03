<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Application\Kernel;

use ReflectionClass;
use Valkyrja\Application\Data\Config;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Kernel\Valkyrja;
use Valkyrja\Application\Provider\ApplicationComponentProvider;
use Valkyrja\Application\Provider\CliApplicationComponentProvider;
use Valkyrja\Application\Provider\CliWithHttpApplicationComponentProvider;
use Valkyrja\Cli\Interaction\Provider\CliInteractionComponentProvider;
use Valkyrja\Cli\Middleware\Provider\CliMiddlewareComponentProvider;
use Valkyrja\Cli\Routing\Provider\CliRoutingComponentProvider;
use Valkyrja\Cli\Server\Provider\CliServerComponentProvider;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Provider\ContainerComponentProvider;
use Valkyrja\Event\Provider\EventComponentProvider;
use Valkyrja\Http\Message\Provider\HttpMessageComponentProvider;
use Valkyrja\Http\Middleware\Provider\HttpMiddlewareComponentProvider;
use Valkyrja\Http\Routing\Provider\HttpRoutingCliComponentProvider;
use Valkyrja\Http\Routing\Provider\HttpRoutingComponentProvider;
use Valkyrja\Http\Server\Provider\HttpServerComponentProvider;
use Valkyrja\Log\Provider\LogComponentProvider;
use Valkyrja\Tests\Fixtures\Application\Provider\CliComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\CliContainerDataProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\CliRouteComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\CliRouteProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\CliRoutingDataProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\ComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\EventComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\GrpcRouteComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\GrpcRouteProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\HttpComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\HttpContainerDataProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\HttpRouteComponentProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\HttpRouteProviderFixture;
use Valkyrja\Tests\Fixtures\Application\Provider\HttpRoutingDataProviderFixture;
use Valkyrja\Tests\Fixtures\Event\Provider\ListenerProviderFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function date_default_timezone_get;

/**
 * Test the Application service.
 */
final class ApplicationTest extends TestCase
{
    /**
     * Test the application with defaults.
     */
    public function testDefaults(): void
    {
        $config    = new Config();
        $container = new Container();

        $application = new Valkyrja(
            container: $container,
            config: $config,
        );

        self::assertSame($container, $application->getContainer());
        self::assertSame($config->environment, $application->getEnvironment());
        self::assertSame($config->debugMode, $application->getDebugMode());
        self::assertSame($config->version, $application->getVersion());
        self::assertSame($config->timezone, date_default_timezone_get());

        $providers = $application->getProviders();
        self::assertCount(3, $providers);
        self::assertInstanceOf(ContainerComponentProvider::class, $providers[0]);
        self::assertInstanceOf(EventComponentProvider::class, $providers[1]);
        self::assertInstanceOf(ApplicationComponentProvider::class, $providers[2]);
    }

    /**
     * Test the application with defaults.
     */
    public function testWithCustomComponent(): void
    {
        $config    = new Config(
            providers: [
                new CliWithHttpApplicationComponentProvider(),
                new CliComponentProviderFixture(),
            ],
        );
        $container = new Container();

        $application = new Valkyrja(
            container: $container,
            config: $config,
        );

        self::assertSame($container, $application->getContainer());
        self::assertSame($config->environment, $application->getEnvironment());
        self::assertSame($config->debugMode, $application->getDebugMode());
        self::assertSame($config->version, $application->getVersion());
        self::assertSame($config->timezone, date_default_timezone_get());

        $providers = $application->getProviders();
        self::assertCount(16, $providers);
        self::assertInstanceOf(ContainerComponentProvider::class, $providers[0]);
        self::assertInstanceOf(EventComponentProvider::class, $providers[1]);
        self::assertInstanceOf(ApplicationComponentProvider::class, $providers[2]);
        self::assertInstanceOf(CliInteractionComponentProvider::class, $providers[3]);
        self::assertInstanceOf(CliMiddlewareComponentProvider::class, $providers[4]);
        self::assertInstanceOf(CliRoutingComponentProvider::class, $providers[5]);
        self::assertInstanceOf(CliServerComponentProvider::class, $providers[6]);
        self::assertInstanceOf(LogComponentProvider::class, $providers[7]);
        self::assertInstanceOf(CliApplicationComponentProvider::class, $providers[8]);
        self::assertInstanceOf(HttpMessageComponentProvider::class, $providers[9]);
        self::assertInstanceOf(HttpMiddlewareComponentProvider::class, $providers[10]);
        self::assertInstanceOf(HttpRoutingComponentProvider::class, $providers[11]);
        self::assertInstanceOf(HttpRoutingCliComponentProvider::class, $providers[12]);
        self::assertInstanceOf(HttpServerComponentProvider::class, $providers[13]);
        self::assertInstanceOf(CliWithHttpApplicationComponentProvider::class, $providers[14]);
        self::assertInstanceOf(CliComponentProviderFixture::class, $providers[15]);
    }

    /**
     * Test that getProviders expands nested component providers from getComponentProviders.
     */
    public function testGetProvidersExpandsComponentProviders(): void
    {
        $config      = new Config(providers: [new ComponentProviderFixture()]);
        $application = new Valkyrja(container: new Container(), config: $config);

        $providers = $application->getProviders();
        self::assertCount(3, $providers);
        self::assertInstanceOf(CliComponentProviderFixture::class, $providers[0]);
        self::assertInstanceOf(HttpComponentProviderFixture::class, $providers[1]);
        self::assertInstanceOf(ComponentProviderFixture::class, $providers[2]);
    }

    /**
     * Test that getProviders populates the internal cache and subsequent calls use it.
     */
    public function testGetProvidersIsCached(): void
    {
        $config      = new Config(providers: [new ComponentProviderFixture()]);
        $application = new Valkyrja(container: new Container(), config: $config);
        $reflection  = new ReflectionClass($application);
        $property    = $reflection->getProperty('providers');

        self::assertSame([], $property->getValue($application));

        $result = $application->getProviders();

        self::assertCount(3, $result);
        self::assertInstanceOf(CliComponentProviderFixture::class, $result[0]);
        self::assertInstanceOf(HttpComponentProviderFixture::class, $result[1]);
        self::assertInstanceOf(ComponentProviderFixture::class, $result[2]);
        self::assertSame($result, $property->getValue($application));
        self::assertSame($result, $application->getProviders());
    }

    /**
     * Test that getContainerProviders collects results from all expanded providers.
     */
    public function testGetContainerProviders(): void
    {
        $config      = new Config(providers: [new ComponentProviderFixture()]);
        $application = new Valkyrja(container: new Container(), config: $config);

        $result = $application->getContainerProviders();

        self::assertCount(4, $result);
        self::assertInstanceOf(CliContainerDataProviderFixture::class, $result[0]);
        self::assertInstanceOf(CliRoutingDataProviderFixture::class, $result[1]);
        self::assertInstanceOf(HttpContainerDataProviderFixture::class, $result[2]);
        self::assertInstanceOf(HttpRoutingDataProviderFixture::class, $result[3]);
    }

    /**
     * Test that getContainerProviders populates the internal cache and subsequent calls use it.
     */
    public function testGetContainerProvidersIsCached(): void
    {
        $config      = new Config(providers: [new ComponentProviderFixture()]);
        $application = new Valkyrja(container: new Container(), config: $config);
        $reflection  = new ReflectionClass($application);
        $property    = $reflection->getProperty('serviceProviders');

        self::assertSame([], $property->getValue($application));

        $result = $application->getContainerProviders();

        self::assertCount(4, $result);
        self::assertInstanceOf(CliContainerDataProviderFixture::class, $result[0]);
        self::assertInstanceOf(CliRoutingDataProviderFixture::class, $result[1]);
        self::assertInstanceOf(HttpContainerDataProviderFixture::class, $result[2]);
        self::assertInstanceOf(HttpRoutingDataProviderFixture::class, $result[3]);
        self::assertSame($result, $property->getValue($application));
        self::assertSame($result, $application->getContainerProviders());
    }

    /**
     * Test that getEventProviders collects results from all expanded providers.
     */
    public function testGetEventProviders(): void
    {
        $config      = new Config(providers: [new EventComponentProviderFixture()]);
        $application = new Valkyrja(container: new Container(), config: $config);

        self::assertSame(
            [ListenerProviderFixture::class],
            $application->getEventProviders(),
        );
    }

    /**
     * Test that getEventProviders populates the internal cache and subsequent calls use it.
     */
    public function testGetEventProvidersIsCached(): void
    {
        $config      = new Config(providers: [new EventComponentProviderFixture()]);
        $application = new Valkyrja(container: new Container(), config: $config);
        $reflection  = new ReflectionClass($application);
        $property    = $reflection->getProperty('eventProviders');

        self::assertSame([], $property->getValue($application));

        $result = $application->getEventProviders();

        self::assertSame([ListenerProviderFixture::class], $result);
        self::assertSame($result, $property->getValue($application));
        self::assertSame($result, $application->getEventProviders());
    }

    /**
     * Test that getCliProviders collects results from all expanded providers.
     */
    public function testGetCliProviders(): void
    {
        $config      = new Config(providers: [new CliRouteComponentProviderFixture()]);
        $application = new Valkyrja(container: new Container(), config: $config);

        self::assertSame(
            [CliRouteProviderFixture::class],
            $application->getCliProviders(),
        );
    }

    /**
     * Test that getCliProviders populates the internal cache and subsequent calls use it.
     */
    public function testGetCliProvidersIsCached(): void
    {
        $config      = new Config(providers: [new CliRouteComponentProviderFixture()]);
        $application = new Valkyrja(container: new Container(), config: $config);
        $reflection  = new ReflectionClass($application);
        $property    = $reflection->getProperty('cliRouteProviders');

        self::assertSame([], $property->getValue($application));

        $result = $application->getCliProviders();

        self::assertSame([CliRouteProviderFixture::class], $result);
        self::assertSame($result, $property->getValue($application));
        self::assertSame($result, $application->getCliProviders());
    }

    /**
     * Test that getHttpProviders collects results from all expanded providers.
     */
    public function testGetHttpProviders(): void
    {
        $config      = new Config(providers: [new HttpRouteComponentProviderFixture()]);
        $application = new Valkyrja(container: new Container(), config: $config);

        self::assertSame(
            [HttpRouteProviderFixture::class],
            $application->getHttpProviders(),
        );
    }

    /**
     * Test that getHttpProviders populates the internal cache and subsequent calls use it.
     */
    public function testGetHttpProvidersIsCached(): void
    {
        $config      = new Config(providers: [new HttpRouteComponentProviderFixture()]);
        $application = new Valkyrja(container: new Container(), config: $config);
        $reflection  = new ReflectionClass($application);
        $property    = $reflection->getProperty('httpRouteProviders');

        self::assertSame([], $property->getValue($application));

        $result = $application->getHttpProviders();

        self::assertSame([HttpRouteProviderFixture::class], $result);
        self::assertSame($result, $property->getValue($application));
        self::assertSame($result, $application->getHttpProviders());
    }

    /**
     * Test that getGrpcProviders collects results from all expanded providers.
     */
    public function testGetGrpcProviders(): void
    {
        $config      = new Config(providers: [new GrpcRouteComponentProviderFixture()]);
        $application = new Valkyrja(container: new Container(), config: $config);

        self::assertSame(
            [GrpcRouteProviderFixture::class],
            $application->getGrpcProviders(),
        );
    }

    /**
     * Test that getGrpcProviders populates the internal cache and subsequent calls use it.
     */
    public function testGetGrpcProvidersIsCached(): void
    {
        $config      = new Config(providers: [new GrpcRouteComponentProviderFixture()]);
        $application = new Valkyrja(container: new Container(), config: $config);
        $reflection  = new ReflectionClass($application);
        $property    = $reflection->getProperty('grpcRouteProviders');

        self::assertSame([], $property->getValue($application));

        $result = $application->getGrpcProviders();

        self::assertSame([GrpcRouteProviderFixture::class], $result);
        self::assertSame($result, $property->getValue($application));
        self::assertSame($result, $application->getGrpcProviders());
    }

    /**
     * Test that publishProviderCallbacks invokes each callback with the application.
     */
    public function testPublishProviderCallbacks(): void
    {
        $received = [];

        $config = new Config(
            providers: [],
            callbacks: [
                static function (ApplicationContract $app) use (&$received): void {
                    $received[] = $app;
                },
                static function (ApplicationContract $app) use (&$received): void {
                    $received[] = $app;
                },
            ],
        );

        $application = new Valkyrja(container: new Container(), config: $config);
        $application->publishProviderCallbacks();

        self::assertCount(2, $received);
        self::assertSame($application, $received[0]);
        self::assertSame($application, $received[1]);
    }

    /**
     * Test that the provider getters return empty arrays when there are no providers.
     */
    public function testProviderGettersWithNoProviders(): void
    {
        $config      = new Config(providers: []);
        $application = new Valkyrja(container: new Container(), config: $config);

        self::assertSame([], $application->getProviders());
        self::assertSame([], $application->getContainerProviders());
        self::assertSame([], $application->getEventProviders());
        self::assertSame([], $application->getCliProviders());
        self::assertSame([], $application->getHttpProviders());
        self::assertSame([], $application->getGrpcProviders());
    }

    /**
     * Test that publishProviderCallbacks with no callbacks does nothing.
     */
    public function testPublishProviderCallbacksWithNoCallbacks(): void
    {
        $config      = new Config(providers: [], callbacks: []);
        $application = new Valkyrja(container: new Container(), config: $config);

        // No exception should be thrown
        $application->publishProviderCallbacks();

        self::assertTrue(true);
    }
}

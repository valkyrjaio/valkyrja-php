<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Application\Entry\Abstract;

use Override;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Valkyrja\Application\Data\CliConfig;
use Valkyrja\Application\Data\Config;
use Valkyrja\Application\Data\Contract\CliConfigContract;
use Valkyrja\Application\Data\Contract\HttpConfigContract;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Data\HttpConfig;
use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Application\Entry\Abstract\App;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Attribute\Collector\Contract\CollectorContract;
use Valkyrja\Cli\Interaction\Data\Contract\CliInteractionConfigContract;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Interaction\Provider\CliInteractionComponentProvider;
use Valkyrja\Cli\Middleware\Handler\Contract\InputReceivedHandlerContract;
use Valkyrja\Cli\Middleware\Handler\Contract\ProcessExitingHandlerContract;
use Valkyrja\Cli\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Cli\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Cli\Middleware\Handler\Contract\RouteNotMatchedHandlerContract;
use Valkyrja\Cli\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Cli\Middleware\Provider\CliMiddlewareComponentProvider;
use Valkyrja\Cli\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Cli\Routing\Collector\Contract\RouteCollectorContract as CliRoutingCollector;
use Valkyrja\Cli\Routing\Data\CliRoutingData;
use Valkyrja\Cli\Routing\Dispatcher\Contract\RouterContract;
use Valkyrja\Cli\Routing\Provider\CliRoutingCliRouteProvider;
use Valkyrja\Cli\Routing\Provider\CliRoutingComponentProvider;
use Valkyrja\Cli\Server\Handler\Contract\InputHandlerContract;
use Valkyrja\Cli\Server\Middleware\ThrowableCaught\LogThrowableCaughtMiddleware as CliLogThrowableCaughtMiddleware;
use Valkyrja\Cli\Server\Provider\CliServerComponentProvider;
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\ContainerComponentProvider;
use Valkyrja\Event\Collection\Contract\ListenerCollectionContract;
use Valkyrja\Event\Collector\Contract\ListenerCollectorContract;
use Valkyrja\Event\Data\EventData;
use Valkyrja\Event\Dispatcher\Contract\EventDispatcherContract;
use Valkyrja\Event\Provider\EventComponentProvider;
use Valkyrja\Http\Message\Provider\HttpMessageComponentProvider;
use Valkyrja\Http\Message\Response\Factory\Contract\ResponseFactoryContract;
use Valkyrja\Http\Middleware\Handler\Contract\RequestReceivedHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\ResponseSentHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\RouteDispatchedHandlerContract as HttpRouteDispatchedHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\RouteMatchedHandlerContract as HttpRouteMatchedHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\RouteNotMatchedHandlerContract as HttpRouteNotMatchedHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\SendingResponseHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\ThrowableCaughtHandlerContract as HttpThrowableCaughtHandler;
use Valkyrja\Http\Middleware\Provider\HttpMiddlewareComponentProvider;
use Valkyrja\Http\Routing\Collection\Contract\RouteCollectionContract as HttpRoutingCollection;
use Valkyrja\Http\Routing\Collector\Contract\RouteCollectorContract;
use Valkyrja\Http\Routing\Data\HttpRoutingData;
use Valkyrja\Http\Routing\Dispatcher\Contract\RouterContract as HttpRoutingRouter;
use Valkyrja\Http\Routing\Factory\Contract\RoutingResponseFactoryContract;
use Valkyrja\Http\Routing\Matcher\Contract\MatcherContract;
use Valkyrja\Http\Routing\Processor\Contract\ProcessorContract;
use Valkyrja\Http\Routing\Provider\HttpRoutingCliComponentProvider;
use Valkyrja\Http\Routing\Provider\HttpRoutingCliRouteProvider;
use Valkyrja\Http\Routing\Provider\HttpRoutingComponentProvider;
use Valkyrja\Http\Routing\Url\Contract\UrlContract;
use Valkyrja\Http\Server\Handler\Contract\RequestHandlerContract;
use Valkyrja\Http\Server\Middleware\CacheResponseMiddleware;
use Valkyrja\Http\Server\Middleware\RouteMatched\RequestStructMiddleware;
use Valkyrja\Http\Server\Middleware\RouteMatched\ResponseStructMiddleware;
use Valkyrja\Http\Server\Middleware\RouteNotMatched\ViewRouteNotMatchedMiddleware;
use Valkyrja\Http\Server\Middleware\ThrowableCaught\LogThrowableCaughtMiddleware;
use Valkyrja\Http\Server\Middleware\ThrowableCaught\ViewThrowableCaughtMiddleware;
use Valkyrja\Http\Server\Provider\HttpServerComponentProvider;
use Valkyrja\Reflection\Reflector\Contract\ReflectorContract;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Tests\Fixtures\Application\Entry\AppExceptionHandlerFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;
use Valkyrja\Throwable\Handler\Contract\ThrowableHandlerContract;
use Valkyrja\View\Provider\ViewComponentProvider;
use Valkyrja\View\Renderer\Contract\RendererContract;
use Valkyrja\View\Template\Contract\TemplateContract;

use function date_default_timezone_get;
use function defined;
use function set_exception_handler;
use function unlink;

/**
 * Test the App service.
 */
#[RunTestsInSeparateProcesses]
final class AppTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        $directory = Directory::srcPath('App/Provider/Data');

        @unlink("$directory/ContainerData.php");
        @unlink("$directory/HttpRoutingData.php");
    }

    /**
     * Test the appStart method.
     */
    #[RunInSeparateProcess]
    public function testAppStart(): void
    {
        $time = Microtime::get();

        Microtime::freeze($time);

        App::appStart();

        self::assertTrue(defined('APP_START'));
        self::assertSame(APP_START, $time);

        Microtime::unfreeze();
    }

    /**
     * Test the appStart method with all debug modes.
     */
    #[RunInSeparateProcess]
    public function testStartExceptionHandlerDebugModes(): void
    {
        AppExceptionHandlerFixture::$called = false;

        AppExceptionHandlerFixture::start(new Config(debugMode: true));

        self::assertTrue(AppExceptionHandlerFixture::$called);

        AppExceptionHandlerFixture::$called = false;

        AppExceptionHandlerFixture::start(new Config(debugMode: false));

        self::assertFalse(AppExceptionHandlerFixture::$called);

        AppExceptionHandlerFixture::$called = false;
    }

    /**
     * Test the directory method.
     */
    public function testDirectory(): void
    {
        $path = __DIR__;

        App::directory($path);

        self::assertSame($path, Directory::$basePath);
    }

    /**
     * Test the app method.
     */
    public function testApp(): void
    {
        App::directory(Directory::$basePath);

        $config = new Config();

        $application = App::app($config);

        $container = $application->getContainer();

        self::assertSame($config, $container->getSingleton(Config::class));
        self::assertSame($config->timezone, date_default_timezone_get());
    }

    /**
     * Test the app method.
     */
    public function testAppWithCache(): void
    {
        App::directory(Directory::$basePath);

        $config = new Config(
            providers: [
                new EventComponentProvider(),
                new CliRoutingComponentProvider(),
                new HttpRoutingComponentProvider(),
            ]
        );

        $application = App::app($config);

        $container = $application->getContainer();

        self::assertTrue($container->has(ContainerData::class));
        self::assertTrue($container->has(EventData::class));
        self::assertTrue($container->has(CliRoutingData::class));
        self::assertTrue($container->has(HttpRoutingData::class));
        self::assertSame($config, $container->getSingleton(Config::class));
        self::assertSame($config->timezone, date_default_timezone_get());
    }

    /**
     * Ensure that default components are added when using config with empty APP_COMPONENTS.
     */
    public function testEnsureDefaultComponents(): void
    {
        $config = new Config(
            providers: [
                new EventComponentProvider(),
                new CliInteractionComponentProvider(),
                new CliMiddlewareComponentProvider(),
                new CliRoutingComponentProvider(),
                new CliServerComponentProvider(),
                new HttpMessageComponentProvider(),
                new HttpMiddlewareComponentProvider(),
                new HttpRoutingComponentProvider(),
                new HttpRoutingCliComponentProvider(),
                new HttpServerComponentProvider(),
            ]
        );

        $application = App::app($config);

        $container = $application->getContainer();

        self::assertSame($container, $application->getContainer());
        self::assertFalse($container->has(ReflectorContract::class));
        self::assertFalse($container->has(CollectorContract::class));
        self::assertTrue($container->has(ApplicationContract::class));
        self::assertTrue($container->has(Config::class));
        self::assertTrue($container->has(CliInteractionConfigContract::class));
        self::assertTrue($container->has(OutputFactoryContract::class));
        self::assertTrue($container->has(InputReceivedHandlerContract::class));
        self::assertTrue($container->has(ThrowableCaughtHandlerContract::class));
        self::assertTrue($container->has(RouteMatchedHandlerContract::class));
        self::assertTrue($container->has(RouteNotMatchedHandlerContract::class));
        self::assertTrue($container->has(RouteDispatchedHandlerContract::class));
        self::assertTrue($container->has(ProcessExitingHandlerContract::class));
        self::assertTrue($container->has(CliRoutingCollector::class));
        self::assertTrue($container->has(RouterContract::class));
        self::assertTrue($container->has(RouteCollectionContract::class));
        self::assertTrue($container->has(InputHandlerContract::class));
        self::assertTrue($container->has(CliLogThrowableCaughtMiddleware::class));
        self::assertTrue($container->has(ListenerCollectorContract::class));
        self::assertTrue($container->has(EventDispatcherContract::class));
        self::assertTrue($container->has(ListenerCollectionContract::class));
        self::assertTrue($container->has(ResponseFactoryContract::class));
        self::assertTrue($container->has(RequestReceivedHandlerContract::class));
        self::assertTrue($container->has(HttpThrowableCaughtHandler::class));
        self::assertTrue($container->has(HttpRouteMatchedHandlerContract::class));
        self::assertTrue($container->has(HttpRouteNotMatchedHandlerContract::class));
        self::assertTrue($container->has(HttpRouteDispatchedHandlerContract::class));
        self::assertTrue($container->has(SendingResponseHandlerContract::class));
        self::assertTrue($container->has(ResponseSentHandlerContract::class));
        self::assertTrue($container->has(CacheResponseMiddleware::class));
        self::assertTrue($container->has(HttpRoutingRouter::class));
        self::assertTrue($container->has(HttpRoutingCollection::class));
        self::assertTrue($container->has(MatcherContract::class));
        self::assertTrue($container->has(UrlContract::class));
        self::assertTrue($container->has(RouteCollectorContract::class));
        self::assertTrue($container->has(ProcessorContract::class));
        self::assertTrue($container->has(RoutingResponseFactoryContract::class));
        self::assertTrue($container->has(RequestStructMiddleware::class));
        self::assertTrue($container->has(ResponseStructMiddleware::class));
        self::assertTrue($container->has(ViewRouteNotMatchedMiddleware::class));
        self::assertTrue($container->has(RequestHandlerContract::class));
        self::assertTrue($container->has(LogThrowableCaughtMiddleware::class));
        self::assertTrue($container->has(ViewThrowableCaughtMiddleware::class));

        $cliProviders = $application->getCliProviders();

        self::assertCount(2, $cliProviders);
        self::assertInstanceOf(CliRoutingCliRouteProvider::class, $cliProviders[0]);
        self::assertInstanceOf(HttpRoutingCliRouteProvider::class, $cliProviders[1]);

        self::assertEmpty($application->getEventProviders());
        self::assertEmpty($application->getHttpProviders());
    }

    /**
     * Testing custom components capability.
     */
    public function testCustomComponents(): void
    {
        $config = new Config(
            providers: [
                new ContainerComponentProvider(),
            ],
        );

        $application = App::app($config);

        $container = $application->getContainer();

        self::assertSame($container, $application->getContainer());
        self::assertFalse($container->has(TemplateContract::class));

        $config2 = new Config(
            providers: [
                new ContainerComponentProvider(),
                new ViewComponentProvider(),
            ],
        );

        $application2 = App::app($config2);

        $container2 = $application2->getContainer();

        self::assertSame($container2, $application2->getContainer());
        self::assertTrue($container2->has(RendererContract::class));
    }

    /**
     * Test bootstrapServices registers the CliConfigContract when a CLI config is given.
     */
    public function testBootstrapServicesRegistersCliConfigContract(): void
    {
        App::directory(Directory::$basePath);

        $config = new CliConfig(providers: []);

        $application = App::app($config);

        self::assertTrue($application->getContainer()->has(CliConfigContract::class));
        self::assertSame($config, $application->getContainer()->getSingleton(CliConfigContract::class));
    }

    /**
     * Test bootstrapServices registers the HttpConfigContract when an HTTP config is given.
     */
    public function testBootstrapServicesRegistersHttpConfigContract(): void
    {
        App::directory(Directory::$basePath);

        $config = new HttpConfig(providers: []);

        $application = App::app($config);

        self::assertTrue($application->getContainer()->has(HttpConfigContract::class));
        self::assertSame($config, $application->getContainer()->getSingleton(HttpConfigContract::class));
    }

    /**
     * Test bootstrapServices registers the QueueConfigContract when a queue config is given.
     */
    public function testBootstrapServicesRegistersQueueConfigContract(): void
    {
        App::directory(Directory::$basePath);

        $config = new QueueConfig(providers: []);

        $application = App::app($config);

        self::assertTrue($application->getContainer()->has(QueueConfigContract::class));
        self::assertSame($config, $application->getContainer()->getSingleton(QueueConfigContract::class));
    }

    /**
     * Test the default exception handler enables without error.
     */
    #[RunInSeparateProcess]
    public function testDefaultExceptionHandler(): void
    {
        App::defaultExceptionHandler();

        self::assertIsCallable(set_exception_handler(null));
    }

    /**
     * Test bootstrapThrowableHandler registers and enables the handler when debug is on.
     */
    #[RunInSeparateProcess]
    public function testBootstrapThrowableHandlerEnablesHandlerWhenDebug(): void
    {
        $app = $this->createMock(ApplicationContract::class);
        $app->expects($this->once())->method('getDebugMode')->willReturn(true);

        $container = $this->createMock(ContainerContract::class);
        $container->expects($this->once())
            ->method('setSingleton')
            ->with(ThrowableHandlerContract::class, self::isInstanceOf(ThrowableHandlerContract::class));

        App::bootstrapThrowableHandler($app, $container);
    }

    /**
     * Test bootstrapThrowableHandler does nothing when debug is off.
     */
    public function testBootstrapThrowableHandlerSkipsWhenNotDebug(): void
    {
        $app = $this->createMock(ApplicationContract::class);
        $app->expects($this->once())->method('getDebugMode')->willReturn(false);

        $container = $this->createMock(ContainerContract::class);
        $container->expects($this->never())->method('setSingleton');

        App::bootstrapThrowableHandler($app, $container);
    }
}

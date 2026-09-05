<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Middleware\Provider;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Queue\Middleware\Handler\Contract\JobReceivedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ResultSettledHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteNotMatchedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\SettlingResultHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Queue\Middleware\Handler\JobReceivedHandler;
use Valkyrja\Queue\Middleware\Handler\ResultSettledHandler;
use Valkyrja\Queue\Middleware\Handler\RouteDispatchedHandler;
use Valkyrja\Queue\Middleware\Handler\RouteMatchedHandler;
use Valkyrja\Queue\Middleware\Handler\RouteNotMatchedHandler;
use Valkyrja\Queue\Middleware\Handler\SettlingResultHandler;
use Valkyrja\Queue\Middleware\Handler\ThrowableCaughtHandler;
use Valkyrja\Queue\Middleware\Provider\QueueMiddlewareServiceProvider;

final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = QueueMiddlewareServiceProvider::class;

    /**
     * @return array<string, array{class-string, class-string}>
     */
    public static function handlerProvider(): array
    {
        return [
            'job received'      => [JobReceivedHandlerContract::class, JobReceivedHandler::class],
            'route matched'     => [RouteMatchedHandlerContract::class, RouteMatchedHandler::class],
            'route not matched' => [RouteNotMatchedHandlerContract::class, RouteNotMatchedHandler::class],
            'route dispatched'  => [RouteDispatchedHandlerContract::class, RouteDispatchedHandler::class],
            'throwable caught'  => [ThrowableCaughtHandlerContract::class, ThrowableCaughtHandler::class],
            'settling result'   => [SettlingResultHandlerContract::class, SettlingResultHandler::class],
            'result settled'    => [ResultSettledHandlerContract::class, ResultSettledHandler::class],
        ];
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->container->setSingleton(QueueConfigContract::class, new QueueConfig());
    }

    /**
     * @param class-string $contract
     * @param class-string $concrete
     */
    #[DataProvider('handlerProvider')]
    public function testPublishesHandler(string $contract, string $concrete): void
    {
        $callback = new QueueMiddlewareServiceProvider()->publishers()[$contract];
        $callback($this->container);

        self::assertInstanceOf($concrete, $this->container->getSingleton($contract));
    }

    public function testExpectedPublishers(): void
    {
        self::assertCount(7, new QueueMiddlewareServiceProvider()->publishers());
    }
}

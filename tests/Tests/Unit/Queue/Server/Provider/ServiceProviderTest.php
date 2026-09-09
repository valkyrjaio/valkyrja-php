<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Server\Provider;

use Override;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Data\QueueConfig;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Queue\Middleware\Provider\QueueMiddlewareServiceProvider;
use Valkyrja\Queue\Routing\Dispatcher\Contract\RouterContract;
use Valkyrja\Queue\Routing\Dispatcher\Router;
use Valkyrja\Queue\Server\Handler\Contract\JobHandlerContract;
use Valkyrja\Queue\Server\Handler\JobHandler;
use Valkyrja\Queue\Server\Mapper\Contract\RequestMapperContract;
use Valkyrja\Queue\Server\Mapper\RequestMapper;
use Valkyrja\Queue\Server\Middleware\ThrowableCaught\LogThrowableCaughtMiddleware;
use Valkyrja\Queue\Server\Middleware\ThrowableCaught\RetryPolicyThrowableCaughtMiddleware;
use Valkyrja\Queue\Server\Provider\QueueServerServiceProvider;

final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = QueueServerServiceProvider::class;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->container->setSingleton(QueueConfigContract::class, new QueueConfig());
        $this->container->setSingleton(ApplicationContract::class, self::createStub(ApplicationContract::class));
    }

    public function testExpectedPublishers(): void
    {
        $publishers = new QueueServerServiceProvider()->publishers();

        self::assertArrayHasKey(JobHandlerContract::class, $publishers);
        self::assertArrayHasKey(RequestMapperContract::class, $publishers);
        self::assertArrayHasKey(LogThrowableCaughtMiddleware::class, $publishers);
        self::assertArrayHasKey(RetryPolicyThrowableCaughtMiddleware::class, $publishers);
    }

    public function testPublishJobHandler(): void
    {
        foreach (new QueueMiddlewareServiceProvider()->publishers() as $callback) {
            $callback($this->container);
        }

        $this->container->setSingleton(RouterContract::class, new Router());

        $this->publish(JobHandlerContract::class);

        self::assertInstanceOf(JobHandler::class, $this->container->getSingleton(JobHandlerContract::class));
    }

    public function testPublishRequestMapper(): void
    {
        $this->publish(RequestMapperContract::class);

        self::assertInstanceOf(RequestMapper::class, $this->container->getSingleton(RequestMapperContract::class));
    }

    public function testPublishLogThrowableCaughtMiddleware(): void
    {
        $this->container->setSingleton(LoggerContract::class, self::createStub(LoggerContract::class));

        $this->publish(LogThrowableCaughtMiddleware::class);

        self::assertInstanceOf(
            LogThrowableCaughtMiddleware::class,
            $this->container->getSingleton(LogThrowableCaughtMiddleware::class)
        );
    }

    public function testPublishRetryPolicyThrowableCaughtMiddleware(): void
    {
        $this->publish(RetryPolicyThrowableCaughtMiddleware::class);

        self::assertInstanceOf(
            RetryPolicyThrowableCaughtMiddleware::class,
            $this->container->getSingleton(RetryPolicyThrowableCaughtMiddleware::class)
        );
    }

    /**
     * Publish a single service by its contract.
     *
     * @param class-string $contract
     */
    protected function publish(string $contract): void
    {
        $callback = new QueueServerServiceProvider()->publishers()[$contract];

        $callback($this->container);
    }
}

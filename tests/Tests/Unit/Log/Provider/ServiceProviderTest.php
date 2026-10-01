<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Log\Provider;

use Monolog\Logger;
use PHPUnit\Framework\MockObject\Exception;
use Psr\Log\LoggerInterface;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Log\Data\Contract\LogConfigContract;
use Valkyrja\Log\Data\Contract\LogPsrConfigContract;
use Valkyrja\Log\Data\LogConfig;
use Valkyrja\Log\Data\LogPsrConfig;
use Valkyrja\Log\Enum\LogLevel;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Log\Logger\NullLogger;
use Valkyrja\Log\Logger\PsrLogger;
use Valkyrja\Log\Provider\LogServiceProvider;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Tests\Fixtures\Log\Data\LogConfigFixture;

/**
 * Test the ServiceProvider.
 */
final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = LogServiceProvider::class;

    public function testExpectedPublishers(): void
    {
        self::assertArrayHasKey(LogConfigContract::class, new LogServiceProvider()->publishers());
        self::assertArrayHasKey(LogPsrConfigContract::class, new LogServiceProvider()->publishers());
        self::assertArrayHasKey(LoggerContract::class, new LogServiceProvider()->publishers());
        self::assertArrayHasKey(PsrLogger::class, new LogServiceProvider()->publishers());
        self::assertArrayHasKey(NullLogger::class, new LogServiceProvider()->publishers());
        self::assertArrayHasKey(LoggerInterface::class, new LogServiceProvider()->publishers());
        self::assertArrayHasKey(Logger::class, new LogServiceProvider()->publishers());
    }

    public function testPublishConfig(): void
    {
        $callback = new LogServiceProvider()->publishers()[LogConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(LogConfigContract::class, $config = $this->container->getSingleton(LogConfigContract::class));
        self::assertSame(PsrLogger::class, $config->defaultLogger);
    }

    public function testPublishConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new LogConfigFixture());

        $callback = new LogServiceProvider()->publishers()[LogConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(LogConfigContract::class, $config = $this->container->getSingleton(LogConfigContract::class));
        self::assertSame(NullLogger::class, $config->defaultLogger);
    }

    public function testPublishPsrConfig(): void
    {
        $callback = new LogServiceProvider()->publishers()[LogPsrConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(LogPsrConfig::class, $config = $this->container->getSingleton(LogPsrConfigContract::class));
        self::assertNull($config->psrName);
        self::assertNull($config->psrFilePath);
        self::assertSame(LogLevel::DEBUG, $config->psrLevel);
    }

    public function testPublishPsrConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, $appConfig = new LogConfigFixture());

        $callback = new LogServiceProvider()->publishers()[LogPsrConfigContract::class];
        $callback($this->container);

        self::assertSame($appConfig, $config = $this->container->getSingleton(LogPsrConfigContract::class));
        self::assertSame('fixture-log', $config->psrName);
    }

    /**
     * @throws Exception
     */
    public function testPublishLogger(): void
    {
        $this->container->setSingleton(LogConfigContract::class, new LogConfig());
        $this->container->setSingleton(PsrLogger::class, self::createStub(PsrLogger::class));

        $callback = new LogServiceProvider()->publishers()[LoggerContract::class];
        $callback($this->container);

        self::assertInstanceOf(PsrLogger::class, $this->container->getSingleton(LoggerContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishLoggerWithConfiguredDefault(): void
    {
        $this->container->setSingleton(LogConfigContract::class, new LogConfig(defaultLogger: NullLogger::class));
        $this->container->setSingleton(NullLogger::class, self::createStub(NullLogger::class));

        $callback = new LogServiceProvider()->publishers()[LoggerContract::class];
        $callback($this->container);

        self::assertInstanceOf(NullLogger::class, $this->container->getSingleton(LoggerContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishPsrLogger(): void
    {
        $this->container->setSingleton(LoggerInterface::class, self::createStub(LoggerInterface::class));

        $callback = new LogServiceProvider()->publishers()[PsrLogger::class];
        $callback($this->container);

        self::assertInstanceOf(PsrLogger::class, $this->container->getSingleton(PsrLogger::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishLoggerInterface(): void
    {
        $this->container->setSingleton(Logger::class, self::createStub(Logger::class));

        $callback = new LogServiceProvider()->publishers()[LoggerInterface::class];
        $callback($this->container);

        self::assertInstanceOf(Logger::class, $this->container->getSingleton(LoggerInterface::class));
    }

    public function testPublishMonolog(): void
    {
        $this->container->setSingleton(LogPsrConfigContract::class, new LogPsrConfig());

        $callback = new LogServiceProvider()->publishers()[Logger::class];
        $callback($this->container);

        self::assertInstanceOf(Logger::class, $logger = $this->container->getSingleton(Logger::class));
        // A null name and a null path mean the application name with the date, in the logs storage directory.
        self::assertStringStartsWith('valkyrja-', $logger->getName());
        self::assertStringContainsString(Directory::logsStoragePath(), $logger->getHandlers()[0]->getUrl() ?? '');
    }

    public function testPublishMonologWithConfiguredNameAndPath(): void
    {
        $this->container->setSingleton(
            LogPsrConfigContract::class,
            new LogPsrConfig(psrName: 'configured', psrFilePath: '/tmp', psrLevel: LogLevel::WARNING)
        );

        $callback = new LogServiceProvider()->publishers()[Logger::class];
        $callback($this->container);

        self::assertInstanceOf(Logger::class, $logger = $this->container->getSingleton(Logger::class));
        self::assertSame('configured', $logger->getName());
        self::assertSame('/tmp/configured.log', $logger->getHandlers()[0]->getUrl());
    }

    public function testPublishNullLogger(): void
    {
        $callback = new LogServiceProvider()->publishers()[NullLogger::class];
        $callback($this->container);

        self::assertInstanceOf(NullLogger::class, $this->container->getSingleton(NullLogger::class));
    }
}

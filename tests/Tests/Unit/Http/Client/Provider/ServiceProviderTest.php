<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Http\Client\Provider;

use GuzzleHttp\Client;
use PHPUnit\Framework\MockObject\Exception;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Http\Client\Data\Contract\HttpClientConfigContract;
use Valkyrja\Http\Client\Data\Contract\HttpClientLogConfigContract;
use Valkyrja\Http\Client\Data\HttpClientConfig;
use Valkyrja\Http\Client\Data\HttpClientLogConfig;
use Valkyrja\Http\Client\Manager\Contract\ClientContract;
use Valkyrja\Http\Client\Manager\GuzzleClient;
use Valkyrja\Http\Client\Manager\LogClient;
use Valkyrja\Http\Client\Manager\NullClient;
use Valkyrja\Http\Client\Provider\HttpClientServiceProvider;
use Valkyrja\Http\Message\Enum\RequestMethod;
use Valkyrja\Http\Message\Request\Request;
use Valkyrja\Http\Message\Response\Factory\Contract\ResponseFactoryContract;
use Valkyrja\Http\Message\Uri\Enum\Scheme;
use Valkyrja\Http\Message\Uri\Uri;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Log\Logger\NullLogger;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Tests\Fixtures\Http\Client\Data\HttpClientConfigFixture;

/**
 * Test the ServiceProvider.
 */
final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = HttpClientServiceProvider::class;

    public function testExpectedPublishers(): void
    {
        self::assertArrayHasKey(HttpClientConfigContract::class, new HttpClientServiceProvider()->publishers());
        self::assertArrayHasKey(HttpClientLogConfigContract::class, new HttpClientServiceProvider()->publishers());
        self::assertArrayHasKey(ClientContract::class, new HttpClientServiceProvider()->publishers());
        self::assertArrayHasKey(GuzzleClient::class, new HttpClientServiceProvider()->publishers());
        self::assertArrayHasKey(Client::class, new HttpClientServiceProvider()->publishers());
        self::assertArrayHasKey(LogClient::class, new HttpClientServiceProvider()->publishers());
        self::assertArrayHasKey(NullClient::class, new HttpClientServiceProvider()->publishers());
    }

    public function testPublishConfig(): void
    {
        $callback = new HttpClientServiceProvider()->publishers()[HttpClientConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(HttpClientConfigContract::class, $config = $this->container->getSingleton(HttpClientConfigContract::class));
        self::assertSame(GuzzleClient::class, $config->defaultClient);
    }

    public function testPublishConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new HttpClientConfigFixture());

        $callback = new HttpClientServiceProvider()->publishers()[HttpClientConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(HttpClientConfigContract::class, $config = $this->container->getSingleton(HttpClientConfigContract::class));
        self::assertSame(NullClient::class, $config->defaultClient);
    }

    public function testPublishLogConfig(): void
    {
        $callback = new HttpClientServiceProvider()->publishers()[HttpClientLogConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(HttpClientLogConfig::class, $config = $this->container->getSingleton(HttpClientLogConfigContract::class));
        self::assertSame(LoggerContract::class, $config->httpClientLogLogger);
    }

    public function testPublishLogConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, $appConfig = new HttpClientConfigFixture());

        $callback = new HttpClientServiceProvider()->publishers()[HttpClientLogConfigContract::class];
        $callback($this->container);

        self::assertSame($appConfig, $config = $this->container->getSingleton(HttpClientLogConfigContract::class));
        self::assertSame(NullLogger::class, $config->httpClientLogLogger);
    }

    /**
     * @throws Exception
     */
    public function testPublishClient(): void
    {
        $this->container->setSingleton(HttpClientConfigContract::class, new HttpClientConfig());
        $this->container->setSingleton(GuzzleClient::class, self::createStub(GuzzleClient::class));

        $callback = new HttpClientServiceProvider()->publishers()[ClientContract::class];
        $callback($this->container);

        self::assertInstanceOf(GuzzleClient::class, $this->container->getSingleton(ClientContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishClientWithConfiguredDefault(): void
    {
        $this->container->setSingleton(HttpClientConfigContract::class, new HttpClientConfig(defaultClient: NullClient::class));
        $this->container->setSingleton(NullClient::class, self::createStub(NullClient::class));

        $callback = new HttpClientServiceProvider()->publishers()[ClientContract::class];
        $callback($this->container);

        self::assertInstanceOf(NullClient::class, $this->container->getSingleton(ClientContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishGuzzleClient(): void
    {
        $this->container->setSingleton(Client::class, self::createStub(Client::class));
        $this->container->setSingleton(ResponseFactoryContract::class, self::createStub(ResponseFactoryContract::class));

        $callback = new HttpClientServiceProvider()->publishers()[GuzzleClient::class];
        $callback($this->container);

        self::assertInstanceOf(GuzzleClient::class, $this->container->getSingleton(GuzzleClient::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishLogClient(): void
    {
        // Only the configured logger is bound, so the log call proves the client took it.
        $logger = $this->createMock(NullLogger::class);
        $logger->expects($this->once())->method('info');

        $this->container->setSingleton(HttpClientLogConfigContract::class, new HttpClientLogConfig(httpClientLogLogger: NullLogger::class));
        $this->container->setSingleton(NullLogger::class, $logger);

        $callback = new HttpClientServiceProvider()->publishers()[LogClient::class];
        $callback($this->container);

        self::assertInstanceOf(LogClient::class, $client = $this->container->getSingleton(LogClient::class));

        $client->sendRequest(
            new Request(
                uri: new Uri(scheme: Scheme::HTTPS, host: 'example.com'),
                method: RequestMethod::GET,
            )
        );
    }

    public function testPublishNullClient(): void
    {
        $callback = new HttpClientServiceProvider()->publishers()[NullClient::class];
        $callback($this->container);

        self::assertInstanceOf(NullClient::class, $this->container->getSingleton(NullClient::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishGuzzle(): void
    {
        $callback = new HttpClientServiceProvider()->publishers()[Client::class];
        $callback($this->container);

        self::assertInstanceOf(Client::class, $this->container->getSingleton(Client::class));
    }
}

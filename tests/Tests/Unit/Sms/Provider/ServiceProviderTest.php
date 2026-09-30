<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Sms\Provider;

use PHPUnit\Framework\MockObject\Exception;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Log\Logger\NullLogger;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Sms\Data\Contract\MessageContract;
use Valkyrja\Sms\Data\Contract\SmsConfigContract;
use Valkyrja\Sms\Data\Contract\SmsLogConfigContract;
use Valkyrja\Sms\Data\Contract\SmsVonageConfigContract;
use Valkyrja\Sms\Data\SmsConfig;
use Valkyrja\Sms\Data\SmsLogConfig;
use Valkyrja\Sms\Data\SmsVonageConfig;
use Valkyrja\Sms\Messenger\Contract\MessengerContract;
use Valkyrja\Sms\Messenger\LogMessenger;
use Valkyrja\Sms\Messenger\NullMessenger;
use Valkyrja\Sms\Messenger\VonageMessenger;
use Valkyrja\Sms\Provider\SmsServiceProvider;
use Valkyrja\Tests\Fixtures\Sms\Data\SmsConfigFixture;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;
use Vonage\Client\Credentials\CredentialsInterface;

/**
 * Test the ServiceProvider.
 */
final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = SmsServiceProvider::class;

    public function testExpectedPublishers(): void
    {
        self::assertArrayHasKey(SmsConfigContract::class, new SmsServiceProvider()->publishers());
        self::assertArrayHasKey(SmsVonageConfigContract::class, new SmsServiceProvider()->publishers());
        self::assertArrayHasKey(SmsLogConfigContract::class, new SmsServiceProvider()->publishers());
        self::assertArrayHasKey(MessengerContract::class, new SmsServiceProvider()->publishers());
        self::assertArrayHasKey(VonageMessenger::class, new SmsServiceProvider()->publishers());
        self::assertArrayHasKey(Client::class, new SmsServiceProvider()->publishers());
        self::assertArrayHasKey(CredentialsInterface::class, new SmsServiceProvider()->publishers());
        self::assertArrayHasKey(LogMessenger::class, new SmsServiceProvider()->publishers());
        self::assertArrayHasKey(NullMessenger::class, new SmsServiceProvider()->publishers());
    }

    public function testPublishConfig(): void
    {
        $callback = new SmsServiceProvider()->publishers()[SmsConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(SmsConfigContract::class, $config = $this->container->getSingleton(SmsConfigContract::class));
        self::assertSame(VonageMessenger::class, $config->defaultMessenger);
    }

    public function testPublishConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new SmsConfigFixture());

        $callback = new SmsServiceProvider()->publishers()[SmsConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(SmsConfigContract::class, $config = $this->container->getSingleton(SmsConfigContract::class));
        self::assertSame(NullMessenger::class, $config->defaultMessenger);
    }

    public function testPublishVonageConfig(): void
    {
        $callback = new SmsServiceProvider()->publishers()[SmsVonageConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(SmsVonageConfigContract::class, $config = $this->container->getSingleton(SmsVonageConfigContract::class));
        self::assertSame('vonage-key', $config->vonageKey);
    }

    public function testPublishVonageConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new SmsConfigFixture());

        $callback = new SmsServiceProvider()->publishers()[SmsVonageConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(SmsVonageConfigContract::class, $config = $this->container->getSingleton(SmsVonageConfigContract::class));
        self::assertSame('test-key', $config->vonageKey);
    }

    public function testPublishLogConfig(): void
    {
        $callback = new SmsServiceProvider()->publishers()[SmsLogConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(SmsLogConfig::class, $config = $this->container->getSingleton(SmsLogConfigContract::class));
        self::assertSame(LoggerContract::class, $config->smsLogLogger);
    }

    public function testPublishLogConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, $appConfig = new SmsConfigFixture());

        $callback = new SmsServiceProvider()->publishers()[SmsLogConfigContract::class];
        $callback($this->container);

        self::assertSame($appConfig, $config = $this->container->getSingleton(SmsLogConfigContract::class));
        self::assertSame(NullLogger::class, $config->smsLogLogger);
    }

    /**
     * @throws Exception
     */
    public function testPublishSms(): void
    {
        $this->container->setSingleton(SmsConfigContract::class, new SmsConfig());
        $this->container->setSingleton(VonageMessenger::class, self::createStub(VonageMessenger::class));

        $callback = new SmsServiceProvider()->publishers()[MessengerContract::class];
        $callback($this->container);

        self::assertInstanceOf(VonageMessenger::class, $this->container->getSingleton(MessengerContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishSmsWithConfiguredDefault(): void
    {
        $this->container->setSingleton(SmsConfigContract::class, new SmsConfig(defaultMessenger: NullMessenger::class));
        $this->container->setSingleton(NullMessenger::class, self::createStub(NullMessenger::class));

        $callback = new SmsServiceProvider()->publishers()[MessengerContract::class];
        $callback($this->container);

        self::assertInstanceOf(NullMessenger::class, $this->container->getSingleton(MessengerContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishVonageSms(): void
    {
        $this->container->setSingleton(Client::class, self::createStub(Client::class));

        $callback = new SmsServiceProvider()->publishers()[VonageMessenger::class];
        $callback($this->container);

        self::assertInstanceOf(VonageMessenger::class, $this->container->getSingleton(VonageMessenger::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishVonage(): void
    {
        $this->container->setSingleton(CredentialsInterface::class, new Basic('', ''));

        $callback = new SmsServiceProvider()->publishers()[Client::class];
        $callback($this->container);

        self::assertInstanceOf(Client::class, $this->container->getSingleton(Client::class));
    }

    public function testPublishVonageCredentials(): void
    {
        $this->container->setSingleton(
            SmsVonageConfigContract::class,
            new SmsVonageConfig(vonageKey: 'test-key', vonageSecret: 'test-secret')
        );

        $callback = new SmsServiceProvider()->publishers()[CredentialsInterface::class];
        $callback($this->container);

        self::assertInstanceOf(Basic::class, $this->container->getSingleton(CredentialsInterface::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishLogSms(): void
    {
        // Only the configured logger is bound, so the log call proves the messenger took it.
        $logger = $this->createMock(NullLogger::class);
        $logger->expects($this->atLeastOnce())->method('info');

        $this->container->setSingleton(SmsLogConfigContract::class, new SmsLogConfig(smsLogLogger: NullLogger::class));
        $this->container->setSingleton(NullLogger::class, $logger);

        $callback = new SmsServiceProvider()->publishers()[LogMessenger::class];
        $callback($this->container);

        self::assertInstanceOf(LogMessenger::class, $messenger = $this->container->getSingleton(LogMessenger::class));

        $messenger->send(self::createStub(MessageContract::class));
    }

    public function testPublishNullSms(): void
    {
        $callback = new SmsServiceProvider()->publishers()[NullMessenger::class];
        $callback($this->container);

        self::assertInstanceOf(NullMessenger::class, $this->container->getSingleton(NullMessenger::class));
    }
}

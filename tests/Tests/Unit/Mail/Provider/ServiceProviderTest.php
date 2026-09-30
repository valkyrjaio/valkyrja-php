<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Mail\Provider;

use Mailgun\HttpClient\HttpClientConfigurator;
use Mailgun\Mailgun;
use PHPMailer\PHPMailer\PHPMailer as PHPMailerClient;
use PHPUnit\Framework\MockObject\Exception;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Log\Logger\NullLogger;
use Valkyrja\Mail\Data\Contract\MailConfigContract;
use Valkyrja\Mail\Data\Contract\MailLogConfigContract;
use Valkyrja\Mail\Data\Contract\MailMailgunConfigContract;
use Valkyrja\Mail\Data\Contract\MailPhpMailerConfigContract;
use Valkyrja\Mail\Data\Contract\MessageContract;
use Valkyrja\Mail\Data\MailConfig;
use Valkyrja\Mail\Data\MailLogConfig;
use Valkyrja\Mail\Data\MailMailgunConfig;
use Valkyrja\Mail\Data\MailPhpMailerConfig;
use Valkyrja\Mail\Mailer\Contract\MailerContract;
use Valkyrja\Mail\Mailer\LogMailer;
use Valkyrja\Mail\Mailer\MailgunMailer;
use Valkyrja\Mail\Mailer\NullMailer;
use Valkyrja\Mail\Mailer\PhpMailer;
use Valkyrja\Mail\Provider\MailServiceProvider;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Tests\Fixtures\Mail\Data\MailConfigFixture;

/**
 * Test the ServiceProvider.
 */
final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = MailServiceProvider::class;

    public function testExpectedPublishers(): void
    {
        self::assertArrayHasKey(MailConfigContract::class, new MailServiceProvider()->publishers());
        self::assertArrayHasKey(MailMailgunConfigContract::class, new MailServiceProvider()->publishers());
        self::assertArrayHasKey(MailPhpMailerConfigContract::class, new MailServiceProvider()->publishers());
        self::assertArrayHasKey(MailLogConfigContract::class, new MailServiceProvider()->publishers());
        self::assertArrayHasKey(MailerContract::class, new MailServiceProvider()->publishers());
        self::assertArrayHasKey(MailgunMailer::class, new MailServiceProvider()->publishers());
        self::assertArrayHasKey(Mailgun::class, new MailServiceProvider()->publishers());
        self::assertArrayHasKey(HttpClientConfigurator::class, new MailServiceProvider()->publishers());
        self::assertArrayHasKey(PhpMailer::class, new MailServiceProvider()->publishers());
        self::assertArrayHasKey(PHPMailerClient::class, new MailServiceProvider()->publishers());
        self::assertArrayHasKey(LogMailer::class, new MailServiceProvider()->publishers());
        self::assertArrayHasKey(NullMailer::class, new MailServiceProvider()->publishers());
    }

    public function testPublishConfig(): void
    {
        $callback = new MailServiceProvider()->publishers()[MailConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(MailConfigContract::class, $config = $this->container->getSingleton(MailConfigContract::class));
        self::assertSame(MailgunMailer::class, $config->defaultMailer);
    }

    public function testPublishConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new MailConfigFixture());

        $callback = new MailServiceProvider()->publishers()[MailConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(MailConfigContract::class, $config = $this->container->getSingleton(MailConfigContract::class));
        self::assertSame(NullMailer::class, $config->defaultMailer);
    }

    public function testPublishMailgunConfig(): void
    {
        $callback = new MailServiceProvider()->publishers()[MailMailgunConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(MailMailgunConfigContract::class, $config = $this->container->getSingleton(MailMailgunConfigContract::class));
        self::assertSame('domain', $config->mailgunDomain);
    }

    public function testPublishMailgunConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new MailConfigFixture());

        $callback = new MailServiceProvider()->publishers()[MailMailgunConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(MailMailgunConfigContract::class, $config = $this->container->getSingleton(MailMailgunConfigContract::class));
        self::assertSame('test-domain', $config->mailgunDomain);
    }

    public function testPublishPhpMailerConfig(): void
    {
        $callback = new MailServiceProvider()->publishers()[MailPhpMailerConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(MailPhpMailerConfigContract::class, $config = $this->container->getSingleton(MailPhpMailerConfigContract::class));
        self::assertSame('host', $config->phpMailerHost);
    }

    public function testPublishPhpMailerConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new MailConfigFixture());

        $callback = new MailServiceProvider()->publishers()[MailPhpMailerConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(MailPhpMailerConfigContract::class, $config = $this->container->getSingleton(MailPhpMailerConfigContract::class));
        self::assertSame('test-host', $config->phpMailerHost);
    }

    public function testPublishLogConfig(): void
    {
        $callback = new MailServiceProvider()->publishers()[MailLogConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(MailLogConfig::class, $config = $this->container->getSingleton(MailLogConfigContract::class));
        self::assertSame(LoggerContract::class, $config->mailLogLogger);
    }

    public function testPublishLogConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, $appConfig = new MailConfigFixture());

        $callback = new MailServiceProvider()->publishers()[MailLogConfigContract::class];
        $callback($this->container);

        self::assertSame($appConfig, $config = $this->container->getSingleton(MailLogConfigContract::class));
        self::assertSame(NullLogger::class, $config->mailLogLogger);
    }

    /**
     * @throws Exception
     */
    public function testPublishMailerWithConfiguredDefault(): void
    {
        $this->container->setSingleton(MailConfigContract::class, new MailConfig(defaultMailer: NullMailer::class));
        $this->container->setSingleton(NullMailer::class, self::createStub(NullMailer::class));

        $callback = new MailServiceProvider()->publishers()[MailerContract::class];
        $callback($this->container);

        self::assertInstanceOf(NullMailer::class, $this->container->getSingleton(MailerContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishMailer(): void
    {
        $this->container->setSingleton(MailConfigContract::class, new MailConfig());
        $this->container->setSingleton(MailgunMailer::class, self::createStub(MailgunMailer::class));

        $callback = new MailServiceProvider()->publishers()[MailerContract::class];
        $callback($this->container);

        self::assertInstanceOf(MailgunMailer::class, $this->container->getSingleton(MailerContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishMailgunMailer(): void
    {
        $this->container->setSingleton(MailMailgunConfigContract::class, new MailMailgunConfig());
        $this->container->setSingleton(Mailgun::class, self::createStub(Mailgun::class));

        $callback = new MailServiceProvider()->publishers()[MailgunMailer::class];
        $callback($this->container);

        self::assertInstanceOf(MailgunMailer::class, $this->container->getSingleton(MailgunMailer::class));
    }

    public function testPublishMailgun(): void
    {
        $this->container->setSingleton(MailMailgunConfigContract::class, new MailMailgunConfig());

        MailServiceProvider::publishMailgunHttpClientConfigurator($this->container);

        $callback = new MailServiceProvider()->publishers()[Mailgun::class];
        $callback($this->container);

        self::assertInstanceOf(Mailgun::class, $this->container->getSingleton(Mailgun::class));
    }

    public function testPublishMailgunHttpClientConfigurator(): void
    {
        $this->container->setSingleton(MailMailgunConfigContract::class, new MailMailgunConfig());

        $callback = new MailServiceProvider()->publishers()[HttpClientConfigurator::class];
        $callback($this->container);

        self::assertInstanceOf(HttpClientConfigurator::class, $this->container->getSingleton(HttpClientConfigurator::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishPhpMailer(): void
    {
        $this->container->setSingleton(PHPMailerClient::class, self::createStub(PHPMailerClient::class));

        $callback = new MailServiceProvider()->publishers()[PhpMailer::class];
        $callback($this->container);

        self::assertInstanceOf(PhpMailer::class, $this->container->getSingleton(PhpMailer::class));
    }

    public function testPublishPhpMailerClient(): void
    {
        $this->container->setSingleton(MailPhpMailerConfigContract::class, new MailPhpMailerConfig());

        $callback = new MailServiceProvider()->publishers()[PHPMailerClient::class];
        $callback($this->container);

        $mailer = $this->container->getSingleton(PHPMailerClient::class);

        self::assertInstanceOf(PHPMailerClient::class, $mailer);
        // Debug mode is off by default, so verbose SMTP output stays disabled.
        self::assertSame(0, $mailer->SMTPDebug);
    }

    /**
     * With the application in debug mode the mailer is given verbose SMTP output.
     *
     * @throws Exception
     */
    public function testPublishPhpMailerClientInDebugMode(): void
    {
        $app = self::createStub(ApplicationContract::class);
        $app->method('getDebugMode')->willReturn(true);

        $this->container->setSingleton(ApplicationContract::class, $app);
        $this->container->setSingleton(
            MailPhpMailerConfigContract::class,
            new MailPhpMailerConfig(phpMailerHost: 'test-host', phpMailerPort: 587)
        );

        $callback = new MailServiceProvider()->publishers()[PHPMailerClient::class];
        $callback($this->container);

        $mailer = $this->container->getSingleton(PHPMailerClient::class);

        self::assertInstanceOf(PHPMailerClient::class, $mailer);
        self::assertSame(2, $mailer->SMTPDebug);
    }

    /**
     * @throws Exception
     */
    public function testPublishLogMailer(): void
    {
        // Only the configured logger is bound, so the log call proves the mailer took it.
        $logger = $this->createMock(NullLogger::class);
        $logger->expects($this->atLeastOnce())->method('info');

        $this->container->setSingleton(MailLogConfigContract::class, new MailLogConfig(mailLogLogger: NullLogger::class));
        $this->container->setSingleton(NullLogger::class, $logger);

        $callback = new MailServiceProvider()->publishers()[LogMailer::class];
        $callback($this->container);

        self::assertInstanceOf(LogMailer::class, $mailer = $this->container->getSingleton(LogMailer::class));

        $mailer->send(self::createStub(MessageContract::class));
    }

    public function testPublishNullMailer(): void
    {
        $callback = new MailServiceProvider()->publishers()[NullMailer::class];
        $callback($this->container);

        self::assertInstanceOf(NullMailer::class, $this->container->getSingleton(NullMailer::class));
    }
}

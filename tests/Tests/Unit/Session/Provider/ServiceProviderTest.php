<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Session\Provider;

use Override;
use PHPUnit\Framework\MockObject\Exception;
use Valkyrja\Application\Data\Config;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Cache\Manager\Contract\CacheContract;
use Valkyrja\Cli\Interaction\Input\Contract\InputContract;
use Valkyrja\Crypt\Manager\Contract\CryptContract;
use Valkyrja\Http\Message\Enum\SameSite;
use Valkyrja\Http\Message\Request\Contract\ServerRequestContract;
use Valkyrja\Jwt\Manager\Contract\JwtContract;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Log\Logger\NullLogger;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Session\Data\Contract\SessionConfigContract;
use Valkyrja\Session\Data\Contract\SessionJwtConfigContract;
use Valkyrja\Session\Data\Contract\SessionLogConfigContract;
use Valkyrja\Session\Data\Contract\SessionPhpConfigContract;
use Valkyrja\Session\Data\Contract\SessionTokenConfigContract;
use Valkyrja\Session\Data\SessionConfig;
use Valkyrja\Session\Data\SessionJwtConfig;
use Valkyrja\Session\Data\SessionLogConfig;
use Valkyrja\Session\Data\SessionPhpConfig;
use Valkyrja\Session\Data\SessionTokenConfig;
use Valkyrja\Session\Manager\CacheSession;
use Valkyrja\Session\Manager\Contract\SessionContract;
use Valkyrja\Session\Manager\Cookie\CookieSession;
use Valkyrja\Session\Manager\Cookie\EncryptedCookieSession;
use Valkyrja\Session\Manager\Jwt\Cli\EncryptedOptionJwtSession;
use Valkyrja\Session\Manager\Jwt\Cli\OptionJwtSession;
use Valkyrja\Session\Manager\Jwt\Http\EncryptedHeaderJwtSession;
use Valkyrja\Session\Manager\Jwt\Http\HeaderJwtSession;
use Valkyrja\Session\Manager\LogSession;
use Valkyrja\Session\Manager\NullSession;
use Valkyrja\Session\Manager\PhpSession;
use Valkyrja\Session\Manager\Token\Cli\EncryptedOptionTokenSession;
use Valkyrja\Session\Manager\Token\Cli\OptionTokenSession;
use Valkyrja\Session\Manager\Token\Http\EncryptedHeaderTokenSession;
use Valkyrja\Session\Manager\Token\Http\HeaderTokenSession;
use Valkyrja\Session\Provider\SessionServiceProvider;
use Valkyrja\Tests\Fixtures\Session\Data\SessionConfigFixture;

/**
 * Test the ServiceProvider.
 */
final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = SessionServiceProvider::class;

    /**
     * Every session manager reads the session config, so bind the framework
     * defaults for each publisher test.
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->container->setSingleton(SessionConfigContract::class, new SessionConfig());
        $this->container->setSingleton(SessionPhpConfigContract::class, new SessionPhpConfig());
        $this->container->setSingleton(SessionJwtConfigContract::class, new SessionJwtConfig());
        $this->container->setSingleton(SessionTokenConfigContract::class, new SessionTokenConfig());
    }

    public function testExpectedPublishers(): void
    {
        self::assertArrayHasKey(SessionConfigContract::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(SessionPhpConfigContract::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(SessionJwtConfigContract::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(SessionTokenConfigContract::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(SessionLogConfigContract::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(SessionContract::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(PhpSession::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(NullSession::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(CacheSession::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(CookieSession::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(EncryptedCookieSession::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(OptionJwtSession::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(EncryptedOptionJwtSession::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(HeaderJwtSession::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(EncryptedHeaderJwtSession::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(OptionTokenSession::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(EncryptedOptionTokenSession::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(HeaderTokenSession::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(EncryptedHeaderTokenSession::class, new SessionServiceProvider()->publishers());
        self::assertArrayHasKey(LogSession::class, new SessionServiceProvider()->publishers());
    }

    public function testPublishConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new Config());

        $callback = new SessionServiceProvider()->publishers()[SessionConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(SessionConfigContract::class, $config = $this->container->getSingleton(SessionConfigContract::class));
        self::assertSame(PhpSession::class, $config->defaultSession);
    }

    public function testPublishConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new SessionConfigFixture());

        $callback = new SessionServiceProvider()->publishers()[SessionConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(SessionConfigContract::class, $config = $this->container->getSingleton(SessionConfigContract::class));
        self::assertSame(NullSession::class, $config->defaultSession);
        self::assertSame('test-id', $config->sessionId);
    }

    public function testPublishCookieConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new Config());

        $callback = new SessionServiceProvider()->publishers()[SessionPhpConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(SessionPhpConfigContract::class, $config = $this->container->getSingleton(SessionPhpConfigContract::class));
        self::assertSame('/', $config->phpCookiePath);
    }

    public function testPublishCookieConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new SessionConfigFixture());

        $callback = new SessionServiceProvider()->publishers()[SessionPhpConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(SessionPhpConfigContract::class, $config = $this->container->getSingleton(SessionPhpConfigContract::class));
        self::assertSame('/test', $config->phpCookiePath);
        self::assertSame(SameSite::STRICT, $config->phpCookieSameSite);
    }

    public function testPublishJwtConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new Config());

        $callback = new SessionServiceProvider()->publishers()[SessionJwtConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(SessionJwtConfigContract::class, $config = $this->container->getSingleton(SessionJwtConfigContract::class));
        self::assertNull($config->jwtOptionName);
    }

    public function testPublishJwtConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new SessionConfigFixture());

        $callback = new SessionServiceProvider()->publishers()[SessionJwtConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(SessionJwtConfigContract::class, $config = $this->container->getSingleton(SessionJwtConfigContract::class));
        self::assertSame('test-jwt-option', $config->jwtOptionName);
    }

    public function testPublishTokenConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new Config());

        $callback = new SessionServiceProvider()->publishers()[SessionTokenConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(SessionTokenConfigContract::class, $config = $this->container->getSingleton(SessionTokenConfigContract::class));
        self::assertNull($config->tokenOptionName);
    }

    public function testPublishTokenConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new SessionConfigFixture());

        $callback = new SessionServiceProvider()->publishers()[SessionTokenConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(SessionTokenConfigContract::class, $config = $this->container->getSingleton(SessionTokenConfigContract::class));
        self::assertSame('test-token-option', $config->tokenOptionName);
    }

    public function testPublishLogConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new Config());

        $callback = new SessionServiceProvider()->publishers()[SessionLogConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(SessionLogConfig::class, $config = $this->container->getSingleton(SessionLogConfigContract::class));
        self::assertSame(LoggerContract::class, $config->sessionLogLogger);
    }

    public function testPublishLogConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, $appConfig = new SessionConfigFixture());

        $callback = new SessionServiceProvider()->publishers()[SessionLogConfigContract::class];
        $callback($this->container);

        self::assertSame($appConfig, $config = $this->container->getSingleton(SessionLogConfigContract::class));
        self::assertSame(NullLogger::class, $config->sessionLogLogger);
    }

    /**
     * @throws Exception
     */
    public function testPublishSession(): void
    {
        $this->container->setSingleton(PhpSession::class, self::createStub(PhpSession::class));

        $callback = new SessionServiceProvider()->publishers()[SessionContract::class];
        $callback($this->container);

        self::assertInstanceOf(PhpSession::class, $this->container->getSingleton(SessionContract::class));
    }

    public function testPublishPhpSession(): void
    {
        $callback = new SessionServiceProvider()->publishers()[PhpSession::class];
        $callback($this->container);

        self::assertInstanceOf(PhpSession::class, $this->container->getSingleton(PhpSession::class));
    }

    public function testPublishNullSession(): void
    {
        $callback = new SessionServiceProvider()->publishers()[NullSession::class];
        $callback($this->container);

        self::assertInstanceOf(NullSession::class, $this->container->getSingleton(NullSession::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishCacheSession(): void
    {
        $this->container->setSingleton(CacheContract::class, self::createStub(CacheContract::class));

        $callback = new SessionServiceProvider()->publishers()[CacheSession::class];
        $callback($this->container);

        self::assertInstanceOf(CacheSession::class, $this->container->getSingleton(CacheSession::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishCookieSession(): void
    {
        $this->container->setSingleton(ServerRequestContract::class, self::createStub(ServerRequestContract::class));

        $callback = new SessionServiceProvider()->publishers()[CookieSession::class];
        $callback($this->container);

        self::assertInstanceOf(CookieSession::class, $this->container->getSingleton(CookieSession::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishEncryptedCookieSession(): void
    {
        $this->container->setSingleton(CryptContract::class, self::createStub(CryptContract::class));
        $this->container->setSingleton(ServerRequestContract::class, self::createStub(ServerRequestContract::class));

        $callback = new SessionServiceProvider()->publishers()[EncryptedCookieSession::class];
        $callback($this->container);

        self::assertInstanceOf(EncryptedCookieSession::class, $this->container->getSingleton(EncryptedCookieSession::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishOptionJwtSession(): void
    {
        $this->container->setSingleton(JwtContract::class, self::createStub(JwtContract::class));
        $this->container->setSingleton(InputContract::class, self::createStub(InputContract::class));

        $callback = new SessionServiceProvider()->publishers()[OptionJwtSession::class];
        $callback($this->container);

        self::assertInstanceOf(OptionJwtSession::class, $this->container->getSingleton(OptionJwtSession::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishEncryptedOptionJwtSession(): void
    {
        $this->container->setSingleton(CryptContract::class, self::createStub(CryptContract::class));
        $this->container->setSingleton(JwtContract::class, self::createStub(JwtContract::class));
        $this->container->setSingleton(InputContract::class, self::createStub(InputContract::class));

        $callback = new SessionServiceProvider()->publishers()[EncryptedOptionJwtSession::class];
        $callback($this->container);

        self::assertInstanceOf(EncryptedOptionJwtSession::class, $this->container->getSingleton(EncryptedOptionJwtSession::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishHeaderJwtSession(): void
    {
        $this->container->setSingleton(JwtContract::class, self::createStub(JwtContract::class));
        $this->container->setSingleton(ServerRequestContract::class, self::createStub(ServerRequestContract::class));

        $callback = new SessionServiceProvider()->publishers()[HeaderJwtSession::class];
        $callback($this->container);

        self::assertInstanceOf(HeaderJwtSession::class, $this->container->getSingleton(HeaderJwtSession::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishEncryptedHeaderJwtSession(): void
    {
        $this->container->setSingleton(CryptContract::class, self::createStub(CryptContract::class));
        $this->container->setSingleton(JwtContract::class, self::createStub(JwtContract::class));
        $this->container->setSingleton(ServerRequestContract::class, self::createStub(ServerRequestContract::class));

        $callback = new SessionServiceProvider()->publishers()[EncryptedHeaderJwtSession::class];
        $callback($this->container);

        self::assertInstanceOf(EncryptedHeaderJwtSession::class, $this->container->getSingleton(EncryptedHeaderJwtSession::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishOptionTokenSession(): void
    {
        $this->container->setSingleton(InputContract::class, self::createStub(InputContract::class));

        $callback = new SessionServiceProvider()->publishers()[OptionTokenSession::class];
        $callback($this->container);

        self::assertInstanceOf(OptionTokenSession::class, $this->container->getSingleton(OptionTokenSession::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishEncryptedOptionTokenSession(): void
    {
        $this->container->setSingleton(CryptContract::class, self::createStub(CryptContract::class));
        $this->container->setSingleton(InputContract::class, self::createStub(InputContract::class));

        $callback = new SessionServiceProvider()->publishers()[EncryptedOptionTokenSession::class];
        $callback($this->container);

        self::assertInstanceOf(EncryptedOptionTokenSession::class, $this->container->getSingleton(EncryptedOptionTokenSession::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishHeaderTokenSession(): void
    {
        $this->container->setSingleton(ServerRequestContract::class, self::createStub(ServerRequestContract::class));

        $callback = new SessionServiceProvider()->publishers()[HeaderTokenSession::class];
        $callback($this->container);

        self::assertInstanceOf(HeaderTokenSession::class, $this->container->getSingleton(HeaderTokenSession::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishEncryptedHeaderTokenSession(): void
    {
        $this->container->setSingleton(CryptContract::class, self::createStub(CryptContract::class));
        $this->container->setSingleton(ServerRequestContract::class, self::createStub(ServerRequestContract::class));

        $callback = new SessionServiceProvider()->publishers()[EncryptedHeaderTokenSession::class];
        $callback($this->container);

        self::assertInstanceOf(EncryptedHeaderTokenSession::class, $this->container->getSingleton(EncryptedHeaderTokenSession::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishLogSession(): void
    {
        // Only the configured logger is bound, so the log call proves the session took it.
        $logger = $this->createMock(NullLogger::class);
        $logger->expects($this->once())->method('info');

        $this->container->setSingleton(SessionLogConfigContract::class, new SessionLogConfig(sessionLogLogger: NullLogger::class));
        $this->container->setSingleton(NullLogger::class, $logger);

        $callback = new SessionServiceProvider()->publishers()[LogSession::class];
        $callback($this->container);

        self::assertInstanceOf(LogSession::class, $session = $this->container->getSingleton(LogSession::class));

        $session->set('key', 'value');
    }
}

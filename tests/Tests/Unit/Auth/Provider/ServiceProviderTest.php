<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Auth\Provider;

use PHPUnit\Framework\MockObject\Exception;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Auth\Authenticator\Contract\AuthenticatorContract;
use Valkyrja\Auth\Authenticator\SessionAuthenticator;
use Valkyrja\Auth\Data\AuthConfig;
use Valkyrja\Auth\Data\AuthSessionConfig;
use Valkyrja\Auth\Data\Contract\AuthConfigContract;
use Valkyrja\Auth\Data\Contract\AuthSessionConfigContract;
use Valkyrja\Auth\Hasher\Contract\PasswordHasherContract;
use Valkyrja\Auth\Hasher\PhpPasswordHasher;
use Valkyrja\Auth\Provider\AuthServiceProvider;
use Valkyrja\Auth\Store\Contract\StoreContract;
use Valkyrja\Auth\Store\InMemoryStore;
use Valkyrja\Auth\Store\NullStore;
use Valkyrja\Auth\Store\OrmStore;
use Valkyrja\Orm\Manager\Contract\ManagerContract;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Session\Manager\Contract\SessionContract;
use Valkyrja\Tests\Fixtures\Auth\Data\AuthConfigFixture;

/**
 * Test the ServiceProvider.
 */
final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = AuthServiceProvider::class;

    public function testExpectedPublishers(): void
    {
        self::assertArrayHasKey(AuthConfigContract::class, new AuthServiceProvider()->publishers());
        self::assertArrayHasKey(AuthSessionConfigContract::class, new AuthServiceProvider()->publishers());
        self::assertArrayHasKey(AuthenticatorContract::class, new AuthServiceProvider()->publishers());
        self::assertArrayHasKey(SessionAuthenticator::class, new AuthServiceProvider()->publishers());
        self::assertArrayHasKey(StoreContract::class, new AuthServiceProvider()->publishers());
        self::assertArrayHasKey(OrmStore::class, new AuthServiceProvider()->publishers());
        self::assertArrayHasKey(InMemoryStore::class, new AuthServiceProvider()->publishers());
        self::assertArrayHasKey(NullStore::class, new AuthServiceProvider()->publishers());
        self::assertArrayHasKey(PasswordHasherContract::class, new AuthServiceProvider()->publishers());
    }

    public function testPublishConfig(): void
    {
        $callback = new AuthServiceProvider()->publishers()[AuthConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(AuthConfigContract::class, $config = $this->container->getSingleton(AuthConfigContract::class));
        self::assertSame(SessionAuthenticator::class, $config->defaultAuthenticator);
        self::assertSame(OrmStore::class, $config->defaultStore);
    }

    public function testPublishConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new AuthConfigFixture());

        $callback = new AuthServiceProvider()->publishers()[AuthConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(AuthConfigContract::class, $config = $this->container->getSingleton(AuthConfigContract::class));
        self::assertSame(NullStore::class, $config->defaultStore);
    }

    public function testPublishSessionConfig(): void
    {
        $callback = new AuthServiceProvider()->publishers()[AuthSessionConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(AuthSessionConfig::class, $config = $this->container->getSingleton(AuthSessionConfigContract::class));
        self::assertSame('auth.users', $config->sessionItemId);
    }

    public function testPublishSessionConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, $appConfig = new AuthConfigFixture());

        $callback = new AuthServiceProvider()->publishers()[AuthSessionConfigContract::class];
        $callback($this->container);

        self::assertSame($appConfig, $config = $this->container->getSingleton(AuthSessionConfigContract::class));
        self::assertSame('auth.fixture', $config->sessionItemId);
    }

    /**
     * @throws Exception
     */
    public function testPublishAuthenticator(): void
    {
        $this->container->setSingleton(AuthConfigContract::class, new AuthConfig());
        $this->container->setSingleton(SessionAuthenticator::class, self::createStub(SessionAuthenticator::class));

        $callback = new AuthServiceProvider()->publishers()[AuthenticatorContract::class];
        $callback($this->container);

        self::assertInstanceOf(SessionAuthenticator::class, $this->container->getSingleton(AuthenticatorContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishSessionAuthenticator(): void
    {
        // The authenticator reads the session item as it is built, so the item id proves which config it took.
        $session = $this->createMock(SessionContract::class);
        $session->expects($this->once())
            ->method('has')
            ->with('auth.custom')
            ->willReturn(false);

        $this->container->setSingleton(AuthConfigContract::class, new AuthConfig());
        $this->container->setSingleton(AuthSessionConfigContract::class, new AuthSessionConfig(sessionItemId: 'auth.custom'));
        $this->container->setSingleton(SessionContract::class, $session);
        $this->container->setSingleton(StoreContract::class, self::createStub(StoreContract::class));
        $this->container->setSingleton(PasswordHasherContract::class, self::createStub(PasswordHasherContract::class));

        $callback = new AuthServiceProvider()->publishers()[SessionAuthenticator::class];
        $callback($this->container);

        self::assertInstanceOf(SessionAuthenticator::class, $this->container->getSingleton(SessionAuthenticator::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishStore(): void
    {
        $this->container->setSingleton(AuthConfigContract::class, new AuthConfig());
        $this->container->setSingleton(OrmStore::class, self::createStub(OrmStore::class));

        $callback = new AuthServiceProvider()->publishers()[StoreContract::class];
        $callback($this->container);

        self::assertInstanceOf(OrmStore::class, $this->container->getSingleton(StoreContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishStoreWithConfiguredDefault(): void
    {
        $this->container->setSingleton(AuthConfigContract::class, new AuthConfig(defaultStore: NullStore::class));
        $this->container->setSingleton(NullStore::class, self::createStub(NullStore::class));

        $callback = new AuthServiceProvider()->publishers()[StoreContract::class];
        $callback($this->container);

        self::assertInstanceOf(NullStore::class, $this->container->getSingleton(StoreContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishOrmStore(): void
    {
        $this->container->setSingleton(ManagerContract::class, self::createStub(ManagerContract::class));

        $callback = new AuthServiceProvider()->publishers()[OrmStore::class];
        $callback($this->container);

        self::assertInstanceOf(OrmStore::class, $this->container->getSingleton(OrmStore::class));
    }

    public function testPublishInMemoryStore(): void
    {
        $callback = new AuthServiceProvider()->publishers()[InMemoryStore::class];
        $callback($this->container);

        self::assertInstanceOf(InMemoryStore::class, $this->container->getSingleton(InMemoryStore::class));
    }

    public function testPublishNullStore(): void
    {
        $callback = new AuthServiceProvider()->publishers()[NullStore::class];
        $callback($this->container);

        self::assertInstanceOf(NullStore::class, $this->container->getSingleton(NullStore::class));
    }

    public function testPublishPasswordHasher(): void
    {
        $callback = new AuthServiceProvider()->publishers()[PasswordHasherContract::class];
        $callback($this->container);

        self::assertInstanceOf(PhpPasswordHasher::class, $this->container->getSingleton(PasswordHasherContract::class));
    }
}

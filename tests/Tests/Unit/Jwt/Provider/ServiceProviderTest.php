<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Jwt\Provider;

use PHPUnit\Framework\MockObject\Exception;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Jwt\Data\Contract\JwtConfigContract;
use Valkyrja\Jwt\Data\Contract\JwtEdDsaConfigContract;
use Valkyrja\Jwt\Data\Contract\JwtFirebaseConfigContract;
use Valkyrja\Jwt\Data\Contract\JwtHsConfigContract;
use Valkyrja\Jwt\Data\Contract\JwtRsConfigContract;
use Valkyrja\Jwt\Data\JwtConfig;
use Valkyrja\Jwt\Data\JwtEdDsaConfig;
use Valkyrja\Jwt\Data\JwtFirebaseConfig;
use Valkyrja\Jwt\Data\JwtHsConfig;
use Valkyrja\Jwt\Data\JwtRsConfig;
use Valkyrja\Jwt\Enum\Algorithm;
use Valkyrja\Jwt\Manager\Contract\JwtContract;
use Valkyrja\Jwt\Manager\FirebaseJwt;
use Valkyrja\Jwt\Manager\NullJwt;
use Valkyrja\Jwt\Provider\JwtServiceProvider;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;
use Valkyrja\Tests\Fixtures\Jwt\Data\JwtConfigFixture;

/**
 * Test the ServiceProvider.
 */
final class ServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = JwtServiceProvider::class;

    public function testExpectedPublishers(): void
    {
        self::assertArrayHasKey(JwtConfigContract::class, new JwtServiceProvider()->publishers());
        self::assertArrayHasKey(JwtHsConfigContract::class, new JwtServiceProvider()->publishers());
        self::assertArrayHasKey(JwtRsConfigContract::class, new JwtServiceProvider()->publishers());
        self::assertArrayHasKey(JwtEdDsaConfigContract::class, new JwtServiceProvider()->publishers());
        self::assertArrayHasKey(JwtFirebaseConfigContract::class, new JwtServiceProvider()->publishers());
        self::assertArrayHasKey(JwtContract::class, new JwtServiceProvider()->publishers());
        self::assertArrayHasKey(FirebaseJwt::class, new JwtServiceProvider()->publishers());
        self::assertArrayHasKey(NullJwt::class, new JwtServiceProvider()->publishers());
    }

    public function testPublishConfig(): void
    {
        $callback = new JwtServiceProvider()->publishers()[JwtConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(JwtConfigContract::class, $config = $this->container->getSingleton(JwtConfigContract::class));
        self::assertSame(FirebaseJwt::class, $config->defaultJwt);
    }

    public function testPublishConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new JwtConfigFixture());

        $callback = new JwtServiceProvider()->publishers()[JwtConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(JwtConfigContract::class, $config = $this->container->getSingleton(JwtConfigContract::class));
        self::assertSame(NullJwt::class, $config->defaultJwt);
    }

    public function testPublishFirebaseConfig(): void
    {
        $callback = new JwtServiceProvider()->publishers()[JwtFirebaseConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(JwtFirebaseConfig::class, $config = $this->container->getSingleton(JwtFirebaseConfigContract::class));
        self::assertSame(Algorithm::HS256, $config->firebaseAlgorithm);
    }

    public function testPublishFirebaseConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, $appConfig = new JwtConfigFixture(firebaseAlgorithm: Algorithm::RS256));

        $callback = new JwtServiceProvider()->publishers()[JwtFirebaseConfigContract::class];
        $callback($this->container);

        self::assertSame($appConfig, $config = $this->container->getSingleton(JwtFirebaseConfigContract::class));
        self::assertSame(Algorithm::RS256, $config->firebaseAlgorithm);
    }

    public function testPublishHsConfig(): void
    {
        $callback = new JwtServiceProvider()->publishers()[JwtHsConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(JwtHsConfigContract::class, $config = $this->container->getSingleton(JwtHsConfigContract::class));
        self::assertSame('key', $config->hsKey);
    }

    public function testPublishHsConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new JwtConfigFixture());

        $callback = new JwtServiceProvider()->publishers()[JwtHsConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(JwtHsConfigContract::class, $config = $this->container->getSingleton(JwtHsConfigContract::class));
        self::assertSame('test-key', $config->hsKey);
    }

    public function testPublishRsConfig(): void
    {
        $callback = new JwtServiceProvider()->publishers()[JwtRsConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(JwtRsConfigContract::class, $config = $this->container->getSingleton(JwtRsConfigContract::class));
        self::assertSame('private-key', $config->rsPrivateKey);
    }

    public function testPublishRsConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new JwtConfigFixture());

        $callback = new JwtServiceProvider()->publishers()[JwtRsConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(JwtRsConfigContract::class, $config = $this->container->getSingleton(JwtRsConfigContract::class));
        self::assertSame('test-rs-private', $config->rsPrivateKey);
    }

    public function testPublishEdDsaConfig(): void
    {
        $callback = new JwtServiceProvider()->publishers()[JwtEdDsaConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(JwtEdDsaConfigContract::class, $config = $this->container->getSingleton(JwtEdDsaConfigContract::class));
        self::assertSame('private-key', $config->edDsaPrivateKey);
    }

    public function testPublishEdDsaConfigWithApplicationConfig(): void
    {
        $this->container->setSingleton(ConfigContract::class, new JwtConfigFixture());

        $callback = new JwtServiceProvider()->publishers()[JwtEdDsaConfigContract::class];
        $callback($this->container);

        self::assertInstanceOf(JwtEdDsaConfigContract::class, $config = $this->container->getSingleton(JwtEdDsaConfigContract::class));
        self::assertSame('test-eddsa-private', $config->edDsaPrivateKey);
    }

    /**
     * @throws Exception
     */
    public function testPublishJwt(): void
    {
        $this->container->setSingleton(JwtConfigContract::class, new JwtConfig());
        $this->container->setSingleton(FirebaseJwt::class, self::createStub(FirebaseJwt::class));

        $callback = new JwtServiceProvider()->publishers()[JwtContract::class];
        $callback($this->container);

        self::assertInstanceOf(FirebaseJwt::class, $this->container->getSingleton(JwtContract::class));
    }

    /**
     * @throws Exception
     */
    public function testPublishJwtWithConfiguredDefault(): void
    {
        $this->container->setSingleton(JwtConfigContract::class, new JwtConfig(defaultJwt: NullJwt::class));
        $this->container->setSingleton(NullJwt::class, self::createStub(NullJwt::class));

        $callback = new JwtServiceProvider()->publishers()[JwtContract::class];
        $callback($this->container);

        self::assertInstanceOf(NullJwt::class, $this->container->getSingleton(JwtContract::class));
    }

    public function testPublishFirebaseJwt(): void
    {
        $this->container->setSingleton(JwtFirebaseConfigContract::class, new JwtFirebaseConfig());
        $this->container->setSingleton(JwtHsConfigContract::class, new JwtHsConfig());

        $callback = new JwtServiceProvider()->publishers()[FirebaseJwt::class];
        $callback($this->container);

        self::assertInstanceOf(FirebaseJwt::class, $this->container->getSingleton(FirebaseJwt::class));
    }

    public function testPublishFirebaseJwtRsAlgorithm(): void
    {
        $this->container->setSingleton(JwtFirebaseConfigContract::class, new JwtFirebaseConfig(firebaseAlgorithm: Algorithm::RS256));
        $this->container->setSingleton(JwtRsConfigContract::class, new JwtRsConfig());

        $callback = new JwtServiceProvider()->publishers()[FirebaseJwt::class];
        $callback($this->container);

        self::assertInstanceOf(FirebaseJwt::class, $this->container->getSingleton(FirebaseJwt::class));
    }

    public function testPublishFirebaseJwtEdDSAAlgorithm(): void
    {
        $this->container->setSingleton(JwtFirebaseConfigContract::class, new JwtFirebaseConfig(firebaseAlgorithm: Algorithm::EdDSA));
        $this->container->setSingleton(JwtEdDsaConfigContract::class, new JwtEdDsaConfig());

        $callback = new JwtServiceProvider()->publishers()[FirebaseJwt::class];
        $callback($this->container);

        self::assertInstanceOf(FirebaseJwt::class, $this->container->getSingleton(FirebaseJwt::class));
    }

    public function testPublishFirebaseJwtDefault(): void
    {
        $this->container->setSingleton(JwtFirebaseConfigContract::class, new JwtFirebaseConfig(firebaseAlgorithm: Algorithm::PS256));

        $callback = new JwtServiceProvider()->publishers()[FirebaseJwt::class];
        $callback($this->container);

        self::assertInstanceOf(FirebaseJwt::class, $this->container->getSingleton(FirebaseJwt::class));
    }

    public function testPublishNullJwt(): void
    {
        $callback = new JwtServiceProvider()->publishers()[NullJwt::class];
        $callback($this->container);

        self::assertInstanceOf(NullJwt::class, $this->container->getSingleton(NullJwt::class));
    }
}

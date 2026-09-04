<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Container\Manager;

use Throwable;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Throwable\Exception\Abstract\ContainerInvalidArgumentException;
use Valkyrja\Container\Throwable\Exception\ContainerCyclicAliasException;
use Valkyrja\Container\Throwable\Exception\ContainerInvalidReferenceException;
use Valkyrja\Tests\Fixtures\Container\Provider\ProvidedFixture;
use Valkyrja\Tests\Fixtures\Container\Provider\PublishingProviderFixture;
use Valkyrja\Tests\Fixtures\Container\ServiceFixture;
use Valkyrja\Tests\Fixtures\Container\SingletonFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the container service.
 */
final class ContainerTest extends TestCase
{
    /**
     * The class  to test with.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testBind(): void
    {
        $container = new Container();
        $id        = ServiceFixture::class;

        $container->bind($id, [ServiceFixture::class, 'make']);

        self::assertTrue($container->has($id));
        self::assertTrue($container->isService($id));
        self::assertTrue($container->isPublished($id));

        self::assertFalse($container->isAlias($id));
        self::assertFalse($container->isSingleton($id));

        self::assertInstanceOf($id, $service = $container->get($id));
        // A bound service should return a new instance each time it is retrieved
        self::assertNotSame($service, $container->get($id));
        self::assertNotSame($service, $container->getService($id));
    }

    public function testBindAlias(): void
    {
        $container = $this->container;
        $id        = ServiceFixture::class;
        $alias     = 'alias';

        $container->bind($id, [ServiceFixture::class, 'make']);
        $container->bindAlias($alias, $id);

        self::assertTrue($container->has($alias));
        self::assertTrue($container->isAlias($alias));
        self::assertTrue($container->isPublished($id));
        self::assertFalse($container->isService($alias));
        self::assertFalse($container->isSingleton($id));

        self::assertInstanceOf($id, $service = $container->get($alias));
        // A bound service should return a new instance each time it is retrieved
        self::assertNotSame($service, $container->get($alias));

        self::assertInstanceOf($id, $container->getAliased($alias));
        self::assertNotSame($service, $container->getAliased($alias));
    }

    public function testGetAliasedId(): void
    {
        $container = $this->container;
        $id        = ServiceFixture::class;
        $alias     = 'alias';

        $container->bind($id, [ServiceFixture::class, 'make']);
        $container->bindAlias($alias, $id);

        self::assertSame($id, $container->getAliasedId($alias));
    }

    public function testGetAliasedIdReturnsNullWhenNotAnAlias(): void
    {
        $container = $this->container;

        $container->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);

        self::assertNull($container->getAliasedId(ServiceFixture::class));
        self::assertNull($container->getAliasedId('unknown'));
    }

    public function testGetAliasedIdReturnsOneHopOnly(): void
    {
        $container = $this->container;
        $id        = ServiceFixture::class;

        $container->bind($id, [ServiceFixture::class, 'make']);
        $container->bindAlias('second', $id);
        $container->bindAlias('first', 'second');

        self::assertSame('second', $container->getAliasedId('first'));
    }

    public function testBindAliasRejectsAnAliasOfItself(): void
    {
        $container = $this->container;

        $this->expectException(ContainerCyclicAliasException::class);

        $container->bindAlias(ServiceFixture::class, ServiceFixture::class);
    }

    public function testSetFromDataRejectsACyclicAliasMap(): void
    {
        $container = $this->container;

        $this->expectException(ContainerCyclicAliasException::class);

        // setFromData() is an entry point for aliases, so it validates them too
        $container->setFromData(new ContainerData(aliases: ['first' => 'second', 'second' => 'first']));
    }

    public function testConstructorRejectsACyclicAliasMapAnAliasIsNoPartOf(): void
    {
        $this->expectException(ContainerCyclicAliasException::class);

        // 'third' sits outside the cycle and is swept first, so its walk needs a bound
        new Container(new ContainerData(aliases: [
            'third'  => 'first',
            'first'  => 'second',
            'second' => 'first',
        ]));
    }

    public function testConstructorAcceptsAMapOfAliasesThatDoNotReturn(): void
    {
        $container = new Container(new ContainerData(aliases: [
            'first'  => 'second',
            'second' => ServiceFixture::class,
        ]));

        self::assertSame('second', $container->getAliasedId('first'));
        self::assertSame(ServiceFixture::class, $container->getAliasedId('second'));
    }

    public function testConstructorRejectsACyclicAliasMap(): void
    {
        $this->expectException(ContainerCyclicAliasException::class);

        new Container(new ContainerData(aliases: ['first' => 'second', 'second' => 'first']));
    }

    public function testBindAliasRejectsAChainThatReturnsToTheAlias(): void
    {
        $container = $this->container;

        $container->bindAlias('first', 'second');

        $this->expectException(ContainerCyclicAliasException::class);

        $container->bindAlias('second', 'first');
    }

    public function testBindAliasRejectsALongerChainThatReturnsToTheAlias(): void
    {
        $container = $this->container;

        $container->bindAlias('first', 'second');
        $container->bindAlias('second', 'third');

        $this->expectException(ContainerCyclicAliasException::class);

        $container->bindAlias('third', 'first');
    }

    public function testBindAliasAllowsAChainThatDoesNotReturn(): void
    {
        $container = $this->container;

        $container->bindAlias('first', 'second');
        $container->bindAlias('second', ServiceFixture::class);

        self::assertSame('second', $container->getAliasedId('first'));
        self::assertSame(ServiceFixture::class, $container->getAliasedId('second'));
    }

    public function testBindSingleton(): void
    {
        $container = $this->container;
        $id        = SingletonFixture::class;

        $container->bindSingleton($id, [SingletonFixture::class, 'make']);

        self::assertTrue($container->has($id));
        self::assertTrue($container->isSingleton($id));
        // A singleton is a service when bound
        self::assertTrue($container->isService($id));
        self::assertTrue($container->isPublished($id));

        self::assertFalse($container->isAlias($id));

        self::assertInstanceOf($id, $service = $container->get($id));
        // A bound singleton should return the same instance each time it is retrieved
        self::assertSame($service, $container->get($id));
        self::assertSame($service, $container->getSingleton($id));
    }

    /**
     * A singleton binding whose callable does not produce an object must not be cached as
     * an instance; the resolution yields null and getSingleton() reports the service as
     * not found. Reaching this defensive guard needs a synthetic factory, since a
     * well-behaved one always returns an object.
     */
    public function testGetSingletonWithNonObjectFromBindingThrows(): void
    {
        $container = $this->container;
        $id        = SingletonFixture::class;

        /** @psalm-suppress InvalidArgument, MixedArgumentTypeCoercion */
        $container->bindSingleton($id, static fn (): null => null);

        self::assertTrue($container->isSingletonBinding($id));
        // Nothing was cached as an instance, so the singleton is still unresolved.
        self::assertFalse($container->isSingletonInstance($id));

        $this->expectException(ContainerInvalidReferenceException::class);

        $container->getSingleton($id);
    }

    public function testSetSingleton(): void
    {
        $container = $this->container;
        $id        = SingletonFixture::class;
        $singleton = new SingletonFixture();

        $result = $container->setSingleton($id, $singleton);

        self::assertSame($container, $result);
        self::assertTrue($container->isSingleton($id));
        self::assertSame($singleton, $container->getSingleton($id));
    }

    public function testProvided(): void
    {
        $container = $this->container;

        $container->register(new PublishingProviderFixture());

        self::assertTrue($container->has(ProvidedFixture::class));
    }

    public function testGetNonExistent(): void
    {
        $this->expectException(ContainerInvalidArgumentException::class);

        $this->container->get(ApplicationContract::class);
    }

    public function testGetNonExistentSingleton(): void
    {
        $this->expectException(ContainerInvalidArgumentException::class);

        $this->container->getSingleton(ServiceFixture::class);
    }

    public function testGetNonExistentInvalidSingleton(): void
    {
        $this->expectException(ContainerInvalidArgumentException::class);

        $this->container->getSingleton(self::class);
    }

    public function testGetNonExistentAliased(): void
    {
        $this->expectException(ContainerInvalidArgumentException::class);

        $this->container->getAliased(self::class);
    }

    public function testGetNonExistentService(): void
    {
        $this->expectException(ContainerInvalidArgumentException::class);

        $this->container->getService(ServiceFixture::class);
    }

    public function testGetNonExistentInvalidService(): void
    {
        $this->expectException(ContainerInvalidArgumentException::class);

        $this->container->getService(self::class);
    }

    public function testGetData(): void
    {
        $container = $this->container;

        $container->register(new PublishingProviderFixture());

        self::assertTrue($container->has(ProvidedFixture::class));

        $data = $this->container->getData();

        self::assertSame(
            [
                ProvidedFixture::class => [PublishingProviderFixture::class, 'publishProvided'],
            ],
            $data->callbacks
        );

        self::assertEmpty($data->aliases);
        self::assertEmpty($data->services);
        self::assertEmpty($data->singletons);
    }

    public function testSetFromData(): void
    {
        $container = $this->container;

        $container->register(new PublishingProviderFixture());

        self::assertTrue($container->has(ProvidedFixture::class));

        $data = $this->container->getData();

        $container2 = new Container();

        self::assertFalse($container2->has(ProvidedFixture::class));

        $container2->setFromData($data);

        self::assertTrue($container2->has(ProvidedFixture::class));

        $newData = $container2->getData();

        self::assertSame(
            [
                ProvidedFixture::class => [PublishingProviderFixture::class, 'publishProvided'],
            ],
            $newData->callbacks
        );
    }

    public function testConstructWithData(): void
    {
        $container = $this->container;

        $container->register(new PublishingProviderFixture());

        self::assertTrue($container->has(ProvidedFixture::class));

        $data = $this->container->getData();

        $container2 = new Container($data);

        self::assertTrue($container2->has(ProvidedFixture::class));

        $newData = $container2->getData();

        self::assertSame(
            [
                ProvidedFixture::class => [PublishingProviderFixture::class, 'publishProvided'],
            ],
            $newData->callbacks
        );
    }

    public function testGetThrowsForAnUnboundService(): void
    {
        $this->expectException(ContainerInvalidArgumentException::class);

        $container = new Container();

        $container->get(SingletonFixture::class);
    }

    public function testGetResolvesABoundServiceThroughItsBinding(): void
    {
        $container = new Container();
        $container->bind(SingletonFixture::class, static fn (): SingletonFixture => new SingletonFixture());

        $object = $container->get(SingletonFixture::class);

        self::assertInstanceOf(SingletonFixture::class, $object);
    }

    public function testGetThrowsForAnUnboundInterface(): void
    {
        $this->expectException(ContainerInvalidArgumentException::class);

        $container = new Container();

        $container->get(Throwable::class);
    }
}

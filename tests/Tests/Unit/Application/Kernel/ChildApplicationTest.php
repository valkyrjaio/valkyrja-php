<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Application\Kernel;

use Valkyrja\Application\Data\Config;
use Valkyrja\Application\Kernel\ChildApplication;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Kernel\Valkyrja;
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Container\Manager\ChildContainer;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Manager\NativeChildContainer;
use Valkyrja\Tests\Fixtures\Container\SingletonFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the ChildApplication: own container, all other methods delegate to parent.
 */
final class ChildApplicationTest extends TestCase
{
    private Valkyrja $parent;
    private ChildApplication $child;
    private NativeChildContainer $childContainer;

    protected function setUp(): void
    {
        $config          = new Config();
        $parentContainer = new Container();
        $this->parent    = new Valkyrja(container: $parentContainer, config: $config);

        $this->childContainer = new NativeChildContainer($parentContainer);
        $this->child          = new ChildApplication($this->parent, $this->childContainer);
    }

    // -----------------------------------------------------------------------
    // getContainer
    // -----------------------------------------------------------------------

    public function testGetContainerReturnsChildContainer(): void
    {
        self::assertSame($this->childContainer, $this->child->getContainer());
        self::assertNotSame($this->parent->getContainer(), $this->child->getContainer());
    }

    // -----------------------------------------------------------------------
    // publishProviderCallbacks — delegates to parent, parent app is passed
    // -----------------------------------------------------------------------

    public function testPublishProviderCallbacksDelegatesToParent(): void
    {
        $received = null;

        $config = new Config(
            callbacks: [static function (ApplicationContract $app) use (&$received): void {
                $received = $app;
            }],
        );

        $parentContainer = new Container();
        $parent          = new Valkyrja(container: $parentContainer, config: $config);
        $child           = new ChildApplication($parent, new NativeChildContainer($parentContainer));

        $child->publishProviderCallbacks();

        // The callback must be invoked with the parent application, not the child
        self::assertSame($parent, $received);
        self::assertNotSame($child, $received);
    }

    // -----------------------------------------------------------------------
    // Delegation — all non-container methods return the parent's values
    // -----------------------------------------------------------------------

    public function testGetProvidersDelegatesToParent(): void
    {
        self::assertSame($this->parent->getProviders(), $this->child->getProviders());
    }

    public function testGetContainerProvidersDelegatesToParent(): void
    {
        self::assertSame($this->parent->getContainerProviders(), $this->child->getContainerProviders());
    }

    public function testGetEventProvidersDelegatesToParent(): void
    {
        self::assertSame($this->parent->getEventProviders(), $this->child->getEventProviders());
    }

    public function testGetCliProvidersDelegatesToParent(): void
    {
        self::assertSame($this->parent->getCliProviders(), $this->child->getCliProviders());
    }

    public function testGetHttpProvidersDelegatesToParent(): void
    {
        self::assertSame($this->parent->getHttpProviders(), $this->child->getHttpProviders());
    }

    public function testGetGrpcProvidersDelegatesToParent(): void
    {
        self::assertSame($this->parent->getGrpcProviders(), $this->child->getGrpcProviders());
    }

    public function testGetDebugModeDelegatesToParent(): void
    {
        self::assertSame($this->parent->getDebugMode(), $this->child->getDebugMode());
    }

    public function testGetEnvironmentDelegatesToParent(): void
    {
        self::assertSame($this->parent->getEnvironment(), $this->child->getEnvironment());
    }

    public function testGetVersionDelegatesToParent(): void
    {
        self::assertSame($this->parent->getVersion(), $this->child->getVersion());
    }

    // -----------------------------------------------------------------------
    // Container isolation — child writes must not reach the parent container
    // -----------------------------------------------------------------------

    public function testChildContainerWriteDoesNotAffectParentContainer(): void
    {
        $instance = new SingletonFixture();
        $this->child->getContainer()->setSingleton(SingletonFixture::class, $instance);

        self::assertFalse($this->parent->getContainer()->isSingletonInstance(SingletonFixture::class));
    }

    public function testChildContainerServesItsOwnRegistrations(): void
    {
        $instance = new SingletonFixture();
        $this->child->getContainer()->setSingleton(SingletonFixture::class, $instance);

        self::assertSame($instance, $this->child->getContainer()->getSingleton(SingletonFixture::class));
    }

    // -----------------------------------------------------------------------
    // Alternative container type — ChildContainer (portable default)
    // -----------------------------------------------------------------------

    public function testWorksWithChildContainer(): void
    {
        $parentContainer = new Container();
        $parent          = new Valkyrja(container: $parentContainer, config: new Config());
        $data            = $parentContainer->getData();
        $childContainer  = new ChildContainer($parentContainer, new ContainerData(
            callbacks: $data->callbacks,
            singletons: $data->singletons,
        ));
        $child = new ChildApplication($parent, $childContainer);

        self::assertSame($childContainer, $child->getContainer());
        self::assertSame($parent->getEnvironment(), $child->getEnvironment());
        self::assertSame($parent->getVersion(), $child->getVersion());
    }

    // -----------------------------------------------------------------------
    // Multiple children — independent containers, same parent delegation
    // -----------------------------------------------------------------------

    public function testMultipleChildrenHaveIndependentContainers(): void
    {
        $parentContainer = new Container();
        $child2Container = new NativeChildContainer($parentContainer);
        $child2          = new ChildApplication($this->parent, $child2Container);

        self::assertNotSame($this->child->getContainer(), $child2->getContainer());
        self::assertSame($this->child->getEnvironment(), $child2->getEnvironment());
    }

    public function testMultipleChildrenContainerWritesAreIsolatedFromEachOther(): void
    {
        $parentContainer = new Container();
        $child2Container = new NativeChildContainer($parentContainer);
        $child2          = new ChildApplication($this->parent, $child2Container);

        $instance = new SingletonFixture();
        $this->child->getContainer()->setSingleton(SingletonFixture::class, $instance);

        self::assertFalse($child2->getContainer()->isSingletonInstance(SingletonFixture::class));
    }
}

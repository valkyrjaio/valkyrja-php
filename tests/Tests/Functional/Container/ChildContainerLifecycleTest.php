<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Functional\Container;

use Valkyrja\Container\Manager\ChildContainer;
use Valkyrja\Tests\Fixtures\Container\Provider\ProvidedFixture;
use Valkyrja\Tests\Fixtures\Container\Provider\PublishingProviderFixture;
use Valkyrja\Tests\Fixtures\Container\ServiceFixture;
use Valkyrja\Tests\Fixtures\Container\SingletonFixture;
use Valkyrja\Tests\Functional\Abstract\TestCase;

/**
 * Test the container lifecycle a worker runs: one frozen parent, one snapshot, a child per request.
 */
final class ChildContainerLifecycleTest extends TestCase
{
    public function testEachRequestKeepsItsOwnScopeAndLeavesTheParentAlone(): void
    {
        $parent = $this->app->getContainer();

        $parent->register(new PublishingProviderFixture());
        $parent->bindSingleton('shared', [SingletonFixture::class, 'make']);
        $parent->bindSingleton('unbuilt', [SingletonFixture::class, 'make']);
        $parent->bind('fresh', [ServiceFixture::class, 'make']);
        $parent->bindAlias('sharedAlias', 'shared');
        $shared = $parent->getSingleton('shared');

        $data = $parent->getData();

        $scoped   = [];
        $unbuilt  = [];
        $provided = [];

        for ($request = 0; $request < 3; $request++) {
            $child = new ChildContainer($parent, $data);

            self::assertFalse($child->isSingletonInstance('request'));

            $child->setSingleton('request', $scoped[$request] = new SingletonFixture());

            self::assertSame($shared, $child->getSingleton('shared'));
            self::assertSame($shared, $child->getAliased('sharedAlias'));

            $unbuilt[$request] = $child->getSingleton('unbuilt');
            self::assertSame($unbuilt[$request], $child->getSingleton('unbuilt'));

            $provided[$request] = $child->get(ProvidedFixture::class);

            self::assertNotSame($child->get('fresh'), $child->get('fresh'));

            self::assertSame($scoped[$request], $child->getSingleton('request'));
        }

        self::assertFalse($parent->has('request'));
        self::assertFalse($parent->isSingletonInstance('unbuilt'));
        self::assertFalse($parent->isSingletonInstance('fresh'));
        self::assertFalse($parent->isPublished(ProvidedFixture::class));
        self::assertFalse($parent->isSingletonInstance(ProvidedFixture::class));

        self::assertNotSame($unbuilt[0], $unbuilt[1]);
        self::assertNotSame($unbuilt[1], $unbuilt[2]);
        self::assertNotSame($provided[0], $provided[1]);
        self::assertNotSame($provided[1], $provided[2]);

        $current = $parent->getData();

        self::assertSame($data->aliases, $current->aliases);
        self::assertSame($data->singletons, $current->singletons);
        self::assertSame($data->services, $current->services);
        self::assertSame($data->callbacks, $current->callbacks);
    }
}

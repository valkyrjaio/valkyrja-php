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

        // Boot. Everything a worker registers before the request loop begins.
        $parent->register(new PublishingProviderFixture());
        $parent->bindSingleton('shared', [SingletonFixture::class, 'make']);
        $parent->bindSingleton('unbuilt', [SingletonFixture::class, 'make']);
        $parent->bind('fresh', [ServiceFixture::class, 'make']);
        $parent->bindAlias('sharedAlias', 'shared');
        $shared = $parent->getSingleton('shared');

        // One snapshot, taken once, read by every request.
        $data          = $parent->getData();
        $registrations = $parent->getData();

        $scoped   = [];
        $unbuilt  = [];
        $provided = [];

        for ($request = 0; $request < 3; $request++) {
            $child = new ChildContainer($parent, $data);

            // A fresh child carries nothing the last request registered
            self::assertFalse($child->isSingletonInstance('request'));

            $child->setSingleton('request', $scoped[$request] = new SingletonFixture());

            // The parent built this one before the loop, so every request shares it
            self::assertSame($shared, $child->getSingleton('shared'));
            self::assertSame($shared, $child->getAliased('sharedAlias'));

            // The parent never built this one, so the request builds its own
            $unbuilt[$request] = $child->getSingleton('unbuilt');
            self::assertSame($unbuilt[$request], $child->getSingleton('unbuilt'));

            // The child holds the publish callback, so it publishes into itself
            $provided[$request] = $child->get(ProvidedFixture::class);

            // A bound factory runs for each call, and caches nowhere
            self::assertNotSame($child->get('fresh'), $child->get('fresh'));

            self::assertSame($scoped[$request], $child->getSingleton('request'));
        }

        // Nothing a request registered reaches the parent
        self::assertFalse($parent->has('request'));
        self::assertFalse($parent->isSingletonInstance('unbuilt'));
        self::assertFalse($parent->isSingletonInstance('fresh'));
        self::assertFalse($parent->isPublished(ProvidedFixture::class));
        self::assertFalse($parent->isSingletonInstance(ProvidedFixture::class));

        // Nothing one request built reaches another
        self::assertNotSame($unbuilt[0], $unbuilt[1]);
        self::assertNotSame($unbuilt[1], $unbuilt[2]);
        self::assertNotSame($provided[0], $provided[1]);
        self::assertNotSame($provided[1], $provided[2]);

        // The parent still holds the registrations it booted with
        $current = $parent->getData();

        self::assertSame($registrations->aliases, $current->aliases);
        self::assertSame($registrations->singletons, $current->singletons);
        self::assertSame($registrations->services, $current->services);
        self::assertSame($registrations->callbacks, $current->callbacks);
    }
}

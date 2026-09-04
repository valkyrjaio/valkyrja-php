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

use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Container\Manager\ChildContainer;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Throwable\Exception\ContainerCyclicAliasException;
use Valkyrja\Container\Throwable\Exception\ContainerInvalidReferenceException;
use Valkyrja\Tests\Fixtures\Container\Provider\ProvidedFixture;
use Valkyrja\Tests\Fixtures\Container\Provider\PublishingProviderFixture;
use Valkyrja\Tests\Fixtures\Container\ServiceFixture;
use Valkyrja\Tests\Fixtures\Container\SingletonFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class ChildContainerTest extends TestCase
{
    private Container $parent;
    private ChildContainer $child;

    protected function setUp(): void
    {
        $this->parent = new Container();
        $this->child  = $this->createChild();
    }

    // -----------------------------------------------------------------------
    // isAlias
    // -----------------------------------------------------------------------

    public function testIsAliasFromParent(): void
    {
        $this->parent->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);
        $this->parent->bindAlias('myAlias', ServiceFixture::class);

        self::assertTrue($this->child->isAlias('myAlias'));
        self::assertFalse($this->child->isAlias('unknown'));
    }

    public function testIsAliasFromChild(): void
    {
        $this->child->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);
        $this->child->bindAlias('childAlias', ServiceFixture::class);

        self::assertTrue($this->child->isAlias('childAlias'));
        self::assertFalse($this->parent->isAlias('childAlias'));
    }

    public function testGetAliasedIdAgreesWithIsAlias(): void
    {
        $this->parent->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);
        $this->parent->bindAlias('myAlias', ServiceFixture::class);

        self::assertTrue($this->child->isAlias('myAlias'));
        self::assertSame(ServiceFixture::class, $this->child->getAliasedId('myAlias'));

        self::assertFalse($this->child->isAlias('unknown'));
        self::assertNull($this->child->getAliasedId('unknown'));
    }

    public function testGetAliasedIdFromChildTakesPrecedence(): void
    {
        $this->parent->bindAlias('shared', ServiceFixture::class);
        $this->child->bindAlias('shared', SingletonFixture::class);

        self::assertSame(SingletonFixture::class, $this->child->getAliasedId('shared'));
        self::assertSame(ServiceFixture::class, $this->parent->getAliasedId('shared'));
    }

    // -----------------------------------------------------------------------
    // isService
    // -----------------------------------------------------------------------

    public function testIsServiceFromParent(): void
    {
        $this->parent->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);

        self::assertTrue($this->child->isService(ServiceFixture::class));
        self::assertFalse($this->child->isService(SingletonFixture::class));
    }

    public function testIsServiceFromChild(): void
    {
        $this->child->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);

        self::assertTrue($this->child->isService(ServiceFixture::class));
        self::assertFalse($this->parent->isService(ServiceFixture::class));
    }

    // -----------------------------------------------------------------------
    // isSingleton / isSingletonBinding / isSingletonInstance
    // -----------------------------------------------------------------------

    public function testIsSingletonBindingFromParent(): void
    {
        $this->parent->bindSingleton(SingletonFixture::class, [SingletonFixture::class, 'make']);
        // Re-create child after parent setup so the copied data includes the binding
        $child = $this->createChild();

        self::assertTrue($child->isSingletonBinding(SingletonFixture::class));
        self::assertTrue($child->isSingleton(SingletonFixture::class));
        self::assertFalse($child->isSingletonInstance(SingletonFixture::class));
    }

    public function testIsSingletonInstanceFromParent(): void
    {
        $instance = new SingletonFixture();
        $this->parent->setSingleton(SingletonFixture::class, $instance);

        self::assertTrue($this->child->isSingletonInstance(SingletonFixture::class));
        self::assertTrue($this->child->isSingleton(SingletonFixture::class));
    }

    public function testIsSingletonBindingFromChild(): void
    {
        $this->child->bindSingleton(SingletonFixture::class, [SingletonFixture::class, 'make']);

        self::assertTrue($this->child->isSingletonBinding(SingletonFixture::class));
        self::assertFalse($this->parent->isSingletonBinding(SingletonFixture::class));
    }

    public function testIsSingletonInstanceFromChild(): void
    {
        $instance = new SingletonFixture();
        $this->child->setSingleton(SingletonFixture::class, $instance);

        self::assertTrue($this->child->isSingletonInstance(SingletonFixture::class));
        self::assertFalse($this->parent->isSingletonInstance(SingletonFixture::class));
    }

    // -----------------------------------------------------------------------
    // has (registered via provider) / isPublished
    // -----------------------------------------------------------------------

    public function testHasFromParentWhenRegisteredInParent(): void
    {
        $this->parent->register(new PublishingProviderFixture());
        // Re-create child so callbacks are copied from parent
        $child = $this->createChild();

        self::assertTrue($child->has(ProvidedFixture::class));
    }

    public function testHasFromChildWhenRegisteredInChild(): void
    {
        $this->child->register(new PublishingProviderFixture());

        self::assertTrue($this->child->has(ProvidedFixture::class));
        self::assertFalse($this->parent->has(ProvidedFixture::class));
    }

    public function testIsPublishedFromParent(): void
    {
        $this->parent->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);

        self::assertTrue($this->child->isPublished(ServiceFixture::class));
    }

    public function testIsPublishedFromChild(): void
    {
        $this->child->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);

        self::assertTrue($this->child->isPublished(ServiceFixture::class));
        self::assertFalse($this->parent->isPublished(ServiceFixture::class));
    }

    // -----------------------------------------------------------------------
    // getSingleton — parent fallback and child isolation
    // -----------------------------------------------------------------------

    public function testGetSingletonFromParentBinding(): void
    {
        $this->parent->bindSingleton(SingletonFixture::class, [SingletonFixture::class, 'make']);
        // Re-create child so the copied data includes the binding
        $child = $this->createChild();

        $instance = $child->getSingleton(SingletonFixture::class);

        self::assertInstanceOf(SingletonFixture::class, $instance);
        // Resolved instance must be cached in child, NOT in parent
        self::assertSame($instance, $child->getSingleton(SingletonFixture::class));
        self::assertFalse($this->parent->isSingletonInstance(SingletonFixture::class));
    }

    public function testGetSingletonFromParentInstance(): void
    {
        $parentInstance = new SingletonFixture();
        $this->parent->setSingleton(SingletonFixture::class, $parentInstance);

        $childResult = $this->child->getSingleton(SingletonFixture::class);

        self::assertSame($parentInstance, $childResult);
    }

    public function testGetSingletonFromChildOverridesParent(): void
    {
        $parentInstance = new SingletonFixture();
        $this->parent->setSingleton(SingletonFixture::class, $parentInstance);

        $childInstance = new SingletonFixture();
        $this->child->setSingleton(SingletonFixture::class, $childInstance);

        self::assertSame($childInstance, $this->child->getSingleton(SingletonFixture::class));
        self::assertNotSame($parentInstance, $this->child->getSingleton(SingletonFixture::class));
    }

    public function testChildSingletonDoesNotPollutesParent(): void
    {
        $this->parent->bindSingleton(SingletonFixture::class, [SingletonFixture::class, 'make']);
        // Re-create child so the copied data includes the binding
        $child = $this->createChild();

        $childInstance = $child->getSingleton(SingletonFixture::class);

        // Parent must remain unpolluted
        self::assertFalse($this->parent->isSingletonInstance(SingletonFixture::class));
        self::assertNotNull($childInstance);
    }

    // -----------------------------------------------------------------------
    // getService — parent delegation and child-local
    // -----------------------------------------------------------------------

    public function testGetServiceFromParent(): void
    {
        $this->parent->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);

        $instance = $this->child->getService(ServiceFixture::class);

        self::assertInstanceOf(ServiceFixture::class, $instance);
        self::assertNotSame($instance, $this->child->getService(ServiceFixture::class));
    }

    public function testGetServiceDelegatesWhenTheParentAlreadyPublished(): void
    {
        $this->parent->setFromData(new ContainerData(
            callbacks: [ServiceFixture::class => static function (ContainerContract $container): void {
                $container->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);
            }],
        ));
        $this->parent->get(ServiceFixture::class);
        $child = new ChildContainer($this->parent, new ContainerData());

        // The callback ran, so the guard lets the delegation through
        self::assertTrue($this->parent->isPublished(ServiceFixture::class));
        self::assertInstanceOf(ServiceFixture::class, $child->getService(ServiceFixture::class));
    }

    public function testGetServiceFromChild(): void
    {
        $this->child->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);

        $instance = $this->child->getService(ServiceFixture::class);

        self::assertInstanceOf(ServiceFixture::class, $instance);
        self::assertFalse($this->parent->isService(ServiceFixture::class));
    }

    // -----------------------------------------------------------------------
    // getAliased — parent fallback
    // -----------------------------------------------------------------------

    public function testSnapshotChildResolvesAnUnbuiltParentSingletonItself(): void
    {
        // Boot: two singletons on the parent, one resolved before any child exists
        $this->parent->bindSingleton('Resolved', [SingletonFixture::class, 'make']);
        $this->parent->bindSingleton('Unresolved', [ServiceFixture::class, 'make']);
        $this->parent->bindAlias('UnresolvedAlias', 'Unresolved');
        $shared = $this->parent->getSingleton('Resolved');

        // The request loop begins from one snapshot
        $child = $this->createChild();

        // The resolved one is shared, and the unresolved one is the child's own
        self::assertSame($shared, $child->get('Resolved'));
        self::assertInstanceOf(ServiceFixture::class, $child->get('Unresolved'));
        self::assertTrue($child->isSingletonInstance('Unresolved'));
        self::assertFalse($this->parent->isSingletonInstance('Unresolved'));

        // The alias reaches the same copy, so the request holds one instance of it
        self::assertSame($child->get('Unresolved'), $child->get('UnresolvedAlias'));
        self::assertFalse($this->parent->isSingletonInstance('Unresolved'));
    }

    public function testAChainOntoAnUnbuiltParentSingletonResolvesInTheChild(): void
    {
        // outer → middle → the singleton, none of it built in the parent
        $this->parent->bindSingleton(SingletonFixture::class, [SingletonFixture::class, 'make']);
        $this->parent->bindAlias('middle', SingletonFixture::class);
        $this->parent->bindAlias('outer', 'middle');
        $child = $this->createChild();

        $instance = $child->get('outer');

        self::assertInstanceOf(SingletonFixture::class, $instance);
        self::assertSame($instance, $child->get(SingletonFixture::class));
        self::assertFalse($this->parent->isSingletonInstance(SingletonFixture::class));
    }

    public function testGetAliasedPublishesADeferredParentTargetInTheChild(): void
    {
        $this->parent->register(new PublishingProviderFixture());
        $this->parent->bindAlias('providedAlias', ProvidedFixture::class);
        $child = $this->createChild();

        // The child holds the same callback, so it publishes into itself
        $fromId    = $child->get(ProvidedFixture::class);
        $fromAlias = $child->get('providedAlias');

        self::assertSame($fromId, $fromAlias);
        self::assertFalse($this->parent->isPublished(ProvidedFixture::class));
        self::assertFalse($this->parent->isSingletonInstance(ProvidedFixture::class));
    }

    public function testGetAliasedStopsWhereTheParentStops(): void
    {
        // The parent answers 'middle' as a singleton, so it never reaches the rest
        $this->parent->bindAlias('outer', 'middle');
        $this->parent->bindSingleton('middle', [SingletonFixture::class, 'make']);
        $this->parent->bindAlias('middle', ServiceFixture::class);
        $this->parent->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);
        $child = $this->createChild();

        self::assertInstanceOf(SingletonFixture::class, $child->getAliased('outer'));
        self::assertFalse($this->parent->isSingletonInstance('middle'));
    }

    public function testGetAliasedFromParent(): void
    {
        $this->parent->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);
        $this->parent->bindAlias('svcAlias', ServiceFixture::class);

        $instance = $this->child->getAliased('svcAlias');

        self::assertInstanceOf(ServiceFixture::class, $instance);
    }

    public function testGetAliasedFromChild(): void
    {
        $this->child->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);
        $this->child->bindAlias('childAlias', ServiceFixture::class);

        $instance = $this->child->getAliased('childAlias');

        self::assertInstanceOf(ServiceFixture::class, $instance);
        self::assertFalse($this->parent->isAlias('childAlias'));
    }

    public function testGetAliasedFromParentReusesAResolvedSingleton(): void
    {
        $parentInstance = new SingletonFixture();
        $this->parent->setSingleton(SingletonFixture::class, $parentInstance);
        $this->parent->bindAlias('singletonAlias', SingletonFixture::class);
        $child = $this->createChild();

        self::assertSame($parentInstance, $child->getAliased('singletonAlias'));
    }

    public function testGetAliasedFromParentReusesAForceResolvedSingletonBinding(): void
    {
        $this->parent->bindSingleton(SingletonFixture::class, [SingletonFixture::class, 'make']);
        $this->parent->bindAlias('singletonAlias', SingletonFixture::class);
        $parentInstance = $this->parent->getSingleton(SingletonFixture::class);
        $child          = $this->createChild();

        self::assertSame($parentInstance, $child->getAliased('singletonAlias'));
    }

    public function testGetAliasedFollowsAParentAliasChain(): void
    {
        $this->parent->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);
        $this->parent->bindAlias('second', ServiceFixture::class);
        $this->parent->bindAlias('first', 'second');
        $child = $this->createChild();

        self::assertInstanceOf(ServiceFixture::class, $child->getAliased('first'));
    }

    public function testGetAliasedThrowsTheParentsOwnErrorForAnAbsentTarget(): void
    {
        $this->parent->bindAlias('svcAlias', ServiceFixture::class);
        $child = $this->createChild();

        // The parent never bound the target, so this is not an unresolved parent alias
        $this->expectException(ContainerInvalidReferenceException::class);

        $child->getAliased('svcAlias');
    }

    public function testIsDeferredReportsOnlyTheChildsOwnCallbacks(): void
    {
        $this->parent->register(new PublishingProviderFixture());
        // A child built without the parent's callbacks cannot run them
        $child = new ChildContainer($this->parent, new ContainerData());

        // has() reads isDeferred(), so a true here would promise a get() that fails
        self::assertFalse($child->isDeferred(ProvidedFixture::class));
        self::assertFalse($child->has(ProvidedFixture::class));
    }

    public function testIsDeferredFromCopiedCallbacks(): void
    {
        $this->parent->register(new PublishingProviderFixture());
        $child = $this->createChild();

        self::assertTrue($child->isDeferred(ProvidedFixture::class));
        self::assertFalse($child->isDeferred(SingletonFixture::class));
    }

    public function testGetAliasedDelegatesWhenTheParentAlreadyPublished(): void
    {
        $this->parent->register(new PublishingProviderFixture());
        $this->parent->get(ProvidedFixture::class);
        $this->parent->bindAlias('providedAlias', ProvidedFixture::class);
        $child = $this->createChild();

        // The callback ran, so the already-published arm delegates
        self::assertTrue($this->parent->isPublished(ProvidedFixture::class));
        self::assertInstanceOf(ProvidedFixture::class, $child->getAliased('providedAlias'));
    }

    public function testGetThrowsWhenNoContainerHasTheAlias(): void
    {
        $this->expectException(ContainerInvalidReferenceException::class);

        $this->child->get(SingletonFixture::class);
    }

    // -----------------------------------------------------------------------
    // Parent immutability — parent state must not change through child operations
    // -----------------------------------------------------------------------

    public function testParentStateUnchangedAfterChildOperations(): void
    {
        // Set up parent with each registration type
        $this->parent->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);
        $this->parent->bindAlias('svcAlias', ServiceFixture::class);
        $this->parent->bindSingleton(SingletonFixture::class, [SingletonFixture::class, 'make']);
        $this->parent->register(new PublishingProviderFixture());

        // Build child from the fully-set-up parent
        $child = $this->createChild();

        // Snapshot parent state before any child interaction
        $dataBefore                = $this->parent->getData();
        $singletonInstanceBefore   = $this->parent->isSingletonInstance(SingletonFixture::class);
        $providedPublishedBefore   = $this->parent->isPublished(ProvidedFixture::class);

        // Perform a broad set of child operations
        $child->get(ServiceFixture::class);
        $child->getService(ServiceFixture::class);
        $child->getAliased('svcAlias');
        $child->getSingleton(SingletonFixture::class);
        $child->get(ProvidedFixture::class); // triggers publish in child

        // Parent data maps must be identical
        $dataAfter = $this->parent->getData();
        self::assertSame($dataBefore->aliases, $dataAfter->aliases);
        self::assertSame($dataBefore->services, $dataAfter->services);
        self::assertSame($dataBefore->singletons, $dataAfter->singletons);
        self::assertSame($dataBefore->callbacks, $dataAfter->callbacks);

        // Singleton resolved in child must not have been cached in parent
        self::assertSame($singletonInstanceBefore, $this->parent->isSingletonInstance(SingletonFixture::class));

        // Service published in child must not mark parent as published
        self::assertSame($providedPublishedBefore, $this->parent->isPublished(ProvidedFixture::class));
    }

    // -----------------------------------------------------------------------
    // Provider — published in child context
    // -----------------------------------------------------------------------

    public function testProviderFromChildPublishedInChild(): void
    {
        $this->child->register(new PublishingProviderFixture());

        self::assertTrue($this->child->has(ProvidedFixture::class));

        $provided = $this->child->get(ProvidedFixture::class);
        self::assertInstanceOf(ProvidedFixture::class, $provided);

        self::assertFalse($this->parent->isPublished(ProvidedFixture::class));
    }

    public function testProviderFromParentPublishedInChild(): void
    {
        $this->parent->register(new PublishingProviderFixture());
        // Re-create child so callbacks are copied from parent
        $child = $this->createChild();

        self::assertTrue($child->has(ProvidedFixture::class));

        $provided = $child->get(ProvidedFixture::class);
        self::assertInstanceOf(ProvidedFixture::class, $provided);

        // Publishing must stay in child, not pollute parent
        self::assertFalse($this->parent->isPublished(ProvidedFixture::class));
    }

    public function testGetAliasedReusesAParentTargetTheParentAlreadyPublished(): void
    {
        $this->parent->register(new PublishingProviderFixture());
        $this->parent->bindAlias('providedAlias', ProvidedFixture::class);
        // The parent publishes at boot, so the request reuses what it holds
        $shared = $this->parent->get(ProvidedFixture::class);
        $child  = $this->createChild();

        self::assertSame($shared, $child->getAliased('providedAlias'));
    }

    public function testGetAliasedStopsAtAParentServiceInTheChain(): void
    {
        // The parent answers 'middle' as a service, so it never reaches the rest
        $this->parent->bindAlias('outer', 'middle');
        $this->parent->bind('middle', [ServiceFixture::class, 'make']);
        $this->parent->bindAlias('middle', SingletonFixture::class);
        $this->parent->bindSingleton(SingletonFixture::class, [SingletonFixture::class, 'make']);
        $child = $this->createChild();

        self::assertInstanceOf(ServiceFixture::class, $child->getAliased('outer'));
        self::assertFalse($this->parent->isSingletonInstance(SingletonFixture::class));
    }

    public function testIsSingletonBindingReadsTheChildThenTheParent(): void
    {
        $child = $this->createChild();
        $child->bindSingleton(ServiceFixture::class, [ServiceFixture::class, 'make']);
        // A snapshot copies the parent's bindings, so only a later one reaches the fallback
        $this->parent->bindSingleton(SingletonFixture::class, [SingletonFixture::class, 'make']);

        self::assertTrue($child->isSingletonBinding(ServiceFixture::class));
        self::assertTrue($child->isSingletonBinding(SingletonFixture::class));
        self::assertFalse($child->isSingletonBinding('unknown'));
    }

    public function testGetAliasedStopsAtADeferredHopInTheChain(): void
    {
        // The parent publishes before it reads any map, so it stops at the deferred hop
        $this->parent->register(new PublishingProviderFixture());
        $this->parent->bindAlias('outer', ProvidedFixture::class);
        $this->parent->bindAlias(ProvidedFixture::class, ServiceFixture::class);
        $this->parent->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);
        $child = $this->createChild();

        // The child holds the same callback, so it publishes into itself
        $fromId = $child->get(ProvidedFixture::class);

        self::assertSame($fromId, $child->getAliased('outer'));
        self::assertFalse($this->parent->isPublished(ProvidedFixture::class));
        self::assertFalse($this->parent->isSingletonInstance(ProvidedFixture::class));
    }

    public function testGetAliasedStopsAtAParentInstanceInTheChain(): void
    {
        // The parent holds 'middle' as an instance, so it never reaches the rest
        $this->parent->bindAlias('outer', 'middle');
        $this->parent->setSingleton('middle', $shared = new SingletonFixture());
        $this->parent->bindAlias('middle', ServiceFixture::class);
        $this->parent->bind(ServiceFixture::class, [ServiceFixture::class, 'make']);
        $child = $this->createChild();

        self::assertSame($shared, $child->getAliased('outer'));
    }

    public function testSetFromDataLeavesTheAliasMapAloneWhenItIsCyclic(): void
    {
        $this->parent->bindAlias('kept', ServiceFixture::class);

        try {
            $this->parent->setFromData(new ContainerData(
                aliases: ['first' => 'second', 'second' => 'first'],
            ));
        } catch (ContainerCyclicAliasException) {
            // The container a caller keeps holds no part of the rejected map
        }

        self::assertSame(ServiceFixture::class, $this->parent->getAliasedId('kept'));
        self::assertNull($this->parent->getAliasedId('first'));
    }

    /**
     * Create a ChildContainer from the current parent state.
     * The ContainerData is built from the parent and passed explicitly.
     */
    private function createChild(): ChildContainer
    {
        $data = $this->parent->getData();

        return new ChildContainer($this->parent, new ContainerData(
            callbacks: $data->callbacks,
            singletons: $data->singletons,
        ));
    }
}

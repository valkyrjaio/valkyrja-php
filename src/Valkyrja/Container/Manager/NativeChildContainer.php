<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Container\Manager;

use Override;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Throwable\Exception\ContainerCyclicAliasException;

class NativeChildContainer extends Container
{
    /**
     * The alias targets this container is resolving.
     *
     * @var array<class-string, true>
     */
    private array $targetsInFlight = [];

    public function __construct(
        protected Container $parent
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    public function isAlias(string $id): bool
    {
        return $this->getAliasedId($id) !== null;
    }

    /**
     * @inheritDoc
     *
     * @param class-string $alias The alias
     *
     * @return class-string|null
     */
    #[Override]
    public function getAliasedId(string $alias): string|null
    {
        return $this->aliases[$alias]
            ?? $this->parent->aliases[$alias]
            ?? null;
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    public function isService(string $id): bool
    {
        return $this->getServiceCallable($id) !== null;
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    public function isSingletonBinding(string $id): bool
    {
        return isset($this->singletons[$id])
            || isset($this->parent->singletons[$id]);
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    public function isSingletonInstance(string $id): bool
    {
        return isset($this->instances[$id])
            || isset($this->parent->instances[$id]);
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The provided service id
     */
    #[Override]
    public function isDeferred(string $id): bool
    {
        return isset($this->callbacks[$id])
            || isset($this->parent->callbacks[$id]);
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The provided service id
     */
    #[Override]
    public function isPublished(string $id): bool
    {
        return isset($this->published[$id])
            || isset($this->parent->published[$id]);
    }

    /**
     * @inheritDoc
     *
     * @param class-string            $id        The service id
     * @param array<array-key, mixed> $arguments [optional] The arguments
     */
    #[Override]
    protected function getAliasedWithoutChecks(string $id, array $arguments = []): object|null
    {
        if (isset($this->aliases[$id])) {
            return parent::getAliasedWithoutChecks($id, $arguments);
        }

        $target = $this->getParentAliasTarget($id);

        if ($target === null) {
            return null;
        }

        // The child holds the same registration. One request must not hold one copy
        // for the alias and another for the target.
        if ($this->resolvesInChild($target)) {
            return $this->getTargetOnce($id, $target, $arguments);
        }

        return $this->parent->getAliased($id, $arguments);
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     *
     * @return callable(ContainerContract):void|null
     */
    #[Override]
    protected function getCallback(string $id): callable|null
    {
        return $this->callbacks[$id]
            ?? $this->parent->callbacks[$id]
            ?? null;
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    protected function getSingletonInstance(string $id): object|null
    {
        return $this->instances[$id]
            ?? $this->parent->instances[$id]
            ?? null;
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     *
     * @return callable(ContainerContract, array<array-key, mixed>):object|null
     */
    #[Override]
    protected function getServiceCallable(string $id): callable|null
    {
        return $this->services[$id]
            ?? $this->parent->services[$id]
            ?? null;
    }

    /**
     * Walk the parent's chain of aliases, and return the last hop it reaches.
     *
     * The walk stops at a hop the parent's own resolution would stop at, or at the end of
     * the chain.
     *
     * @param class-string $id The alias
     *
     * @return class-string|null
     */
    private function getParentAliasTarget(string $id): string|null
    {
        $current = $id;
        $target  = null;

        while (($aliasedId = $this->parent->aliases[$current] ?? null) !== null) {
            $target  = $aliasedId;
            $current = $aliasedId;

            // The parent reads these before it follows an alias, so it can answer at this
            // hop rather than continue the chain.
            if (($this->parent->isDeferred($current) && ! $this->parent->isPublished($current))
                || isset($this->parent->singletons[$current])
                || isset($this->parent->instances[$current])
                || isset($this->parent->services[$current])
            ) {
                break;
            }
        }

        return $target;
    }

    /**
     * Check whether the child resolves the target of a parent-declared alias itself.
     *
     * @param class-string $target The target id
     */
    private function resolvesInChild(string $target): bool
    {
        // The parent publishes before it reads any map, so this test comes first. This
        // class copies no callback map, so the parent's callback is the child's as well.
        if ($this->parent->isDeferred($target) && ! $this->parent->isPublished($target)) {
            return true;
        }

        if (isset($this->parent->instances[$target])) {
            return false;
        }

        // This class copies no map, so the parent's marker is the child's as well. One read
        // carries what the portable child needs two for.
        return $this->parent->isSingletonBinding($target);
    }

    /**
     * Resolve an alias target, and reject a chain that returns to one already in flight.
     *
     * @param class-string            $id        The alias
     * @param class-string            $target    The target id
     * @param array<array-key, mixed> $arguments The arguments
     */
    private function getTargetOnce(string $id, string $target, array $arguments): object
    {
        // A chain that closes across two walks returns here rather than to one walk. An
        // instance cached for the target has broken the chain, so read that first.
        if (isset($this->targetsInFlight[$target])) {
            // The factory receives the child, so the child's map is where a registration
            // made during this resolution lands.
            $registered = $this->instances[$target] ?? null;

            if ($registered !== null) {
                return $registered;
            }

            throw new ContainerCyclicAliasException($id, $target);
        }

        $this->targetsInFlight[$target] = true;

        try {
            return $this->get($target, $arguments);
        } finally {
            unset($this->targetsInFlight[$target]);
        }
    }
}

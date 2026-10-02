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
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Throwable\Exception\ContainerCyclicAliasException;

class ChildContainer extends Container
{
    /**
     * The alias targets this container is resolving.
     *
     * @var array<class-string, true>
     */
    private array $targetsInFlight = [];

    public function __construct(
        protected ContainerContract $parent,
        ContainerData $data,
    ) {
        parent::__construct();

        $this->singletons = $data->singletons;
        $this->callbacks  = $data->callbacks;
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    public function isAlias(string $id): bool
    {
        return parent::isAlias($id)
            || $this->parent->isAlias($id);
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
        return parent::getAliasedId($alias)
            ?? $this->parent->getAliasedId($alias);
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    public function isService(string $id): bool
    {
        return parent::isService($id)
            || $this->parent->isService($id);
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    public function isSingletonInstance(string $id): bool
    {
        return parent::isSingletonInstance($id)
            || $this->parent->isSingletonInstance($id);
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The provided service id
     */
    #[Override]
    public function isPublished(string $id): bool
    {
        return parent::isPublished($id)
            || $this->parent->isPublished($id);
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    protected function getSingletonWithoutChecks(string $id): object|null
    {
        // Parent already has a resolved instance — reuse it (frozen, safe)
        // and the child has none of its own
        if (! parent::isSingletonInstance($id) && $this->parent->isSingletonInstance($id)) {
            return $this->parent->getSingleton($id);
        }

        return parent::getSingletonWithoutChecks($id);
    }

    /**
     * @inheritDoc
     *
     * @param class-string            $id        The service id
     * @param array<array-key, mixed> $arguments [optional] The arguments
     */
    #[Override]
    protected function getServiceWithoutChecks(string $id, array $arguments = []): object|null
    {
        if (! parent::isService($id) && $this->parent->isService($id)) {
            return $this->parent->getService($id, $arguments);
        }

        return parent::getServiceWithoutChecks($id, $arguments);
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
        if (parent::isAlias($id)) {
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
     * Walk the parent's chain of aliases, and return the last hop it reaches.
     *
     * @param class-string $id The alias
     *
     * @return class-string|null
     */
    private function getParentAliasTarget(string $id): string|null
    {
        $current = $id;
        $target  = null;
        $seen    = [$id => true];

        while (($aliasedId = $this->parent->getAliasedId($current)) !== null) {
            // A parent that is itself a child reads its own map and its parent's, and a
            // binding made on either after it was built can close a chain between them.
            if (isset($seen[$aliasedId])) {
                throw new ContainerCyclicAliasException($current, $aliasedId);
            }

            $seen[$aliasedId] = true;
            $target           = $aliasedId;
            $current          = $aliasedId;

            // The parent reads these before it follows an alias, so it can answer at this
            // hop rather than continue the chain.
            if (($this->parent->isDeferred($current) && ! $this->parent->isPublished($current))
                || $this->parent->isSingleton($current)
                || $this->parent->isService($current)
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
        // The parent publishes before it reads any map, so this test comes first. The
        // parent's state and the child's callback each decide one half.
        if ($this->parent->isDeferred($target)
            && ! $this->parent->isPublished($target)
            && $this->isDeferred($target)
        ) {
            return true;
        }

        if ($this->parent->isSingletonInstance($target)) {
            return false;
        }

        // Both containers answer here, and each marker decides one half.
        return $this->parent->isSingletonBinding($target) && $this->isSingletonBinding($target);
    }

    /**
     * Resolve an alias target, and check a chain that returns to one already in flight.
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
            $registered = $this->getSingletonInstance($target);

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

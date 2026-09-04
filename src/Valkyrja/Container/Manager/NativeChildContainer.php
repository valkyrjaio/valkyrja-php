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

class NativeChildContainer extends Container
{
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

        // The parent would resolve this target for the first time, and the child holds
        // the same registration, so letting the parent do it would leave the request
        // with one copy for the alias and another for the id.
        if ($this->isUnbuiltInParent($target)) {
            return $this->get($target, $arguments);
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
     * Walk the parent's chain of aliases to the id the parent would answer.
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

            // The parent publishes, then reads its maps, and only then follows an
            // alias, so it never reaches the rest of the chain from any of these.
            if (isset($this->parent->callbacks[$current])
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
     * Check whether the parent would resolve an id for the first time.
     *
     * @param class-string $id The target id
     */
    private function isUnbuiltInParent(string $id): bool
    {
        // The parent publishes before it reads any map, so this test comes first.
        if ($this->parent->isDeferred($id) && ! $this->parent->isPublished($id)) {
            return true;
        }

        if (isset($this->parent->instances[$id])) {
            return false;
        }

        return $this->parent->isSingletonBinding($id);
    }
}

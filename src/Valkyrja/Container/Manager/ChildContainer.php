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

class ChildContainer extends Container
{
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
    public function isSingletonBinding(string $id): bool
    {
        return parent::isSingletonBinding($id)
            || $this->parent->isSingletonBinding($id);
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

        // The parent would resolve this target for the first time, and the child holds
        // the same registration, so letting the parent do it would leave the request
        // with one copy for the alias and another for the id.
        if ($this->isUnbuiltInParent($target)) {
            return $this->get($target, $arguments);
        }

        return $this->parent->getAliased($id, $arguments);
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

        while (($aliasedId = $this->parent->getAliasedId($current)) !== null) {
            $target  = $aliasedId;
            $current = $aliasedId;

            // The parent publishes, then reads its maps, and only then follows an
            // alias, so it never reaches the rest of the chain from any of these.
            if ($this->parent->isDeferred($current)
                || $this->parent->isSingleton($current)
                || $this->parent->isService($current)
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

        if ($this->parent->isSingletonInstance($id)) {
            return false;
        }

        return $this->parent->isSingletonBinding($id);
    }
}

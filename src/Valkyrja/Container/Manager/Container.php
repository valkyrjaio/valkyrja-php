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
use Valkyrja\Container\Manager\Trait\ProvidersAware;
use Valkyrja\Container\Throwable\Exception\ContainerCyclicAliasException;
use Valkyrja\Container\Throwable\Exception\ContainerInvalidReferenceException;

use function array_merge;
use function is_object;

class Container implements ContainerContract
{
    use ProvidersAware;

    /**
     * The aliases.
     *
     * @var array<class-string, class-string>
     */
    protected array $aliases = [];

    /**
     * The instances.
     *
     * @var array<class-string, object>
     */
    protected array $instances = [];

    /**
     * The services.
     *
     * @var array<class-string, callable(ContainerContract, array<array-key, mixed>):object>
     */
    protected array $services = [];

    /**
     * The singletons.
     *
     * @var array<class-string, class-string>
     */
    protected array $singletons = [];

    public function __construct(
        protected ContainerData $data = new ContainerData()
    ) {
        $this->aliases          = $data->aliases;
        $this->callbacks        = $data->callbacks;
        $this->services         = $data->services;
        $this->singletons       = $data->singletons;

        $this->validateAliasesAreNotCyclic();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getData(): ContainerData
    {
        return new ContainerData(
            aliases: $this->aliases,
            callbacks: $this->callbacks,
            services: $this->services,
            singletons: $this->singletons,
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function setFromData(ContainerData $data): void
    {
        $originalAliases = $this->aliases;

        $this->aliases          = array_merge($this->aliases, $data->aliases);
        $this->callbacks        = array_merge($this->callbacks, $data->callbacks);
        $this->services         = array_merge($this->services, $data->services);
        $this->singletons       = array_merge($this->singletons, $data->singletons);

        try {
            $this->validateAliasesAreNotCyclic();
        } catch (ContainerCyclicAliasException $exception) {
            // A caller that catches this keeps the container it had, not a cyclic map
            $this->aliases = $originalAliases;

            throw $exception;
        }
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    #[Override]
    public function has(string $id): bool
    {
        return $this->isDeferred($id)
            || $this->isSingleton($id)
            || $this->isService($id)
            || $this->isAlias($id);
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    public function bind(string $id, callable $callable): static
    {
        /** @var callable(ContainerContract, mixed...):object $callable */
        $this->services[$id]  = $callable;
        $this->published[$id] = true;

        return $this;
    }

    /**
     * @inheritDoc
     *
     * @param class-string $alias The alias
     * @param class-string $id    The service id to alias
     */
    #[Override]
    public function bindAlias(string $alias, string $id): static
    {
        $this->validateAliasIsNotCyclic($alias, $id);

        $this->aliases[$alias] = $id;

        return $this;
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    public function bindSingleton(string $id, callable $callable): static
    {
        $this->singletons[$id] = $id;

        /** @var callable(ContainerContract, mixed...):object $callable */
        $this->bind($id, $callable);

        return $this;
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    public function setSingleton(string $id, object $singleton): static
    {
        $this->instances[$id]  = $singleton;
        $this->published[$id]  = true;

        return $this;
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    public function isAlias(string $id): bool
    {
        return isset($this->aliases[$id]);
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    public function isService(string $id): bool
    {
        return isset($this->services[$id]);
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    public function isSingleton(string $id): bool
    {
        return $this->isSingletonBinding($id)
            || $this->isSingletonInstance($id);
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    public function isSingletonBinding(string $id): bool
    {
        return isset($this->singletons[$id]);
    }

    /**
     * @inheritDoc
     *
     * @param class-string $id The service id
     */
    #[Override]
    public function isSingletonInstance(string $id): bool
    {
        return isset($this->instances[$id]);
    }

    /**
     * @inheritDoc
     *
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress ImplementedReturnTypeMismatch
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    #[Override]
    public function get(string $id, array $arguments = []): object
    {
        $this->publishUnpublishedProvided($id);

        // @phpstan-ignore-next-line
        return $this->getSingletonWithoutChecks($id)
            ?? $this->getServiceWithoutChecks($id, $arguments)
            ?? $this->getAliasedWithoutChecks($id, $arguments)
            ?? throw new ContainerInvalidReferenceException($id);
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
            ?? null;
    }

    /**
     * @inheritDoc
     *
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    #[Override]
    public function getAliased(string $id, array $arguments = []): object
    {
        // @phpstan-ignore-next-line
        return $this->getAliasedWithoutChecks($id, $arguments)
            ?? throw new ContainerInvalidReferenceException($id);
    }

    /**
     * @inheritDoc
     *
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    #[Override]
    public function getService(string $id, array $arguments = []): object
    {
        $this->publishUnpublishedProvided($id);

        // @phpstan-ignore-next-line
        return $this->getServiceWithoutChecks($id, $arguments)
            ?? throw new ContainerInvalidReferenceException($id);
    }

    /**
     * @inheritDoc
     *
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    #[Override]
    public function getSingleton(string $id): object
    {
        $this->publishUnpublishedProvided($id);

        // @phpstan-ignore-next-line
        return $this->getSingletonWithoutChecks($id)
            ?? throw new ContainerInvalidReferenceException($id);
    }

    /**
     * Get an aliased service from the container without trying to ensuring published.
     *
     * @param class-string            $id        The service id
     * @param array<array-key, mixed> $arguments [optional] The arguments
     */
    protected function getAliasedWithoutChecks(string $id, array $arguments = []): object|null
    {
        $aliased = $this->getAliasedId($id);

        if ($aliased === null) {
            return null;
        }

        return $this->get($aliased, $arguments);
    }

    /**
     * Get a singleton from the container without trying to get an alias or ensuring published.
     *
     * @param class-string $id The service id
     */
    protected function getSingletonWithoutChecks(string $id): object|null
    {
        $instance = $this->getSingletonInstance($id);

        if ($instance !== null) {
            return $instance;
        }

        if (! $this->isSingletonBinding($id)) {
            return null;
        }

        $singleton = $this->getServiceWithoutChecks($id);

        return is_object($singleton) ? $this->instances[$id] = $singleton : null;
    }

    /**
     * Get a service from the container without trying to get an alias or ensuring published.
     *
     * @param class-string            $id        The service id
     * @param array<array-key, mixed> $arguments [optional] The arguments
     */
    protected function getServiceWithoutChecks(string $id, array $arguments = []): object|null
    {
        $service = $this->getServiceCallable($id);

        if ($service === null) {
            return null;
        }

        // Make the object by dispatching the service
        return $service($this, $arguments);
    }

    /**
     * Validate that an alias does not point at a chain that returns to it.
     *
     * @param class-string $alias The alias being bound
     * @param class-string $id    The id the alias points at
     */
    protected function validateAliasIsNotCyclic(string $alias, string $id): void
    {
        if ($alias === $id) {
            throw new ContainerCyclicAliasException($alias, $id);
        }

        $seen    = [];
        $current = $id;

        while (($aliasedId = $this->getAliasedId($current)) !== null) {
            if ($aliasedId === $alias) {
                throw new ContainerCyclicAliasException($alias, $id);
            }

            // A cycle this alias is no part of would spin here. The sweep below reaches
            // every alias, so the walk that starts inside that cycle throws for it.
            if (isset($seen[$aliasedId])) {
                return;
            }

            $seen[$aliasedId] = true;
            $current          = $aliasedId;
        }
    }

    /**
     * Validate that no alias in the map points at a chain that returns to it.
     */
    protected function validateAliasesAreNotCyclic(): void
    {
        foreach ($this->aliases as $alias => $id) {
            /** @var class-string $alias */
            /** @var class-string $id */
            $this->validateAliasIsNotCyclic($alias, $id);
        }
    }

    /**
     * Get a cached singleton instance for a given id.
     *
     * @param class-string $id The service id
     */
    protected function getSingletonInstance(string $id): object|null
    {
        return $this->instances[$id]
            ?? null;
    }

    /**
     * Get the service class binding for a given id.
     *
     * @param class-string $id The service id
     *
     * @return callable(ContainerContract, array<array-key, mixed>):object|null
     */
    protected function getServiceCallable(string $id): callable|null
    {
        return $this->services[$id]
            ?? null;
    }
}

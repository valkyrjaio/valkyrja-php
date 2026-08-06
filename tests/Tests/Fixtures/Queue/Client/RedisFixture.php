<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Client;

use Override;
use Predis\ClientInterface;
use Predis\Command\CommandInterface;

/**
 * A recording stand-in for a Predis client.
 *
 * Every Redis command reaches a Predis client through `__call`, so recording
 * there captures the whole command surface without stubbing each one.
 */
final class RedisFixture implements ClientInterface
{
    /** @var array<int, array{0: string, 1: array<int, mixed>}> */
    public array $calls = [];

    public bool $connected = false;

    /** @var array<string, mixed> */
    public array $returns = [];

    /**
     * Get the arguments of every call to a command.
     *
     * @return array<int, array<int, mixed>>
     */
    public function getCalls(string $method): array
    {
        $calls = [];

        foreach ($this->calls as [$name, $arguments]) {
            if ($name === $method) {
                $calls[] = $arguments;
            }
        }

        return $calls;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function __call($method, $arguments)
    {
        $this->calls[] = [$method, $arguments];

        return $this->returns[$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function connect(): void
    {
        $this->connected = true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function disconnect(): void
    {
        $this->connected = false;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getCommandFactory(): void
    {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getOptions(): void
    {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getConnection(): void
    {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function createCommand($method, $arguments = []): void
    {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function executeCommand(CommandInterface $command): void
    {
    }
}

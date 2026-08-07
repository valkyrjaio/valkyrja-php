<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Puller;

use JsonException;
use Override;
use Predis\ClientInterface;
use Valkyrja\Queue\Client\Manager\RedisClient;
use Valkyrja\Queue\Client\Puller\Contract\PullerContract;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Support\Time\Microtime;

use function is_array;
use function is_string;

class RedisPuller implements PullerContract
{
    /**
     * @param non-empty-string $queue   The list key jobs are popped from
     * @param int<1, max>      $timeout The blocking pop timeout, in seconds
     */
    public function __construct(
        protected ClientInterface $redis,
        protected string $queue = 'queues:default',
        protected int $timeout = 1,
        protected JobFactoryContract $factory = new JobFactory(),
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function connect(): void
    {
        $this->redis->connect();
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    public function receive(): JobContract|null
    {
        $this->promoteDelayed();

        $popped = $this->redis->blpop([$this->queue], $this->timeout);

        // A blocking pop returns [key, value]; a timeout returns nothing
        if (! is_array($popped) || ! isset($popped[1]) || ! is_string($popped[1])) {
            return null;
        }

        return $this->factory->fromJson($popped[1]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function disconnect(): void
    {
        $this->redis->disconnect();
    }

    /**
     * Move every delayed job whose hold has elapsed onto the ready list.
     */
    protected function promoteDelayed(): void
    {
        $delayedQueue = $this->queue . RedisClient::DELAYED_SUFFIX;

        /** @var mixed $due */
        $due = $this->redis->zrangebyscore($delayedQueue, '-inf', (string) $this->now());

        if (! is_array($due)) {
            return;
        }

        /** @var mixed $envelope */
        foreach ($due as $envelope) {
            if (! is_string($envelope)) {
                continue;
            }

            // Only the worker that wins the removal may enqueue it, so a job
            // cannot be promoted twice by two workers polling at once
            if ($this->redis->zrem($delayedQueue, $envelope) > 0) {
                $this->redis->rpush($this->queue, [$envelope]);
            }
        }
    }

    /**
     * Get the current time in epoch milliseconds.
     *
     * @return int<0, max>
     */
    protected function now(): int
    {
        $now = (int) (Microtime::get() * 1000.0);

        return $now > 0
            ? $now
            : 0;
    }
}

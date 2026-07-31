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
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * A recording stand-in for an AMQP channel.
 *
 * The constructor is bypassed on purpose: a real channel demands a live
 * connection, which is exactly what a unit test must not need.
 */
final class AmqpChannelFixture extends AMQPChannel
{
    /** @var array<int, array{0: string, 1: array<int, mixed>}> */
    public array $calls = [];

    /** @var AMQPMessage|null The next delivery `basic_get` returns */
    public AMQPMessage|null $next = null;

    public bool $closed = false;

    /**
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct()
    {
    }

    /**
     * Get the arguments of every call to a channel method.
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
    public function queue_declare(
        $queue = '',
        $passive = false,
        $durable = false,
        $exclusive = false,
        $auto_delete = true,
        $nowait = false,
        $arguments = [],
        $ticket = null
    ) {
        $this->calls[] = ['queue_declare', [$queue, $passive, $durable, $exclusive, $auto_delete]];

        return [$queue, 0, 0];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function basic_qos($prefetch_size, $prefetch_count, $a_global): void
    {
        $this->calls[] = ['basic_qos', [$prefetch_size, $prefetch_count, $a_global]];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function basic_publish(
        $msg,
        $exchange = '',
        $routing_key = '',
        $mandatory = false,
        $immediate = false,
        $ticket = null
    ): void {
        $this->calls[] = ['basic_publish', [$msg, $exchange, $routing_key]];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function basic_get($queue = '', $no_ack = false, $ticket = null)
    {
        $this->calls[] = ['basic_get', [$queue, $no_ack]];

        $next = $this->next;

        $this->next = null;

        return $next;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function basic_ack($delivery_tag, $multiple = false): void
    {
        $this->calls[] = ['basic_ack', [$delivery_tag, $multiple]];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function basic_nack($delivery_tag, $multiple = false, $requeue = false): void
    {
        $this->calls[] = ['basic_nack', [$delivery_tag, $multiple, $requeue]];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function close($reply_code = 0, $reply_text = '', $method_sig = [0, 0]): void
    {
        $this->calls[] = ['close', []];

        $this->closed = true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function is_open(): bool
    {
        return ! $this->closed;
    }
}

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

use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\Subscription;
use Override;
use Throwable;

/**
 * A recording stand-in for a Pub/Sub subscription.
 *
 * The constructor is bypassed on purpose: a real subscription demands a request
 * handler and a serializer, which is exactly what a unit test must not need.
 */
final class PubSubSubscriptionFixture extends Subscription
{
    /** @var Message[] The deliveries the next pull returns */
    public array $next = [];

    /** @var array<int, array<string, mixed>> Every pull's options, in order */
    public array $pulls = [];

    /** @var Message[] Every message acknowledged, in order */
    public array $acknowledged = [];

    /** @var array<int, array{0: Message, 1: int}> Every deadline change, in order */
    public array $deadlines = [];

    /** @var Throwable|null The failure the next pull raises */
    public Throwable|null $failure = null;

    /**
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct()
    {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function pull(array $options = [])
    {
        $this->pulls[] = $options;

        $failure = $this->failure;

        if ($failure !== null) {
            $this->failure = null;

            throw $failure;
        }

        $next = $this->next;

        $this->next = [];

        return $next;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function acknowledge(Message $message, array $options = []): void
    {
        $this->acknowledged[] = $message;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function modifyAckDeadline(Message $message, $seconds, array $options = []): void
    {
        $this->deadlines[] = [$message, $seconds];
    }
}

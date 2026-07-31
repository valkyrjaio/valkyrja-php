<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * (c) Melech Mizrachi <melechmizrachi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Client;

use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\Subscription;
use Override;

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

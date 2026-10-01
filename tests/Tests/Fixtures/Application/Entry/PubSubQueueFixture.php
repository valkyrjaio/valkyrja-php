<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Application\Entry;

use Google\Cloud\PubSub\Subscription;
use Override;
use Valkyrja\Application\Entry\PubSub\PubSubQueue;
use Valkyrja\Queue\Client\Data\Contract\QueuePubSubClientConfigContract;

/**
 * A Pub/Sub queue entry that pulls from an injected subscription.
 */
final class PubSubQueueFixture extends PubSubQueue
{
    private static Subscription|null $injected = null;

    /**
     * Point the entry at a subscription, as though connect() had opened it.
     *
     * @param int<1, max> $timeoutMs The deadline for one pull, in milliseconds
     */
    public static function inject(Subscription $subscription, int $timeoutMs = 1000): void
    {
        self::$injected         = $subscription;
        self::$subscription     = $subscription;
        self::$timeoutMs        = $timeoutMs;
    }

    /**
     * Drop the subscription and the overrides, so no test leaks into the next.
     */
    public static function reset(): void
    {
        self::$injected         = null;
        self::$subscription     = null;
        self::$current          = null;
        self::$timeoutMs        = 1000;
        self::$subscriptionName = null;
    }

    /**
     * Name the subscription, as an application does on its own entry.
     *
     * @param non-empty-string|null $name The subscription name, or null for the topic's
     */
    public static function nameSubscription(string|null $name): void
    {
        self::$subscriptionName = $name;
    }

    /**
     * Read the resolved subscription name, which is protected on the entry.
     *
     * @return non-empty-string
     */
    public static function readSubscriptionName(QueuePubSubClientConfigContract $config): string
    {
        return self::getSubscriptionName($config);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected static function getSubscription(QueuePubSubClientConfigContract $config): Subscription
    {
        return self::$injected ?? parent::getSubscription($config);
    }
}

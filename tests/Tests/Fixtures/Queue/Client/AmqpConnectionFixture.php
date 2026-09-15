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
use PhpAmqpLib\Connection\AbstractConnection;

/**
 * A stand-in AMQP connection that hands out one recording channel and counts each request for it.
 */
final class AmqpConnectionFixture extends AbstractConnection
{
    public int $channelCount = 0;

    /**
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(
        public AmqpChannelFixture $recordingChannel = new AmqpChannelFixture(),
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function channel($channel_id = null): AMQPChannel
    {
        $this->channelCount++;

        return $this->recordingChannel;
    }
}

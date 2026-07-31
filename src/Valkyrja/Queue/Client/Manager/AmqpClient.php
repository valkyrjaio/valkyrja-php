<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Manager;

use JsonException;
use Override;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Queue\Client\Manager\Abstract\Client;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;

/**
 * Publishes to an AMQP broker.
 *
 * AMQP owns redelivery natively, so this is a processor-owned adapter: a retry
 * is a nack on the consumer side rather than a fresh publish, which is why
 * `republish` here is deliberately not a publish. That asymmetry is the whole
 * difference between the two redelivery models, and it lives in the adapter so
 * the handler and middleware never see it.
 */
class AmqpClient extends Client
{
    /**
     * @param non-empty-string $queue           The queue jobs are published to
     * @param string           $exchange        The exchange to publish through; empty for the default
     * @param non-empty-string $applicationName The application name stamped into the provenance
     * @param non-empty-string $version         The framework version stamped into the provenance
     */
    public function __construct(
        protected AMQPChannel $channel,
        protected string $queue = 'queues.default',
        protected string $exchange = '',
        string $applicationName = 'valkyrja',
        string $version = ApplicationInfo::VERSION,
        protected JobFactoryContract $factory = new JobFactory(),
    ) {
        parent::__construct(
            applicationName: $applicationName,
            version: $version,
        );
    }

    /**
     * Declare the queue this client publishes to.
     *
     * Declaring is idempotent, so a producer may do it safely without racing a
     * consumer that declares the same queue.
     */
    public function declareQueue(): void
    {
        $this->channel->queue_declare($this->queue, false, true, false, false);
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    protected function publish(JobContract $job): void
    {
        $message = new AMQPMessage(
            $this->factory->toJson($job),
            [
                // Survive a broker restart, matching the durable queue
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type'  => 'application/json',
                'priority'      => $this->getPriority($job),
            ]
        );

        $this->channel->basic_publish($message, $this->exchange, $this->queue);
    }

    /**
     * @inheritDoc
     *
     * The broker owns redelivery, so a retry is signalled by the consumer's
     * nack rather than by publishing the job again. Publishing here would
     * duplicate the message: the original delivery is still unacknowledged. The
     * hold is ignored for the same reason — the broker owns its own backoff, so
     * the framework's ramp does not apply to a processor-owned adapter.
     */
    #[Override]
    protected function republish(JobContract $job, int $delayMs = 0): void
    {
    }

    /**
     * Clamp a job's priority into AMQP's unsigned byte range.
     *
     * @return int<0, 255>
     */
    protected function getPriority(JobContract $job): int
    {
        $priority = $job->getPriority();

        if ($priority < 0) {
            return 0;
        }

        return $priority > 255
            ? 255
            : $priority;
    }
}

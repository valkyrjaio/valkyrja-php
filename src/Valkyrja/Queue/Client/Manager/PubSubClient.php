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

namespace Valkyrja\Queue\Client\Manager;

use Google\Cloud\PubSub\Topic;
use JsonException;
use Override;
use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Queue\Client\Manager\Abstract\Client;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;

/**
 * Publishes to Google Cloud Pub/Sub.
 *
 * Pub/Sub owns redelivery through the acknowledgement deadline, so this is a
 * processor-owned adapter: a retry is a deadline change on the consumer side
 * rather than a fresh publish, which is why `republish` here is deliberately
 * not a publish.
 *
 * Pub/Sub has no per-message delay, so a producer's `delay_ms` cannot be
 * honoured here. The value still travels in the envelope, and it is the
 * subscriber's own retry policy that decides when a nacked message comes back.
 */
class PubSubClient extends Client
{
    /**
     * @param non-empty-string $applicationName The application name stamped into the provenance
     * @param non-empty-string $version         The framework version stamped into the provenance
     */
    public function __construct(
        protected Topic $topic,
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
     * @inheritDoc
     *
     * The job name travels as an attribute as well as inside the envelope, so a
     * subscription filter can route on it without reading the body.
     *
     * @throws JsonException
     */
    #[Override]
    protected function publish(JobContract $job): void
    {
        $this->topic->publish([
            'data'       => $this->factory->toJson($job),
            'attributes' => [
                EnvelopeField::NAME => $job->getName(),
                EnvelopeField::ID   => $job->getId(),
            ],
        ]);
    }

    /**
     * @inheritDoc
     *
     * The acknowledgement deadline owns redelivery, so a retry is signalled by
     * the consumer shortening that deadline rather than by publishing the job
     * again. Publishing here would duplicate it: the original delivery is still
     * unacknowledged. The hold is ignored for the same reason — Pub/Sub owns its
     * own backoff, so the framework's ramp does not apply to a processor-owned
     * adapter.
     */
    #[Override]
    protected function republish(JobContract $job, int $delayMs = 0): void
    {
    }
}

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

use AsyncAws\Sqs\SqsClient as Sqs;
use JsonException;
use Override;
use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Queue\Client\Manager\Abstract\Client;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;

use function intdiv;
use function max;
use function min;

class SqsClient extends Client
{
    /**
     * The longest delay SQS accepts on a message, in seconds.
     *
     * A larger delay is not an error the caller can act on, so it is clamped:
     * the job still goes on the queue, at the longest hold the processor has.
     */
    public const int MAX_DELAY_SECONDS = 900;

    /**
     * @param non-empty-string $queueUrl        The queue jobs are published to
     * @param non-empty-string $applicationName The application name stamped into the provenance
     * @param non-empty-string $version         The framework version stamped into the provenance
     */
    public function __construct(
        protected Sqs $sqs,
        protected string $queueUrl,
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
     * @throws JsonException
     */
    #[Override]
    protected function publish(JobContract $job): void
    {
        $this->sqs->sendMessage([
            'QueueUrl'     => $this->queueUrl,
            'MessageBody'  => $this->factory->toJson($job),
            'DelaySeconds' => $this->getDelaySeconds($job->getDelayMs()),
        ]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function republish(JobContract $job, int $delayMs = 0): void
    {
    }

    /**
     * Convert a hold in milliseconds to the whole seconds SQS takes.
     *
     * A sub-second hold rounds down to zero, because SQS has no finer grain.
     *
     * @param int<0, max> $delayMs The hold in milliseconds
     *
     * @return int<0, 900>
     */
    protected function getDelaySeconds(int $delayMs): int
    {
        return max(0, min(intdiv($delayMs, 1000), self::MAX_DELAY_SECONDS));
    }
}

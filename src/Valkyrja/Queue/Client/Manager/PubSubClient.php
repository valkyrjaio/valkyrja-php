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

use Google\Cloud\PubSub\Topic;
use JsonException;
use Override;
use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Queue\Client\Manager\Abstract\Client;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;

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
     */
    #[Override]
    protected function republish(JobContract $job, int $delayMs = 0): void
    {
    }
}

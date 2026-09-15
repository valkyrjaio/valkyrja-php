<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Manager\Abstract;

use Valkyrja\Application\Constant\ApplicationInfo;
use Valkyrja\Application\Entry\Abstract\InternalQueue;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Queue\Client\Requeuer\Contract\RequeuerContract;
use Valkyrja\Queue\Client\Requeuer\Requeuer;
use Valkyrja\Queue\Message\Job\Contract\JobContract;

abstract class InternalClient extends Client
{
    protected ApplicationContract|null $application = null;

    protected ContainerData|null $data = null;

    /**
     * @param class-string<InternalQueue> $entry           The entry whose queue application runs each job
     * @param non-empty-string            $applicationName The application name stamped into the provenance
     * @param non-empty-string            $version         The framework version stamped into the provenance
     */
    public function __construct(
        protected string $entry,
        string $applicationName = 'valkyrja',
        string $version = ApplicationInfo::VERSION,
        protected RequeuerContract $requeuer = new Requeuer(),
    ) {
        parent::__construct(
            applicationName: $applicationName,
            version: $version,
        );
    }

    /**
     * Run a job through the queue application of the entry.
     *
     * The queue application boots on the first job and serves every later one,
     * the same way a worker serves each job that a broker delivers.
     */
    protected function run(JobContract $job, RequeuerContract $requeuer): void
    {
        $entry       = $this->entry;
        $application = $this->application ??= $entry::bootstrap($entry::getConfig());
        $data        = $this->data ??= $application->getContainer()->getSingleton(ContainerData::class);

        $entry::handle(
            app: $application,
            data: $data,
            job: $job,
            client: $this,
            requeuer: $requeuer,
        );
    }
}

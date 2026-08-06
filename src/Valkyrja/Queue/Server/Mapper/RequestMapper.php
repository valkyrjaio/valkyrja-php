<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Server\Mapper;

use JsonException;
use Override;
use Valkyrja\Http\Message\Request\Contract\ServerRequestContract;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Server\Mapper\Contract\RequestMapperContract;

/**
 * Maps a pushed request's body into a job.
 *
 * A push is a normal HTTP request, so only the body matters — the headers are
 * the processor's business, not the envelope's.
 */
class RequestMapper implements RequestMapperContract
{
    public function __construct(
        protected JobFactoryContract $factory = new JobFactory(),
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    public function map(ServerRequestContract $request): JobContract
    {
        return $this->factory->fromJson((string) $request->getBody());
    }
}

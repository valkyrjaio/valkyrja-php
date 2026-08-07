<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Server\Mapper\Contract;

use Valkyrja\Http\Message\Request\Contract\ServerRequestContract;
use Valkyrja\Queue\Message\Job\Contract\JobContract;

interface RequestMapperContract
{
    /**
     * Map a pushed request's body into a job.
     */
    public function map(ServerRequestContract $request): JobContract;
}

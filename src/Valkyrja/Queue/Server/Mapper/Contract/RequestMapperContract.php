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

/**
 * The push-side mapper.
 *
 * This is the one place the Queue and Http modules meet, and the dependency is
 * one-way: the push side depends on Http, never the reverse, so a pull-only
 * deployment loads no Http stack.
 *
 * It takes a *normalized* server request rather than a native runtime one, so
 * every runtime's existing request mapping is reused and the push side leans
 * almost entirely on the Http layer already there.
 */
interface RequestMapperContract
{
    /**
     * Map a pushed request's body into a job.
     */
    public function map(ServerRequestContract $request): JobContract;
}

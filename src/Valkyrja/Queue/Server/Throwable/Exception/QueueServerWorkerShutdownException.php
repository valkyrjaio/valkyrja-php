<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Server\Throwable\Exception;

use Valkyrja\Queue\Server\Throwable\Exception\Abstract\QueueServerRuntimeException;

/**
 * Thrown when a worker is shutting down with a job still in flight.
 *
 * The work was not completed, so the job returns for another worker without
 * being penalized — the attempt budget is for failures, not for a deploy.
 */
class QueueServerWorkerShutdownException extends QueueServerRuntimeException
{
}

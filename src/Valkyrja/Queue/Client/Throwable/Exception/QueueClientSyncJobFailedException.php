<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Client\Throwable\Exception;

use Valkyrja\Queue\Client\Throwable\Exception\Abstract\QueueClientRuntimeException;

/**
 * Thrown when a job pushed through the sync client ends in a terminal failure.
 *
 * A sync push blocks until the job finishes, so the caller is still there to be
 * told. An asynchronous push throws only on an enqueue error, because by the
 * time the job runs the caller has gone. This is the one deliberate difference
 * between the sync client and every other client.
 */
class QueueClientSyncJobFailedException extends QueueClientRuntimeException
{
}

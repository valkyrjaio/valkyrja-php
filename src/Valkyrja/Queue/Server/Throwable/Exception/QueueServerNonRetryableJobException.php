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

use Valkyrja\Queue\Server\Throwable\Contract\QueueServerThrowable;
use Valkyrja\Queue\Throwable\Contract\QueueNonRetryableThrowable;
use Valkyrja\Queue\Throwable\Exception\Abstract\QueueRuntimeException;

/**
 * Thrown by a handler that is giving up on purpose.
 *
 * Retrying a bad payload or a failed validation reproduces the same failure
 * every time, so this dead-letters immediately.
 */
class QueueServerNonRetryableJobException extends QueueRuntimeException implements QueueServerThrowable, QueueNonRetryableThrowable
{
}

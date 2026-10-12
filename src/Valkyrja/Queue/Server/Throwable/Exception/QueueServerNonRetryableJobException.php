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
use Valkyrja\Queue\Throwable\Contract\QueueNonRetryableThrowable;

class QueueServerNonRetryableJobException extends QueueServerRuntimeException implements QueueNonRetryableThrowable
{
}

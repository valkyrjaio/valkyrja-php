<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Middleware\Throwable\Exception\Abstract;

use Valkyrja\Queue\Middleware\Throwable\Contract\QueueMiddlewareThrowable;
use Valkyrja\Queue\Throwable\Exception\Abstract\QueueInvalidArgumentException;

abstract class QueueMiddlewareInvalidArgumentException extends QueueInvalidArgumentException implements QueueMiddlewareThrowable
{
}

<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Server\Throwable\Exception\Abstract;

use Valkyrja\Queue\Server\Throwable\Contract\QueueServerThrowable;
use Valkyrja\Queue\Throwable\Exception\Abstract\QueueInvalidArgumentException;

abstract class QueueServerInvalidArgumentException extends QueueInvalidArgumentException implements QueueServerThrowable
{
}

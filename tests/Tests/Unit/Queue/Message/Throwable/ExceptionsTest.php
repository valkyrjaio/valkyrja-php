<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Message\Throwable;

use Throwable;
use Valkyrja\Queue\Message\Throwable\Contract\QueueMessageThrowable;
use Valkyrja\Queue\Message\Throwable\Exception\Abstract\QueueMessageInvalidArgumentException;
use Valkyrja\Queue\Message\Throwable\Exception\Abstract\QueueMessageRuntimeException;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidAttributeNameException;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidEnvelopeException;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidPayloadParamException;
use Valkyrja\Queue\Throwable\Contract\QueueThrowable;
use Valkyrja\Queue\Throwable\Exception\Abstract\QueueInvalidArgumentException;
use Valkyrja\Queue\Throwable\Exception\Abstract\QueueRuntimeException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class ExceptionsTest extends TestCase
{
    public function testThrowable(): void
    {
        self::isA(Throwable::class, QueueMessageThrowable::class);
        self::isA(QueueThrowable::class, QueueMessageThrowable::class);
    }

    public function testInvalidArgumentException(): void
    {
        self::isA(QueueMessageThrowable::class, QueueMessageInvalidArgumentException::class);
        self::isA(QueueInvalidArgumentException::class, QueueMessageInvalidArgumentException::class);
    }

    public function testRuntimeException(): void
    {
        self::isA(QueueMessageThrowable::class, QueueMessageRuntimeException::class);
        self::isA(QueueRuntimeException::class, QueueMessageRuntimeException::class);
    }

    public function testInvalidAttributeNameException(): void
    {
        self::isA(QueueMessageInvalidArgumentException::class, QueueMessageInvalidAttributeNameException::class);
    }

    public function testInvalidEnvelopeException(): void
    {
        self::isA(QueueMessageInvalidArgumentException::class, QueueMessageInvalidEnvelopeException::class);
    }

    public function testInvalidPayloadParamException(): void
    {
        self::isA(QueueMessageInvalidArgumentException::class, QueueMessageInvalidPayloadParamException::class);
    }
}

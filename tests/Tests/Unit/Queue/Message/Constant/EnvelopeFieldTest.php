<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Message\Constant;

use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class EnvelopeFieldTest extends TestCase
{
    public function testFieldNames(): void
    {
        self::assertSame('id', EnvelopeField::ID);
        self::assertSame('name', EnvelopeField::NAME);
        self::assertSame('producer', EnvelopeField::PRODUCER);
        self::assertSame('attributes', EnvelopeField::ATTRIBUTES);
        self::assertSame('attempts', EnvelopeField::ATTEMPTS);
        self::assertSame('max_attempts', EnvelopeField::MAX_ATTEMPTS);
        self::assertSame('priority', EnvelopeField::PRIORITY);
        self::assertSame('delay_ms', EnvelopeField::DELAY_MS);
        self::assertSame('retry_delay_ms', EnvelopeField::RETRY_DELAY_MS);
        self::assertSame('retry_delay_multiply_by_attempt', EnvelopeField::RETRY_DELAY_MULTIPLY_BY_ATTEMPT);
        self::assertSame('enqueued_at_ms', EnvelopeField::ENQUEUED_AT_MS);
        self::assertSame('enqueued_at_iso', EnvelopeField::ENQUEUED_AT_ISO);
        self::assertSame('modified_at_ms', EnvelopeField::MODIFIED_AT_MS);
        self::assertSame('modified_at_iso', EnvelopeField::MODIFIED_AT_ISO);
        self::assertSame('payload', EnvelopeField::PAYLOAD);
    }
}

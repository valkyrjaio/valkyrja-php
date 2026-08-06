<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Message\Constant;

/**
 * The wire envelope's field names.
 *
 * The envelope is the cross-language interop contract: a job published by this
 * port must run unchanged on every other port, so these keys are `snake_case`
 * on the wire regardless of each port's native casing.
 */
final class EnvelopeField
{
    /** @var non-empty-string */
    public const string ID = 'id';
    /** @var non-empty-string */
    public const string NAME = 'name';
    /** @var non-empty-string */
    public const string PRODUCER = 'producer';
    /** @var non-empty-string */
    public const string ATTRIBUTES = 'attributes';
    /** @var non-empty-string */
    public const string ATTEMPTS = 'attempts';
    /** @var non-empty-string */
    public const string MAX_ATTEMPTS = 'max_attempts';
    /** @var non-empty-string */
    public const string PRIORITY = 'priority';
    /** @var non-empty-string */
    public const string DELAY_MS = 'delay_ms';
    /** @var non-empty-string */
    public const string RETRY_DELAY_MS = 'retry_delay_ms';
    /** @var non-empty-string */
    public const string RETRY_DELAY_MULTIPLY_BY_ATTEMPT = 'retry_delay_multiply_by_attempt';
    /** @var non-empty-string */
    public const string ENQUEUED_AT_MS = 'enqueued_at_ms';
    /** @var non-empty-string */
    public const string ENQUEUED_AT_ISO = 'enqueued_at_iso';
    /** @var non-empty-string */
    public const string MODIFIED_AT_MS = 'modified_at_ms';
    /** @var non-empty-string */
    public const string MODIFIED_AT_ISO = 'modified_at_iso';
    /** @var non-empty-string */
    public const string PAYLOAD = 'payload';
}

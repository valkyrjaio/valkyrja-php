<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Queue\Message\Job\Factory\Contract;

use JsonException;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Payload\Contract\PayloadContract;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidEnvelopeException;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidPayloadParamException;

interface JobFactoryContract
{
    /**
     * Build a job for the given name and body.
     *
     * Ergonomic construction lives on the factory rather than on the message,
     * which stays a data object, and rather than on the client, which stays
     * single-purpose: ship a job.
     *
     * @param non-empty-string                        $name    The routing key
     * @param PayloadContract|array<array-key, mixed> $payload The body
     *
     * @throws QueueMessageInvalidEnvelopeException
     * @throws QueueMessageInvalidPayloadParamException
     */
    public function create(string $name, PayloadContract|array $payload = []): JobContract;

    /**
     * Build a job from a decoded envelope.
     *
     * Unknown top-level fields are ignored and any field an older producer did
     * not send is defaulted, so the contract can gain fields over time without
     * breaking older producers.
     *
     * The `payload` field takes any of three shapes: a `PayloadContract` the
     * caller already built, which is used as it stands; an object decoded from
     * JSON without associative arrays; or a plain array. Any other object in
     * that field is ignored, and one nested anywhere inside the payload raises
     * the envelope throwable, because casting either would put that object's
     * own properties on the wire.
     *
     * @param array<array-key, mixed> $data The decoded envelope
     *
     * @throws QueueMessageInvalidEnvelopeException
     */
    public function fromArray(array $data): JobContract;

    /**
     * Build a job from an encoded envelope.
     *
     * @param string $json The encoded envelope
     *
     * @throws JsonException
     * @throws QueueMessageInvalidEnvelopeException
     */
    public function fromJson(string $json): JobContract;

    /**
     * Encode a job as the wire envelope.
     *
     * @throws JsonException
     */
    public function toJson(JobContract $job): string;
}

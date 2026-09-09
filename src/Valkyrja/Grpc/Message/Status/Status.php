<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Status;

use Override;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Message\Enum\StatusCode;
use Valkyrja\Grpc\Message\Status\Contract\StatusContract;

class Status implements StatusContract
{
    /** @var non-empty-string */
    protected string $message;

    /**
     * @param non-empty-string|null $message The message, defaulting to the code's own
     * @param string|null           $details The `google.rpc.Status` protobuf bytes
     */
    public function __construct(
        protected StatusCode $code = StatusCode::OK,
        string|null $message = null,
        protected string|null $details = null,
    ) {
        $this->message = $message ?? $code->getDefaultMessage();
    }

    /**
     * Create a status for the given code.
     *
     * @param non-empty-string|null $message The message
     */
    public static function of(StatusCode $code, string|null $message = null, string|null $details = null): self
    {
        return new self(code: $code, message: $message, details: $details);
    }

    /**
     * Create an OK status.
     */
    public static function ok(): self
    {
        return new self(code: StatusCode::OK);
    }

    /**
     * Create a CANCELLED status.
     *
     * @param non-empty-string|null $message The message
     */
    public static function cancelled(string|null $message = null): self
    {
        return self::of(code: StatusCode::CANCELLED, message: $message);
    }

    /**
     * Create an UNKNOWN status.
     *
     * @param non-empty-string|null $message The message
     */
    public static function unknown(string|null $message = null): self
    {
        return self::of(code: StatusCode::UNKNOWN, message: $message);
    }

    /**
     * Create an INVALID_ARGUMENT status.
     *
     * @param non-empty-string|null $message The message
     */
    public static function invalidArgument(string|null $message = null): self
    {
        return self::of(code: StatusCode::INVALID_ARGUMENT, message: $message);
    }

    /**
     * Create a DEADLINE_EXCEEDED status.
     *
     * @param non-empty-string|null $message The message
     */
    public static function deadlineExceeded(string|null $message = null): self
    {
        return self::of(code: StatusCode::DEADLINE_EXCEEDED, message: $message);
    }

    /**
     * Create the status for a cancellation reason.
     *
     * DEADLINE_EXCEEDED maps to that status. Every other reason, and an unknown reason, maps to
     * CANCELLED.
     */
    public static function forReason(CancellationReason|null $reason): self
    {
        return $reason === CancellationReason::DEADLINE_EXCEEDED
            ? self::deadlineExceeded()
            : self::cancelled();
    }

    /**
     * Create a NOT_FOUND status.
     *
     * @param non-empty-string|null $message The message
     */
    public static function notFound(string|null $message = null): self
    {
        return self::of(code: StatusCode::NOT_FOUND, message: $message);
    }

    /**
     * Create an ALREADY_EXISTS status.
     *
     * @param non-empty-string|null $message The message
     */
    public static function alreadyExists(string|null $message = null): self
    {
        return self::of(code: StatusCode::ALREADY_EXISTS, message: $message);
    }

    /**
     * Create a PERMISSION_DENIED status.
     *
     * @param non-empty-string|null $message The message
     */
    public static function permissionDenied(string|null $message = null): self
    {
        return self::of(code: StatusCode::PERMISSION_DENIED, message: $message);
    }

    /**
     * Create a RESOURCE_EXHAUSTED status.
     *
     * @param non-empty-string|null $message The message
     */
    public static function resourceExhausted(string|null $message = null): self
    {
        return self::of(code: StatusCode::RESOURCE_EXHAUSTED, message: $message);
    }

    /**
     * Create a FAILED_PRECONDITION status.
     *
     * @param non-empty-string|null $message The message
     */
    public static function failedPrecondition(string|null $message = null): self
    {
        return self::of(code: StatusCode::FAILED_PRECONDITION, message: $message);
    }

    /**
     * Create an ABORTED status.
     *
     * @param non-empty-string|null $message The message
     */
    public static function aborted(string|null $message = null): self
    {
        return self::of(code: StatusCode::ABORTED, message: $message);
    }

    /**
     * Create an OUT_OF_RANGE status.
     *
     * @param non-empty-string|null $message The message
     */
    public static function outOfRange(string|null $message = null): self
    {
        return self::of(code: StatusCode::OUT_OF_RANGE, message: $message);
    }

    /**
     * Create an UNIMPLEMENTED status.
     *
     * @param non-empty-string|null $message The message
     */
    public static function unimplemented(string|null $message = null): self
    {
        return self::of(code: StatusCode::UNIMPLEMENTED, message: $message);
    }

    /**
     * Create an INTERNAL status.
     *
     * @param non-empty-string|null $message The message
     * @param string|null           $details The `google.rpc.Status` protobuf bytes
     */
    public static function internal(string|null $message = null, string|null $details = null): self
    {
        return self::of(code: StatusCode::INTERNAL, message: $message, details: $details);
    }

    /**
     * Create an UNAVAILABLE status.
     *
     * @param non-empty-string|null $message The message
     */
    public static function unavailable(string|null $message = null): self
    {
        return self::of(code: StatusCode::UNAVAILABLE, message: $message);
    }

    /**
     * Create a DATA_LOSS status.
     *
     * @param non-empty-string|null $message The message
     */
    public static function dataLoss(string|null $message = null): self
    {
        return self::of(code: StatusCode::DATA_LOSS, message: $message);
    }

    /**
     * Create an UNAUTHENTICATED status.
     *
     * @param non-empty-string|null $message The message
     */
    public static function unauthenticated(string|null $message = null): self
    {
        return self::of(code: StatusCode::UNAUTHENTICATED, message: $message);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getCode(): StatusCode
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getDetails(): string|null
    {
        return $this->details;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasDetails(): bool
    {
        return $this->details !== null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isOk(): bool
    {
        return $this->code->isOk();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isCancellation(): bool
    {
        return $this->code->isCancellation();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withCode(StatusCode $code): static
    {
        $new = clone $this;

        $new->code = $code;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withMessage(string $message): static
    {
        $new = clone $this;

        $new->message = $message;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withDetails(string|null $details): static
    {
        $new = clone $this;

        $new->details = $details;

        return $new;
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Response;

use Override;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Message\Metadata\Contract\MetadataContract;
use Valkyrja\Grpc\Message\Metadata\Metadata;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Message\Status\Contract\StatusContract;
use Valkyrja\Grpc\Message\Status\Status;

class ServiceResponse implements ServiceResponseContract
{
    /**
     * @param iterable<array-key, mixed> $messages The messages
     */
    public function __construct(
        protected StatusContract $status = new Status(),
        protected MetadataContract $initialMetadata = new Metadata(),
        protected MetadataContract $trailingMetadata = new Metadata(),
        protected iterable $messages = [],
    ) {
    }

    /**
     * Create a response for the given status.
     */
    public static function of(StatusContract $status): self
    {
        return new self(status: $status);
    }

    /**
     * Create an OK response, optionally carrying a single message.
     */
    public static function ok(mixed $message = null): self
    {
        return new self(
            status: Status::ok(),
            messages: $message === null
                ? []
                : [$message]
        );
    }

    /**
     * Create an UNIMPLEMENTED response — the framework's default terminal for an unmatched method.
     *
     * @param non-empty-string|null $message The message
     */
    public static function unimplemented(string|null $message = null): self
    {
        return new self(status: Status::unimplemented($message));
    }

    /**
     * Create a cancellation response for the given reason.
     */
    public static function cancelled(CancellationReason|null $reason = null): self
    {
        return new self(status: Status::forReason($reason));
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getStatus(): StatusContract
    {
        return $this->status;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withStatus(StatusContract $status): static
    {
        $new = clone $this;

        $new->status = $status;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getInitialMetadata(): MetadataContract
    {
        return $this->initialMetadata;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withInitialMetadata(MetadataContract $metadata): static
    {
        $new = clone $this;

        $new->initialMetadata = $metadata;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getTrailingMetadata(): MetadataContract
    {
        return $this->trailingMetadata;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withTrailingMetadata(MetadataContract $metadata): static
    {
        $new = clone $this;

        $new->trailingMetadata = $metadata;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMessages(): iterable
    {
        return $this->messages;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withMessages(iterable $messages): static
    {
        $new = clone $this;

        $new->messages = $messages;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isCancellation(): bool
    {
        return $this->status->isCancellation();
    }
}

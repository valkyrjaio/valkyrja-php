<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Grpc\Server;

use Override;
use Throwable;
use Valkyrja\Grpc\Message\Metadata\Contract\MetadataContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Message\Stream\Contract\OutboundStreamContract;

/**
 * Records what a streaming-model call writes to the wire, so a test can assert the open/emit/close
 * ordering an adapter would otherwise have to be stood up to observe.
 */
final class OutboundStreamFixture implements OutboundStreamContract
{
    /** @var list<string> */
    public array $events = [];

    /** @var list<mixed> */
    public array $messages = [];

    public MetadataContract|null $headers = null;

    public ServiceResponseContract|null $terminal = null;

    /** The throwable `close()` raises after recording, so a test can drive the wire-close failure */
    public Throwable|null $throwOnClose = null;

    /**
     * @inheritDoc
     */
    #[Override]
    public function sendHeaders(MetadataContract $initialMetadata): void
    {
        $this->events[] = 'headers';
        $this->headers  = $initialMetadata;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function sendMessage(mixed $message): void
    {
        $this->events[]   = 'message';
        $this->messages[] = $message;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function close(ServiceResponseContract $terminal): void
    {
        $this->events[] = 'close';
        $this->terminal = $terminal;

        if ($this->throwOnClose !== null) {
            throw $this->throwOnClose;
        }
    }
}

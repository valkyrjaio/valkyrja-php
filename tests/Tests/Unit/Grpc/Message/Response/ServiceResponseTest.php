<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Message\Response;

use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Message\Enum\StatusCode;
use Valkyrja\Grpc\Message\Metadata\Metadata;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Message\Status\Status;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class ServiceResponseTest extends TestCase
{
    public function testDefaults(): void
    {
        $response = new ServiceResponse();

        self::assertSame(StatusCode::OK, $response->getStatus()->getCode());
        self::assertSame([], $response->getInitialMetadata()->toArray());
        self::assertSame([], $response->getTrailingMetadata()->toArray());
        self::assertSame([], $response->getMessages());
        self::assertFalse($response->isCancellation());
    }

    public function testWithStatus(): void
    {
        $response = new ServiceResponse();
        $new      = $response->withStatus(Status::notFound());

        self::assertNotSame($response, $new);
        self::assertSame(StatusCode::OK, $response->getStatus()->getCode());
        self::assertSame(StatusCode::NOT_FOUND, $new->getStatus()->getCode());
    }

    public function testWithInitialMetadata(): void
    {
        $metadata = new Metadata(['x' => ['one']]);
        $response = new ServiceResponse();
        $new      = $response->withInitialMetadata($metadata);

        self::assertNotSame($response, $new);
        self::assertSame([], $response->getInitialMetadata()->toArray());
        self::assertSame($metadata, $new->getInitialMetadata());
    }

    public function testWithTrailingMetadata(): void
    {
        $metadata = new Metadata(['x' => ['one']]);
        $response = new ServiceResponse();
        $new      = $response->withTrailingMetadata($metadata);

        self::assertNotSame($response, $new);
        self::assertSame([], $response->getTrailingMetadata()->toArray());
        self::assertSame($metadata, $new->getTrailingMetadata());
    }

    public function testWithMessages(): void
    {
        $response = new ServiceResponse();
        $new      = $response->withMessages(['one', 'two']);

        self::assertNotSame($response, $new);
        self::assertSame([], $response->getMessages());
        self::assertSame(['one', 'two'], $new->getMessages());
    }

    public function testOf(): void
    {
        $status = Status::aborted();

        self::assertSame($status, ServiceResponse::of($status)->getStatus());
    }

    public function testOkWithoutAMessage(): void
    {
        $response = ServiceResponse::ok();

        self::assertSame(StatusCode::OK, $response->getStatus()->getCode());
        self::assertSame([], $response->getMessages());
    }

    public function testOkWithAMessage(): void
    {
        $response = ServiceResponse::ok('payload');

        self::assertSame(StatusCode::OK, $response->getStatus()->getCode());
        self::assertSame(['payload'], $response->getMessages());
    }

    public function testUnimplemented(): void
    {
        $response = ServiceResponse::unimplemented();

        self::assertSame(StatusCode::UNIMPLEMENTED, $response->getStatus()->getCode());
        self::assertSame('Unimplemented', $response->getStatus()->getMessage());
        self::assertSame('custom', ServiceResponse::unimplemented('custom')->getStatus()->getMessage());
    }

    public function testCancelledWithoutAReason(): void
    {
        $response = ServiceResponse::cancelled();

        self::assertSame(StatusCode::CANCELLED, $response->getStatus()->getCode());
        self::assertTrue($response->isCancellation());
    }

    public function testCancelledForAClientCancellation(): void
    {
        $response = ServiceResponse::cancelled(CancellationReason::CLIENT_CANCELLED);

        self::assertSame(StatusCode::CANCELLED, $response->getStatus()->getCode());
    }

    public function testCancelledForAnElapsedDeadline(): void
    {
        $response = ServiceResponse::cancelled(CancellationReason::DEADLINE_EXCEEDED);

        self::assertSame(StatusCode::DEADLINE_EXCEEDED, $response->getStatus()->getCode());
        self::assertTrue($response->isCancellation());
    }
}

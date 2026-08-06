<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Support;

use Valkyrja\Grpc\Message\Call\ServiceCall;
use Valkyrja\Grpc\Message\Cancellation\CancellationToken;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Message\Enum\StatusCode;
use Valkyrja\Grpc\Message\Metadata\Metadata;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Message\Status\Status;
use Valkyrja\Grpc\Support\Cancellation;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class CancellationTest extends TestCase
{
    public function testContinuesWhenNothingIsCancelled(): void
    {
        $call = new ServiceCall('/pkg.Service/Method');

        self::assertNull(Cancellation::checkAndFinalize($call));
        self::assertNull(Cancellation::checkAndFinalize($call, ServiceResponse::ok()));
    }

    public function testBuildsAFreshResponseWhenNoneExists(): void
    {
        $cancellation = new CancellationToken();
        $cancellation->cancel(CancellationReason::CLIENT_CANCELLED);

        $call = new ServiceCall('/pkg.Service/Method', cancellation: $cancellation);

        $response = Cancellation::checkAndFinalize($call);

        self::assertNotNull($response);
        self::assertSame(StatusCode::CANCELLED, $response->getStatus()->getCode());
    }

    public function testBuildsADeadlineExceededResponse(): void
    {
        $cancellation = new CancellationToken();
        $cancellation->cancel(CancellationReason::DEADLINE_EXCEEDED);

        $call = new ServiceCall('/pkg.Service/Method', cancellation: $cancellation);

        $response = Cancellation::checkAndFinalize($call);

        self::assertNotNull($response);
        self::assertSame(StatusCode::DEADLINE_EXCEEDED, $response->getStatus()->getCode());
    }

    public function testOverlaysTheStatusOnAnExistingResponse(): void
    {
        $cancellation = new CancellationToken();
        $cancellation->cancel(CancellationReason::CLIENT_CANCELLED);

        $call     = new ServiceCall('/pkg.Service/Method', cancellation: $cancellation);
        $metadata = new Metadata(['x' => ['accumulated']]);

        $existing = ServiceResponse::ok('payload')->withTrailingMetadata($metadata);

        $response = Cancellation::checkAndFinalize($call, $existing);

        self::assertNotNull($response);
        self::assertSame(StatusCode::CANCELLED, $response->getStatus()->getCode());
        // Metadata accumulated by middleware that did manage to run is preserved.
        self::assertSame($metadata, $response->getTrailingMetadata());
        self::assertSame(['payload'], $response->getMessages());
    }

    public function testPassesAnAlreadyCancelledResponseThroughUnchanged(): void
    {
        $call     = new ServiceCall('/pkg.Service/Method');
        $existing = ServiceResponse::of(Status::deadlineExceeded('already'));

        self::assertSame($existing, Cancellation::checkAndFinalize($call, $existing));
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Server\Mapper;

use Valkyrja\Http\Message\Header\Collection\HeaderCollection;
use Valkyrja\Http\Message\Header\Header;
use Valkyrja\Http\Message\Request\ServerRequest;
use Valkyrja\Http\Message\Stream\Stream;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Server\Mapper\RequestMapper;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function json_encode;

final class RequestMapperTest extends TestCase
{
    public function testMapsTheBodyIntoAJob(): void
    {
        $job = new RequestMapper()->map($this->request([
            EnvelopeField::NAME     => 'SendWelcomeEmail',
            EnvelopeField::ID       => 'pushed-id',
            EnvelopeField::ATTEMPTS => 2,
            EnvelopeField::PAYLOAD  => ['user_id' => 42],
        ]));

        self::assertSame('SendWelcomeEmail', $job->getName());
        self::assertSame('pushed-id', $job->getId());
        self::assertSame(2, $job->getAttempts());
        self::assertSame(['user_id' => 42], $job->getPayload()->asArray());
    }

    public function testIgnoresTheHeaders(): void
    {
        // A push is a normal HTTP request; only the body is the envelope
        $request = $this->request([EnvelopeField::NAME => 'SendWelcomeEmail'])
            ->withHeaders(new HeaderCollection(new Header('X-Job-Name', 'SomethingElse')));

        self::assertSame('SendWelcomeEmail', new RequestMapper()->map($request)->getName());
    }

    /**
     * @param array<non-empty-string, mixed> $envelope
     */
    protected function request(array $envelope): ServerRequest
    {
        $body = new Stream();
        $body->write((string) json_encode($envelope));
        $body->rewind();

        return new ServerRequest(body: $body);
    }
}

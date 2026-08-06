<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Application\Entry;

use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Application\Entry\PushQueue;
use Valkyrja\Http\Message\Enum\StatusCode;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the PushQueue entry.
 */
final class PushQueueTest extends TestCase
{
    /**
     * @return array<string, array{JobResult, StatusCode}>
     */
    public static function statusProvider(): array
    {
        return [
            'ack deletes'             => [JobResult::ACK, StatusCode::NO_CONTENT],
            'retry redelivers'        => [JobResult::RETRY, StatusCode::SERVICE_UNAVAILABLE],
            'fail is terminal'        => [JobResult::FAIL, StatusCode::UNPROCESSABLE_ENTITY],
            'dead letter is terminal' => [JobResult::DEAD_LETTER, StatusCode::UNPROCESSABLE_ENTITY],
        ];
    }

    /**
     * The processor reads the response status as the settlement, so this
     * mapping is the entire push-side settlement contract.
     */
    #[DataProvider('statusProvider')]
    public function testRespond(JobResult $result, StatusCode $expected): void
    {
        self::assertSame($expected, PushQueue::respond($result)->getStatusCode());
    }

    public function testEveryAcknowledgementIsATwoHundred(): void
    {
        self::assertLessThan(300, PushQueue::respond(JobResult::ACK)->getStatusCode()->value);
    }

    public function testEveryFailureIsNotATwoHundred(): void
    {
        foreach ([JobResult::RETRY, JobResult::FAIL, JobResult::DEAD_LETTER] as $result) {
            self::assertGreaterThanOrEqual(300, PushQueue::respond($result)->getStatusCode()->value);
        }
    }
}

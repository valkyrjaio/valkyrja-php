<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Message\Enum;

use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class JobResultTest extends TestCase
{
    /**
     * @return array<string, array{JobResult, non-empty-string}>
     */
    public static function valueProvider(): array
    {
        return [
            'ack'         => [JobResult::ACK, 'ack'],
            'retry'       => [JobResult::RETRY, 'retry'],
            'fail'        => [JobResult::FAIL, 'fail'],
            'dead letter' => [JobResult::DEAD_LETTER, 'dead_letter'],
        ];
    }

    /**
     * @return array<string, array{JobResult, bool}>
     */
    public static function terminalProvider(): array
    {
        return [
            'ack'         => [JobResult::ACK, true],
            'retry'       => [JobResult::RETRY, false],
            'fail'        => [JobResult::FAIL, true],
            'dead letter' => [JobResult::DEAD_LETTER, true],
        ];
    }

    /**
     * @return array<string, array{JobResult, bool}>
     */
    public static function deadLetteredProvider(): array
    {
        return [
            'ack'         => [JobResult::ACK, false],
            'retry'       => [JobResult::RETRY, false],
            'fail'        => [JobResult::FAIL, true],
            'dead letter' => [JobResult::DEAD_LETTER, true],
        ];
    }

    /**
     * @param non-empty-string $expected
     */
    #[DataProvider('valueProvider')]
    public function testValue(JobResult $result, string $expected): void
    {
        self::assertSame($expected, $result->value);
        self::assertSame($result, JobResult::from($expected));
    }

    #[DataProvider('terminalProvider')]
    public function testIsTerminal(JobResult $result, bool $expected): void
    {
        self::assertSame($expected, $result->isTerminal());
    }

    #[DataProvider('deadLetteredProvider')]
    public function testIsDeadLettered(JobResult $result, bool $expected): void
    {
        self::assertSame($expected, $result->isDeadLettered());
    }

    public function testCases(): void
    {
        self::assertCount(4, JobResult::cases());
    }
}

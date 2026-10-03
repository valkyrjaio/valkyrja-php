<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Message\Payload;

use stdClass;
use Valkyrja\Queue\Message\Payload\Contract\PayloadContract;
use Valkyrja\Queue\Message\Payload\Payload;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidPayloadParamException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class PayloadTest extends TestCase
{
    public function testDefaults(): void
    {
        $payload = new Payload();

        self::assertSame([], $payload->getAll());
        self::assertSame([], $payload->asArray());
        self::assertFalse($payload->has('test'));
        self::assertNull($payload->get('test'));
    }

    public function testConstructor(): void
    {
        $payload = new Payload(['user_id' => 42, 'name' => 'test', 'active' => true, 'score' => 1.5, 'none' => null]);

        self::assertTrue($payload->has('user_id'));
        self::assertSame(42, $payload->get('user_id'));
        self::assertSame('test', $payload->get('name'));
        self::assertTrue($payload->get('active'));
        self::assertSame(1.5, $payload->get('score'));
        self::assertNull($payload->get('none'));
    }

    public function testNullIsPresentButNotSet(): void
    {
        $payload = new Payload(['none' => null]);

        // isset() semantics: a null param reads as absent, matching Http's param collections
        self::assertFalse($payload->has('none'));
        self::assertArrayHasKey('none', $payload->getAll());
    }

    public function testConstructorRejectsInvalidParam(): void
    {
        $this->expectException(QueueMessageInvalidPayloadParamException::class);

        /* @phpstan-ignore-next-line */
        new Payload(['invalid' => new stdClass()]);
    }

    public function testFromArrayNestsRecursively(): void
    {
        $payload = Payload::fromArray(['user' => ['id' => 42, 'tags' => ['a', 'b']]]);

        $user = $payload->get('user');

        self::assertInstanceOf(PayloadContract::class, $user);
        self::assertSame(42, $user->get('id'));

        $tags = $user->get('tags');

        self::assertInstanceOf(PayloadContract::class, $tags);
        self::assertSame('a', $tags->get(0));
        self::assertSame('b', $tags->get(1));
    }

    public function testFromArrayRejectsInvalidParam(): void
    {
        $this->expectException(QueueMessageInvalidPayloadParamException::class);

        Payload::fromArray(['invalid' => new stdClass()]);
    }

    public function testToArrayFlattensRecursively(): void
    {
        $data = ['user' => ['id' => 42, 'tags' => ['a', 'b']], 'top' => 'level'];

        self::assertSame($data, Payload::fromArray($data)->asArray());
    }

    public function testGetOnly(): void
    {
        $payload = new Payload(['a' => 1, 'b' => 2, 'c' => 3]);

        self::assertSame(['a' => 1, 'c' => 3], $payload->getOnly('a', 'c'));
    }

    public function testGetAllExcept(): void
    {
        $payload = new Payload(['a' => 1, 'b' => 2, 'c' => 3]);

        self::assertSame(['b' => 2], $payload->getAllExcept('a', 'c'));
    }

    public function testWith(): void
    {
        $payload = new Payload(['a' => 1]);
        $new     = $payload->with(['b' => 2]);

        self::assertNotSame($payload, $new);
        self::assertSame(['a' => 1], $payload->getAll());
        self::assertSame(['b' => 2], $new->getAll());
    }

    public function testWithRejectsInvalidParam(): void
    {
        $this->expectException(QueueMessageInvalidPayloadParamException::class);

        /* @phpstan-ignore-next-line */
        new Payload()->with(['invalid' => new stdClass()]);
    }

    public function testWithAdded(): void
    {
        $payload = new Payload(['a' => 1]);
        $new     = $payload->withAdded(['b' => 2]);

        self::assertNotSame($payload, $new);
        self::assertSame(['a' => 1], $payload->getAll());
        self::assertSame(['a' => 1, 'b' => 2], $new->getAll());
    }

    public function testWithAddedRejectsInvalidParam(): void
    {
        $this->expectException(QueueMessageInvalidPayloadParamException::class);

        /* @phpstan-ignore-next-line */
        new Payload()->withAdded(['invalid' => new stdClass()]);
    }

    public function testWithAddedPreservesIntegerKeys(): void
    {
        $payload = new Payload([0 => 'a', 'named' => 'b'])->withAdded([1 => 'c']);

        self::assertSame([0 => 'a', 'named' => 'b', 1 => 'c'], $payload->getAll());
    }

    public function testNestedPayloadIsAValidParam(): void
    {
        $nested  = new Payload(['id' => 1]);
        $payload = new Payload(['user' => $nested]);

        self::assertSame($nested, $payload->get('user'));
    }
}

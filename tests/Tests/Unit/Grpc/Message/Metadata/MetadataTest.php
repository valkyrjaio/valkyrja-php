<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Message\Metadata;

use Valkyrja\Grpc\Message\Metadata\Metadata;
use Valkyrja\Grpc\Throwable\Exception\MetadataInvalidKeyException;
use Valkyrja\Grpc\Throwable\Exception\MetadataInvalidValueException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function iterator_to_array;

final class MetadataTest extends TestCase
{
    public function testEmptyByDefault(): void
    {
        $metadata = new Metadata();

        self::assertSame([], $metadata->toArray());
        self::assertNull($metadata->get('anything'));
        self::assertSame([], $metadata->getAll('anything'));
        self::assertFalse($metadata->has('anything'));
    }

    public function testConstructNormalizesKeys(): void
    {
        $metadata = new Metadata(['Content-Type' => ['application/grpc']]);

        self::assertSame(['content-type' => ['application/grpc']], $metadata->toArray());
        self::assertTrue($metadata->has('CONTENT-TYPE'));
        self::assertSame('application/grpc', $metadata->get('Content-Type'));
    }

    public function testGetReturnsTheFirstValue(): void
    {
        $metadata = new Metadata(['x' => ['one', 'two']]);

        self::assertSame('one', $metadata->get('x'));
        self::assertSame(['one', 'two'], $metadata->getAll('x'));
    }

    public function testGetOnAnEmptyValueList(): void
    {
        $metadata = new Metadata(['x' => []]);

        self::assertNull($metadata->get('x'));
        self::assertTrue($metadata->has('x'));
    }

    public function testIsBinaryKey(): void
    {
        $metadata = new Metadata();

        self::assertTrue($metadata->isBinaryKey('trace-bin'));
        self::assertTrue($metadata->isBinaryKey('TRACE-BIN'));
        self::assertFalse($metadata->isBinaryKey('trace'));
    }

    public function testWithReplaces(): void
    {
        $metadata = new Metadata(['x' => ['one', 'two']]);
        $new      = $metadata->with('X', 'three');

        self::assertNotSame($metadata, $new);
        self::assertSame(['one', 'two'], $metadata->getAll('x'));
        self::assertSame(['three'], $new->getAll('x'));
    }

    public function testWithAddedAppends(): void
    {
        $metadata = new Metadata(['x' => ['one']]);
        $new      = $metadata->withAdded('X', 'two');

        self::assertNotSame($metadata, $new);
        self::assertSame(['one'], $metadata->getAll('x'));
        self::assertSame(['one', 'two'], $new->getAll('x'));
    }

    public function testWithAddedOnAnAbsentKey(): void
    {
        self::assertSame(['one'], new Metadata()->withAdded('x', 'one')->getAll('x'));
    }

    public function testWithout(): void
    {
        $metadata = new Metadata(['x' => ['one'], 'y' => ['two']]);
        $new      = $metadata->without('X');

        self::assertNotSame($metadata, $new);
        self::assertTrue($metadata->has('x'));
        self::assertFalse($new->has('x'));
        self::assertTrue($new->has('y'));
    }

    public function testWithoutAnAbsentKey(): void
    {
        self::assertSame([], new Metadata()->without('x')->toArray());
    }

    public function testIteration(): void
    {
        $metadata = new Metadata(['x' => ['one'], 'y' => ['two']]);

        self::assertSame(
            ['x' => ['one'], 'y' => ['two']],
            iterator_to_array($metadata)
        );
    }

    public function testBinaryKeysCarryArbitraryBytes(): void
    {
        $bytes    = "\x00\x01\xff";
        $metadata = new Metadata()->with('trace-bin', $bytes);

        self::assertSame($bytes, $metadata->get('trace-bin'));
    }

    public function testRejectsAnInvalidKey(): void
    {
        $this->expectException(MetadataInvalidKeyException::class);

        new Metadata()->with('not valid!', 'x');
    }

    public function testRejectsAnEmptyKey(): void
    {
        $this->expectException(MetadataInvalidKeyException::class);

        new Metadata(['' => ['x']]);
    }

    public function testRejectsANonAsciiValueOnAnAsciiKey(): void
    {
        $this->expectException(MetadataInvalidValueException::class);

        new Metadata()->with('trace', "\x00binary");
    }

    public function testAcceptsAnEmptyAsciiValue(): void
    {
        self::assertSame('', new Metadata()->with('trace', '')->get('trace'));
    }
}

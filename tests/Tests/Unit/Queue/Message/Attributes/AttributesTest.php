<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Message\Attributes;

use stdClass;
use Valkyrja\Queue\Message\Attributes\Attributes;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidAttributeNameException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class AttributesTest extends TestCase
{
    public function testDefaults(): void
    {
        $attributes = new Attributes();

        self::assertSame([], $attributes->getAll());
        self::assertSame([], $attributes->asArray());
        self::assertFalse($attributes->has('tenant'));
        self::assertSame([], $attributes->get('tenant'));
        self::assertNull($attributes->getFirst('tenant'));
        self::assertSame('', $attributes->getLine('tenant'));
    }

    public function testConstructorNormalizesNames(): void
    {
        $attributes = new Attributes(['Tenant' => ['acme']]);

        self::assertSame(['tenant' => ['acme']], $attributes->getAll());
        self::assertTrue($attributes->has('TENANT'));
        self::assertSame(['acme'], $attributes->get('tEnAnT'));
    }

    public function testConstructorRejectsEmptyName(): void
    {
        $this->expectException(QueueMessageInvalidAttributeNameException::class);

        /* @phpstan-ignore-next-line */
        new Attributes(['' => ['acme']]);
    }

    public function testConstructorReindexesValues(): void
    {
        $attributes = new Attributes(['tenant' => [3 => 'acme', 7 => 'other']]);

        self::assertSame(['acme', 'other'], $attributes->get('tenant'));
    }

    public function testGetFirst(): void
    {
        $attributes = new Attributes(['tenant' => ['acme', 'other']]);

        self::assertSame('acme', $attributes->getFirst('tenant'));
    }

    public function testGetLine(): void
    {
        $attributes = new Attributes(['tenant' => ['acme', 'other']]);

        self::assertSame('acme,other', $attributes->getLine('tenant'));
    }

    public function testGetOnly(): void
    {
        $attributes = new Attributes(['A' => ['1'], 'b' => ['2'], 'c' => ['3']]);

        self::assertSame(['a' => ['1'], 'c' => ['3']], $attributes->getOnly('A', 'C'));
    }

    public function testGetAllExcept(): void
    {
        $attributes = new Attributes(['A' => ['1'], 'b' => ['2'], 'c' => ['3']]);

        self::assertSame(['b' => ['2']], $attributes->getAllExcept('A', 'C'));
    }

    public function testWithAttributeReplaces(): void
    {
        $attributes = new Attributes(['tenant' => ['acme']]);
        $new        = $attributes->withAttribute('Tenant', 'other');

        self::assertNotSame($attributes, $new);
        self::assertSame(['acme'], $attributes->get('tenant'));
        self::assertSame(['other'], $new->get('tenant'));
    }

    public function testWithAttributeRejectsEmptyName(): void
    {
        $this->expectException(QueueMessageInvalidAttributeNameException::class);

        /* @phpstan-ignore-next-line */
        new Attributes()->withAttribute('', 'acme');
    }

    public function testWithAddedAttributeAppends(): void
    {
        $attributes = new Attributes(['tenant' => ['acme']])->withAddedAttribute('TENANT', 'other');

        self::assertSame(['acme', 'other'], $attributes->get('tenant'));
    }

    public function testWithAddedAttributeCreatesWhenAbsent(): void
    {
        $attributes = new Attributes()->withAddedAttribute('tenant', 'acme');

        self::assertSame(['acme'], $attributes->get('tenant'));
    }

    public function testWithAddedAttributeRejectsEmptyName(): void
    {
        $this->expectException(QueueMessageInvalidAttributeNameException::class);

        /* @phpstan-ignore-next-line */
        new Attributes()->withAddedAttribute('', 'acme');
    }

    public function testWithoutAttributes(): void
    {
        $attributes = new Attributes(['a' => ['1'], 'b' => ['2'], 'c' => ['3']]);
        $new        = $attributes->withoutAttributes('A', 'missing');

        self::assertNotSame($attributes, $new);
        self::assertSame(['a' => ['1'], 'b' => ['2'], 'c' => ['3']], $attributes->getAll());
        self::assertSame(['b' => ['2'], 'c' => ['3']], $new->getAll());
    }

    public function testFromArray(): void
    {
        $attributes = Attributes::fromArray(['Tenant' => ['acme'], 'trace' => ['abc', 'def']]);

        self::assertSame(['tenant' => ['acme'], 'trace' => ['abc', 'def']], $attributes->asArray());
    }

    public function testFromArrayPromotesBareScalar(): void
    {
        // A producer in a laxer language may send a bare value rather than a list
        $attributes = Attributes::fromArray(['tenant' => 'acme', 'retries' => 3]);

        self::assertSame(['tenant' => ['acme'], 'retries' => ['3']], $attributes->asArray());
    }

    public function testFromArrayStringifiesScalarValues(): void
    {
        $attributes = Attributes::fromArray(['flags' => [true, 1.5]]);

        self::assertSame(['flags' => ['1', '1.5']], $attributes->asArray());
    }

    public function testFromArrayCastsIntegerNames(): void
    {
        $attributes = Attributes::fromArray([7 => ['value']]);

        self::assertSame(['7' => ['value']], $attributes->asArray());
    }

    public function testFromArrayRejectsNonScalarValue(): void
    {
        $this->expectException(QueueMessageInvalidAttributeNameException::class);

        Attributes::fromArray(['tenant' => [new stdClass()]]);
    }

    public function testFromArrayRejectsNonScalarBareValue(): void
    {
        $this->expectException(QueueMessageInvalidAttributeNameException::class);

        Attributes::fromArray(['tenant' => new stdClass()]);
    }
}

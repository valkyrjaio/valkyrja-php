<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Grpc\Routing\Collection;

use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Routing\Collection\RouteCollection;
use Valkyrja\Grpc\Routing\Data\GrpcRoutingData;
use Valkyrja\Grpc\Routing\Data\Route;
use Valkyrja\Grpc\Routing\Throwable\Exception\GrpcRoutingInvalidMethodException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

final class RouteCollectionTest extends TestCase
{
    private static function route(string $method): Route
    {
        return new Route($method, static fn (): ServiceResponse => ServiceResponse::ok());
    }

    public function testStartsEmpty(): void
    {
        $collection = new RouteCollection();

        self::assertFalse($collection->has('/pkg.Service/Method'));
        self::assertSame([], $collection->all());
        self::assertSame([], $collection->getData()->routes);
    }

    public function testAddAndGet(): void
    {
        $collection = new RouteCollection();
        $route      = self::route('/pkg.Service/Method');

        self::assertSame($collection, $collection->add($route));
        self::assertTrue($collection->has('/pkg.Service/Method'));
        self::assertSame($route, $collection->get('/pkg.Service/Method'));
        self::assertSame(['/pkg.Service/Method' => $route], $collection->all());
    }

    public function testAddManyAtOnce(): void
    {
        $collection = new RouteCollection();

        $collection->add(self::route('/pkg.Service/One'), self::route('/pkg.Service/Two'));

        self::assertCount(2, $collection->all());
    }

    public function testAddingTheSameMethodTwiceReplaces(): void
    {
        $collection = new RouteCollection();
        $second     = self::route('/pkg.Service/Method');

        $collection->add(self::route('/pkg.Service/Method'));
        $collection->add($second);

        self::assertCount(1, $collection->all());
        self::assertSame($second, $collection->get('/pkg.Service/Method'));
    }

    public function testGetAnUnknownMethodThrows(): void
    {
        $this->expectException(GrpcRoutingInvalidMethodException::class);

        new RouteCollection()->get('/pkg.Service/Missing');
    }

    public function testRoundTripsThroughData(): void
    {
        $collection = new RouteCollection();
        $route      = self::route('/pkg.Service/Method');

        $collection->add($route);

        $data = $collection->getData();

        self::assertInstanceOf(GrpcRoutingData::class, $data);

        $restored = new RouteCollection();
        $restored->setFromData($data);

        self::assertTrue($restored->has('/pkg.Service/Method'));
        self::assertSame($route, $restored->get('/pkg.Service/Method'));
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Routing\Collection;

use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Routing\Collection\RouteCollection;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Queue\Routing\Data\QueueRoutingData;
use Valkyrja\Queue\Routing\Data\Route;
use Valkyrja\Queue\Routing\Throwable\Exception\QueueRoutingInvalidRouteNameException;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function array_keys;

final class RouteCollectionTest extends TestCase
{
    public function testDefaults(): void
    {
        $collection = new RouteCollection();

        self::assertSame([], $collection->all());
        self::assertFalse($collection->has('SendWelcomeEmail'));
        self::assertSame([], $collection->getData()->routes);
    }

    public function testAddAndGet(): void
    {
        $route      = $this->route('SendWelcomeEmail');
        $collection = new RouteCollection();

        self::assertSame($collection, $collection->add($route));
        self::assertTrue($collection->has('SendWelcomeEmail'));
        self::assertSame($route, $collection->get('SendWelcomeEmail'));
    }

    public function testAddKeysByJobName(): void
    {
        $collection = new RouteCollection()->add($this->route('A'), $this->route('B'));

        self::assertSame(['A', 'B'], array_keys($collection->all()));
    }

    public function testAddOverwritesTheSameName(): void
    {
        $first  = $this->route('SendWelcomeEmail');
        $second = $this->route('SendWelcomeEmail')->withDescription('Other');

        $collection = new RouteCollection()->add($first)->add($second);

        self::assertCount(1, $collection->all());
        self::assertSame($second, $collection->get('SendWelcomeEmail'));
    }

    public function testGetThrowsForUnknownName(): void
    {
        $this->expectException(QueueRoutingInvalidRouteNameException::class);

        new RouteCollection()->get('Unknown');
    }

    public function testAllResolvesEveryRoute(): void
    {
        $route      = $this->route('SendWelcomeEmail');
        $collection = new RouteCollection()->add($route);

        self::assertSame(['SendWelcomeEmail' => $route], $collection->all());
    }

    public function testGetDataRoundTripsThroughSetFromData(): void
    {
        $route = $this->route('SendWelcomeEmail');
        $data  = new RouteCollection()->add($route)->getData();

        $collection = new RouteCollection();
        $collection->setFromData($data);

        self::assertSame($route, $collection->get('SendWelcomeEmail'));
    }

    public function testSetFromDataReplacesEverything(): void
    {
        $collection = new RouteCollection()->add($this->route('Old'));
        $collection->setFromData(new QueueRoutingData());

        self::assertSame([], $collection->all());
    }

    /**
     * @param non-empty-string $name
     */
    protected function route(string $name): RouteContract
    {
        return new Route(
            name: $name,
            description: 'Test route',
            handler: static fn (): JobResult => JobResult::ACK,
        );
    }
}

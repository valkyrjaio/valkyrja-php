<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Routing\Provider;

use Override;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Queue\Routing\Data\Route;
use Valkyrja\Queue\Routing\Provider\Contract\QueueRouteProviderContract;
use Valkyrja\Tests\Fixtures\Queue\Routing\Handler\JobOutcomeFixture;

/**
 * Provides routes whose outcome the test dictates.
 */
final class QueueRoutingProviderFixture implements QueueRouteProviderContract
{
    /** @var non-empty-string */
    public const string ALWAYS_RETRY = 'AlwaysRetry';
    /** @var non-empty-string */
    public const string ALWAYS_ACK = 'AlwaysAck';
    /** @var non-empty-string */
    public const string ALWAYS_FAIL = 'AlwaysFail';
    /** @var non-empty-string */
    public const string ALWAYS_THROWS = 'AlwaysThrows';

    /**
     * @inheritDoc
     */
    #[Override]
    public function getControllerClasses(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getRoutes(): array
    {
        return [
            new Route(
                name: self::ALWAYS_ACK,
                description: 'Always acknowledges',
                handler: static fn (ContainerContract $c, RouteContract $r): JobResult => JobResult::ACK,
            ),
            new Route(
                name: self::ALWAYS_RETRY,
                description: 'Always asks for a retry',
                handler: static fn (ContainerContract $c, RouteContract $r): JobResult => JobResult::RETRY,
            ),
            new Route(
                name: self::ALWAYS_FAIL,
                description: 'Always gives up',
                handler: static fn (ContainerContract $c, RouteContract $r): JobResult => JobResult::FAIL,
            ),
            new Route(
                name: self::ALWAYS_THROWS,
                description: 'Always throws',
                handler: [JobOutcomeFixture::class, 'throws'],
            ),
        ];
    }
}

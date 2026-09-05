<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Application\Provider;

use Override;
use Valkyrja\Queue\Routing\Provider\Contract\QueueRouteProviderContract;

final class QueueRouteProviderFixture implements QueueRouteProviderContract
{
    public static bool $called = false;

    /**
     * @inheritDoc
     */
    #[Override]
    public function getControllerClasses(): array
    {
        self::$called = true;

        return [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getRoutes(): array
    {
        return [];
    }
}

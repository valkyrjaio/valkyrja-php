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

/**
 * A queue route provider that contributes no controllers and no routes.
 */
final class QueueRouteProviderFixture implements QueueRouteProviderContract
{
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
        return [];
    }
}

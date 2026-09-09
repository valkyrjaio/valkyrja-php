<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Middleware\Handler;

use Override;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;
use Valkyrja\Tests\Unit\Abstract\TestCase;

abstract class HandlerTestCase extends TestCase
{
    protected Container $container;

    protected Job $job;

    protected RouteContract $route;

    /**
     * @inheritDoc
     */
    #[Override]
    protected function setUp(): void
    {
        $this->container = new Container();

        $this->job   = new Job(name: 'SendWelcomeEmail');
        $this->route = self::createStub(RouteContract::class);
    }
}

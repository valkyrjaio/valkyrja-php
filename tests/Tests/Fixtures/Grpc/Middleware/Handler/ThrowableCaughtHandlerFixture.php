<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Grpc\Middleware\Handler;

use Override;
use Throwable;
use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Middleware\Handler\ThrowableCaughtHandler;

final class ThrowableCaughtHandlerFixture extends ThrowableCaughtHandler
{
    protected int $count = 0;

    /**
     * Get the count of calls.
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function throwableCaught(ServiceCallContract $call, ServiceResponseContract $response, Throwable $throwable): ServiceResponseContract
    {
        $this->count++;

        return parent::throwableCaught($call, $response, $throwable);
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Provider;

use Override;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\Contract\ComponentProviderContract;
use Valkyrja\Grpc\Middleware\Provider\GrpcMiddlewareComponentProvider;
use Valkyrja\Grpc\Routing\Provider\GrpcRoutingComponentProvider;
use Valkyrja\Grpc\Server\Provider\GrpcServerComponentProvider;
use Valkyrja\Log\Provider\LogComponentProvider;

class GrpcApplicationComponentProvider implements ComponentProviderContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function getComponentProviders(ApplicationContract $app): array
    {
        return [
            new ApplicationComponentProvider(),
            new GrpcMiddlewareComponentProvider(),
            new GrpcRoutingComponentProvider(),
            new GrpcServerComponentProvider(),
            new LogComponentProvider(),
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getContainerProviders(ApplicationContract $app): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getEventProviders(ApplicationContract $app): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getCliProviders(ApplicationContract $app): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getHttpProviders(ApplicationContract $app): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getGrpcProviders(ApplicationContract $app): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Http\Routing\Controller;

use Valkyrja\Http\Routing\Attribute\Route;

/** A controller whose route names no handler, which the collector must reject. */
final class ControllerMissingHandlerFixture
{
    #[Route(path: '/no-handler', name: 'no-handler')]
    public function noHandler(): void
    {
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Routing\Controller;

use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Routing\Attribute\Route;

/**
 * A controller that carries no class-level name.
 */
final class UnnamedJobControllerFixture
{
    #[Route(name: 'RebuildIndex', description: 'Rebuild the index')]
    public function rebuildIndex(): JobResult
    {
        return JobResult::ACK;
    }
}

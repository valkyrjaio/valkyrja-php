<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Cli\Routing\Command;

use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Interaction\Output\Output;
use Valkyrja\Cli\Routing\Attribute\Route;

/** A command whose route names no handler, which the collector must reject. */
final class CommandMissingHandlerFixture
{
    /** @var non-empty-string */
    public const string NAME = 'missing-handler';
    /** @var non-empty-string */
    public const string DESCRIPTION = 'A command that names no handler';

    #[Route(name: self::NAME, description: self::DESCRIPTION)]
    public function run(): OutputContract
    {
        return new Output();
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Constant;

final class ApplicationInfo
{
    /**
     * The Application framework version.
     *
     * @var non-empty-string
     */
    public const string VERSION = '26.15.8';

    /**
     * The Application framework version build datetime.
     *
     * @var non-empty-string
     */
    public const string VERSION_BUILD_DATE_TIME = 'September 12 2026 08:23:22 MST';

    /**
     * The valkyrja framework ascii art.
     *
     * @var non-empty-string
     */
    public const string ASCII = <<<'TEXT'
                     _ _               _
         /\   /\__ _| | | ___   _ _ __(_) __ _
         \ \ / / _` | | |/ / | | | '__| |/ _` |
          \ V / (_| | |   <| |_| | |  | | (_| |
           \_/ \__,_|_|_|\_\\__, |_| _/ |\__,_|
                            |___/   |__/
        TEXT;

    /**
     * The default CLI banner icon (Valkyrie).
     *
     * @var non-empty-string
     */
    public const string ICON = <<<'ICON'
        ▗▄▄▖     ▗▄▄▖
        ▝▜██▄▄▄▄▄██▛▘
           ▝▜███▛▘
              █
        ICON;
}

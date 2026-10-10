<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Enum;

enum AddressType: string
{
    case IPV4    = 'ipv4';
    case IPV6    = 'ipv6';
    case UNIX    = 'unix';
    case UNKNOWN = 'unknown';
}

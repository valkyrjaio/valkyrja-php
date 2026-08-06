<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Peer\Contract;

use Valkyrja\Grpc\Message\Enum\AddressType;

/**
 * Information about the connection's other end, derived from the transport rather than a single
 * header. Never null on a service call; its auth context may be `insecure`.
 */
interface PeerContract
{
    /**
     * Get the peer address, e.g. `192.168.1.5:54321` or `unix:/var/run/sock`.
     *
     * @return non-empty-string
     */
    public function getAddress(): string;

    /**
     * Get the address family of the peer.
     */
    public function getAddressType(): AddressType;

    /**
     * Get the peer's authentication context. Always present.
     */
    public function getAuthContext(): AuthContextContract;
}

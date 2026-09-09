<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Message\Peer;

use Override;
use Valkyrja\Grpc\Message\Enum\AddressType;
use Valkyrja\Grpc\Message\Peer\Contract\AuthContextContract;
use Valkyrja\Grpc\Message\Peer\Contract\PeerContract;

class Peer implements PeerContract
{
    /**
     * @param non-empty-string $address The peer address
     */
    public function __construct(
        protected string $address,
        protected AddressType $addressType = AddressType::UNKNOWN,
        protected AuthContextContract $authContext = new AuthContext(),
    ) {
    }

    /**
     * Create a peer with an insecure auth context and unknown address type.
     *
     * @param non-empty-string $address The peer address
     */
    public static function insecure(string $address): self
    {
        return new self(
            address: $address,
            addressType: AddressType::UNKNOWN,
            authContext: AuthContext::insecure()
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getAddressType(): AddressType
    {
        return $this->addressType;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getAuthContext(): AuthContextContract
    {
        return $this->authContext;
    }
}

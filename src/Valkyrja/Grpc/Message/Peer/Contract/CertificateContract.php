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

/**
 * A peer certificate presented during the transport handshake.
 *
 * Modeled agnostically as its encoded (DER) bytes so the framework core stays free of any language-
 * or library-specific certificate type. Adapters translate their native certificate into this
 * shape.
 */
interface CertificateContract
{
    /**
     * Get the encoded (DER) certificate bytes.
     */
    public function getEncoded(): string;
}

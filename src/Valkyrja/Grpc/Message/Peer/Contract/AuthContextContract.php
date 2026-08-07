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

interface AuthContextContract
{
    /**
     * Get the auth type: `ssl`, `tls`, `insecure`, or a custom value.
     *
     * @return non-empty-string
     */
    public function getType(): string;

    /**
     * Get the auth properties as a multi-map of string keys to string values.
     *
     * @return array<string, string[]>
     */
    public function getProperties(): array;

    /**
     * Get the peer certificate chain; empty if none were presented.
     *
     * @return CertificateContract[]
     */
    public function getPeerCertificates(): array;

    /**
     * Get the peer subject (e.g. the certificate subject DN), or null if unknown.
     */
    public function getPeerSubject(): string|null;

    /**
     * Get the transport security type (e.g. the negotiated cipher/protocol), or null if none.
     */
    public function getTransportSecurityType(): string|null;
}

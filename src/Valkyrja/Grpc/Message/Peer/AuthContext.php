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
use Valkyrja\Grpc\Message\Peer\Contract\AuthContextContract;
use Valkyrja\Grpc\Message\Peer\Contract\CertificateContract;

class AuthContext implements AuthContextContract
{
    /** @var non-empty-string */
    public const string TYPE_INSECURE = 'insecure';

    /**
     * @param non-empty-string        $type             The auth type
     * @param array<string, string[]> $properties       The auth properties
     * @param CertificateContract[]   $peerCertificates The peer certificate chain
     */
    public function __construct(
        protected string $type = self::TYPE_INSECURE,
        protected array $properties = [],
        protected array $peerCertificates = [],
        protected string|null $peerSubject = null,
        protected string|null $transportSecurityType = null,
    ) {
    }

    /**
     * Create an auth context for an insecure (plaintext) connection.
     */
    public static function insecure(): self
    {
        return new self(type: self::TYPE_INSECURE);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getPeerCertificates(): array
    {
        return $this->peerCertificates;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getPeerSubject(): string|null
    {
        return $this->peerSubject;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getTransportSecurityType(): string|null
    {
        return $this->transportSecurityType;
    }
}

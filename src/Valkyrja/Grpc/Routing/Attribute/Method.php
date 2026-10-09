<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Routing\Attribute;

use Attribute;
use Valkyrja\Attribute\Contract\ReflectionAwareAttributeContract;
use Valkyrja\Attribute\Trait\ReflectionAwareAttribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Method implements ReflectionAwareAttributeContract
{
    use ReflectionAwareAttribute;

    /**
     * @param non-empty-string  $name            The RPC method name, e.g. `SayHello`
     * @param bool              $clientStreaming Whether the client streams multiple request messages
     * @param bool              $serverStreaming Whether the server streams multiple response messages
     * @param class-string|null $requestType     The generated protobuf request message type
     * @param class-string|null $responseType    The generated protobuf response message type
     */
    public function __construct(
        public string $name,
        public bool $clientStreaming = false,
        public bool $serverStreaming = false,
        public string|null $requestType = null,
        public string|null $responseType = null,
    ) {
    }
}

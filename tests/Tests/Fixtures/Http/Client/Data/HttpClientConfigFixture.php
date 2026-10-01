<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Http\Client\Data;

use Valkyrja\Application\Data\Config;
use Valkyrja\Http\Client\Data\Contract\HttpClientConfigContract;
use Valkyrja\Http\Client\Data\Contract\HttpClientLogConfigContract;
use Valkyrja\Http\Client\Manager\Contract\ClientContract;
use Valkyrja\Http\Client\Manager\NullClient;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Log\Logger\NullLogger;

/**
 * An application config that implements every http client contract at once.
 */
final class HttpClientConfigFixture extends Config implements HttpClientConfigContract, HttpClientLogConfigContract
{
    /**
     * @param class-string<ClientContract> $defaultClient
     * @param class-string<LoggerContract> $httpClientLogLogger
     */
    public function __construct(
        public string $defaultClient = NullClient::class,
        public string $httpClientLogLogger = NullLogger::class,
    ) {
        parent::__construct();
    }
}

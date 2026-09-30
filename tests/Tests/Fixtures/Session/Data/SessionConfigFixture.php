<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Session\Data;

use Valkyrja\Application\Data\Config;
use Valkyrja\Http\Message\Enum\SameSite;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Log\Logger\NullLogger;
use Valkyrja\Session\Data\Contract\SessionConfigContract;
use Valkyrja\Session\Data\Contract\SessionJwtConfigContract;
use Valkyrja\Session\Data\Contract\SessionLogConfigContract;
use Valkyrja\Session\Data\Contract\SessionPhpConfigContract;
use Valkyrja\Session\Data\Contract\SessionTokenConfigContract;
use Valkyrja\Session\Manager\Contract\SessionContract;
use Valkyrja\Session\Manager\NullSession;

/**
 * An application config that implements every session contract at once.
 */
final class SessionConfigFixture extends Config implements SessionConfigContract, SessionPhpConfigContract, SessionJwtConfigContract, SessionTokenConfigContract, SessionLogConfigContract
{
    /**
     * @param class-string<SessionContract> $defaultSession
     * @param non-empty-string|null         $sessionId
     * @param non-empty-string|null         $sessionName
     * @param non-empty-string              $phpCookiePath
     * @param non-empty-string|null         $phpCookieDomain
     * @param non-empty-string|null         $jwtOptionName
     * @param non-empty-string|null         $jwtHeaderName
     * @param non-empty-string|null         $tokenOptionName
     * @param non-empty-string|null         $tokenHeaderName
     * @param class-string<LoggerContract>  $sessionLogLogger
     */
    public function __construct(
        public string $defaultSession = NullSession::class,
        public string|null $sessionId = 'test-id',
        public string|null $sessionName = 'test-name',
        public string $phpCookiePath = '/test',
        public string|null $phpCookieDomain = 'test.dev',
        public int $phpCookieLifetime = 3600,
        public bool $phpCookieSecure = true,
        public bool $phpCookieHttpOnly = true,
        public SameSite $phpCookieSameSite = SameSite::STRICT,
        public string|null $jwtOptionName = 'test-jwt-option',
        public string|null $jwtHeaderName = 'test-jwt-header',
        public string|null $tokenOptionName = 'test-token-option',
        public string|null $tokenHeaderName = 'test-token-header',
        public string $sessionLogLogger = NullLogger::class,
    ) {
        parent::__construct();
    }
}

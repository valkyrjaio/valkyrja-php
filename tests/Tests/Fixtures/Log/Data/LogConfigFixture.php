<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Log\Data;

use Valkyrja\Application\Data\Config;
use Valkyrja\Log\Data\Contract\LogConfigContract;
use Valkyrja\Log\Data\Contract\LogPsrConfigContract;
use Valkyrja\Log\Enum\LogLevel;
use Valkyrja\Log\Logger\Contract\LoggerContract;
use Valkyrja\Log\Logger\NullLogger;

/**
 * An application config that implements every log contract at once.
 */
final class LogConfigFixture extends Config implements LogConfigContract, LogPsrConfigContract
{
    /**
     * @param class-string<LoggerContract> $defaultLogger
     * @param non-empty-string|null        $psrName
     * @param non-empty-string|null        $psrFilePath
     */
    public function __construct(
        public string $defaultLogger = NullLogger::class,
        public string|null $psrName = 'fixture-log',
        public string|null $psrFilePath = '/tmp',
        public LogLevel $psrLevel = LogLevel::WARNING,
    ) {
        parent::__construct();
    }
}

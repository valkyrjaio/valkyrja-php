<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Application\Entry;

use Override;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use TypeError;
use Valkyrja\Application\Data\CliConfig;
use Valkyrja\Application\Data\Config;
use Valkyrja\Application\Data\Contract\ConfigContract;
use Valkyrja\Application\Entry\Cli;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Cli\Interaction\Input\Contract\InputContract;
use Valkyrja\Cli\Server\Handler\Contract\InputHandlerContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Tests\Fixtures\Application\Entry\CliFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

/**
 * Test the Cli service.
 */
#[RunTestsInSeparateProcesses]
final class CliTest extends TestCase
{
    public function testRunThrowsWhenBaseConfigPassed(): void
    {
        $this->expectException(TypeError::class);

        Cli::run(config: new Config());
    }

    public function testRunBootstrapsAndDispatchesInputHandler(): void
    {
        $_SERVER['argv'] = ['cli'];

        $handler = $this->createMock(InputHandlerContract::class);
        $handler->expects($this->once())
            ->method('run')
            ->with(self::isInstanceOf(InputContract::class));

        $container = $this->createMock(ContainerContract::class);
        $container->expects($this->once())
            ->method('getSingleton')
            ->with(InputHandlerContract::class)
            ->willReturn($handler);

        $app = $this->createMock(ApplicationContract::class);
        $app->expects($this->once())->method('getContainer')->willReturn($container);
        $app->expects($this->once())->method('getDebugMode')->willReturn(false);

        $entry = new class extends Cli {
            public static ApplicationContract $appMock;

            #[Override]
            public static function start(ConfigContract $config): ApplicationContract
            {
                return self::$appMock;
            }
        };
        $entry::$appMock = $app;

        $entry::run(new CliConfig());
    }

    public function testGetInputDefaults(): void
    {
        $_SERVER['argv'] = [];

        $input = CliFixture::getInputExposed(new CliConfig());

        self::assertSame('valkyrja', $input->getCaller());
        self::assertSame('list', $input->getCommandName());
        self::assertEmpty($input->getArguments());
        self::assertEmpty($input->getOptions());
    }

    public function testGetInputWithCustomApplicationName(): void
    {
        $_SERVER['argv'] = [];

        $input = CliFixture::getInputExposed(new CliConfig(applicationName: 'test'));

        self::assertSame('test', $input->getCaller());
        self::assertSame('list', $input->getCommandName());
        self::assertEmpty($input->getArguments());
        self::assertEmpty($input->getOptions());
    }

    public function testGetInputWithCustomCommandName(): void
    {
        $_SERVER['argv'] = [];

        $input = CliFixture::getInputExposed(new CliConfig(defaultCommandName: 'test'));

        self::assertSame('valkyrja', $input->getCaller());
        self::assertSame('test', $input->getCommandName());
        self::assertEmpty($input->getArguments());
        self::assertEmpty($input->getOptions());
    }

    public function testGetInputFromGlobals(): void
    {
        $_SERVER['argv'] = [
            'cli',
            'command',
            '-t',
            '-v=value',
            '--value',
            '--value2=test',
            'argument',
            'argument2',
        ];

        $input = CliFixture::getInputExposed(new CliConfig());

        self::assertSame('cli', $input->getCaller());
        self::assertSame('command', $input->getCommandName());
        self::assertCount(2, $input->getArguments());
        self::assertCount(4, $input->getOptions());
    }

    public function testGetInputWithoutServerArgv(): void
    {
        // A process started without argv registered, such as a non-CLI SAPI,
        // reaches the fallback rather than reading a missing global
        unset($_SERVER['argv']);

        $input = CliFixture::getInputExposed(new CliConfig());

        self::assertSame('valkyrja', $input->getCaller());
        self::assertSame([], $input->getArguments());
    }
}

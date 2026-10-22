<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Application\Entry\Abstract;

use Override;
use PhpAmqpLib\Connection\AbstractConnection;
use Throwable;
use Valkyrja\Application\Directory\Directory;
use Valkyrja\Container\Data\ContainerData;
use Valkyrja\Queue\Client\Manager\AmqpClient;
use Valkyrja\Queue\Client\Manager\InMemoryClient;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Tests\Fixtures\Application\Entry\ProcessStateInternalQueueFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Handler\ProcessStateFixture;
use Valkyrja\Tests\Fixtures\Queue\Routing\Provider\QueueRoutingProviderFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function date_default_timezone_get;
use function date_default_timezone_set;
use function restore_exception_handler;
use function set_exception_handler;

final class InternalQueueTest extends TestCase
{
    protected string $basePath;

    protected string $timezone;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = Directory::$basePath;
        $this->timezone = date_default_timezone_get();

        ProcessStateFixture::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        Directory::$basePath = $this->basePath;
        date_default_timezone_set($this->timezone);

        ProcessStateFixture::reset();

        parent::tearDown();
    }

    public function testBootstrapLeavesTheHostProcessStateInPlace(): void
    {
        ProcessStateInternalQueueFixture::bootstrap(ProcessStateInternalQueueFixture::getConfig());

        self::assertSame($this->basePath, Directory::$basePath);
        self::assertSame($this->timezone, date_default_timezone_get());
    }

    public function testAJobSeesTheProcessStateOfTheQueueApplication(): void
    {
        $app = ProcessStateInternalQueueFixture::bootstrap(ProcessStateInternalQueueFixture::getConfig());

        ProcessStateInternalQueueFixture::handle(
            app: $app,
            data: $app->getContainer()->getSingleton(ContainerData::class),
            job: new JobFactory()->create(QueueRoutingProviderFixture::RECORD_PROCESS_STATE),
            client: new InMemoryClient(),
        );

        self::assertSame(ProcessStateInternalQueueFixture::getConfig()->dir, ProcessStateFixture::$basePath);
        self::assertSame(ProcessStateInternalQueueFixture::TIMEZONE, ProcessStateFixture::$timezone);
        self::assertSame($this->basePath, Directory::$basePath);
        self::assertSame($this->timezone, date_default_timezone_get());
    }

    public function testSettleRedeliversThroughAClientNoProcessorOwns(): void
    {
        // The buffer is not an entry-driving client, so a guard written against
        // that class alone leaves its retry unsettled and the job disappears
        $client = new InMemoryClient();

        ProcessStateInternalQueueFixture::settle(
            new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK),
            JobResult::RETRY,
            $client,
        );

        self::assertCount(1, $client->getBuffered());
    }

    public function testSettleLeavesAProcessorOwnedClientAlone(): void
    {
        // This broker answers a retry from its own settlement, and refuses a
        // requeue, so reaching it here would throw rather than redeliver
        $client = new AmqpClient(connection: self::createStub(AbstractConnection::class));

        ProcessStateInternalQueueFixture::settle(
            new JobFactory()->create(QueueRoutingProviderFixture::ALWAYS_ACK),
            JobResult::RETRY,
            $client,
        );

        self::assertSame([], $client->getPushed());
    }

    public function testTheExceptionHandlerOfTheHostStaysInPlace(): void
    {
        $handler = static function (Throwable $throwable): void {
        };

        set_exception_handler($handler);

        try {
            ProcessStateInternalQueueFixture::bootstrap(ProcessStateInternalQueueFixture::getConfig());

            self::assertSame($handler, set_exception_handler(null));
        } finally {
            restore_exception_handler();
            restore_exception_handler();
        }
    }
}

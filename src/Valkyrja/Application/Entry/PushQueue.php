<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Application\Entry;

use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Entry\Abstract\App;
use Valkyrja\Http\Message\Enum\StatusCode;
use Valkyrja\Http\Message\Request\Contract\ServerRequestContract;
use Valkyrja\Http\Message\Request\Factory\RequestFactory;
use Valkyrja\Http\Message\Response\Contract\ResponseContract;
use Valkyrja\Http\Message\Response\Response;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Requeuer\Contract\RequeuerContract;
use Valkyrja\Queue\Client\Requeuer\Requeuer;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Server\Handler\Contract\JobHandlerContract;
use Valkyrja\Queue\Server\Mapper\Contract\RequestMapperContract;
use Valkyrja\Queue\Server\Mapper\RequestMapper;

class PushQueue extends App
{
    /**
     * Handle one pushed job and send the settlement status.
     */
    public static function run(
        QueueConfigContract $config,
        ServerRequestContract|null $request = null,
        ClientContract|null $client = null,
        RequestMapperContract $mapper = new RequestMapper(),
        RequeuerContract $requeuer = new Requeuer(),
    ): void {
        $app = static::start(
            config: $config,
        );

        $container = $app->getContainer();

        self::bootstrapThrowableHandler($app, $container);

        $job = $mapper->map($request ?? static::getRequest());

        $handler = $container->getSingleton(JobHandlerContract::class);

        $result = $handler->run($job);

        // A processor-owned push has no client to re-queue through: the status
        // *is* the retry signal, so settlement only runs when one was supplied
        if ($client !== null) {
            $requeuer->settle($job, $result, $client);
        }

        static::send(static::respond($result));

        $handler->resultSettled($job, $result);
    }

    /**
     * Send the settlement response.
     *
     * A seam so a test can drive the entry without writing headers.
     *
     * @codeCoverageIgnore
     */
    public static function send(ResponseContract $response): void
    {
        $response->send();
    }

    /**
     * Get the current request.
     *
     * @codeCoverageIgnore
     */
    public static function getRequest(): ServerRequestContract
    {
        return RequestFactory::fromGlobals();
    }

    /**
     * Turn an outcome into the status the processor reads as settlement.
     */
    public static function respond(JobResult $result): ResponseContract
    {
        return new Response(
            statusCode: match ($result) {
                // 2xx deletes the message
                JobResult::ACK                          => StatusCode::NO_CONTENT,
                // A retryable failure asks the processor to redeliver
                JobResult::RETRY                        => StatusCode::SERVICE_UNAVAILABLE,
                // A terminal failure is still non-2xx, but the processor's own
                // dead-letter policy is what ends the chain
                JobResult::FAIL, JobResult::DEAD_LETTER => StatusCode::UNPROCESSABLE_ENTITY,
            }
        );
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Grpc\Support;

use Valkyrja\Grpc\Message\Call\Contract\ServiceCallContract;
use Valkyrja\Grpc\Message\Enum\CancellationReason;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Message\Status\Status;

class Cancellation
{
    /**
     * Run the two-question check.
     *
     * @param ServiceCallContract          $call     The current call
     * @param ServiceResponseContract|null $response The response in hand, or null if none exists yet
     *
     * @return ServiceResponseContract|null A cancellation response to fast-exit with, or null to
     *                                      continue normally
     */
    public static function checkAndFinalize(ServiceCallContract $call, ServiceResponseContract|null $response = null): ServiceResponseContract|null
    {
        $cancellation = $call->getCancellation();
        $isCancelled  = $cancellation->isCancelled();

        // An elapsed deadline is the second half of the first question, and a transport that never
        // fires the token still has to report DEADLINE_EXCEEDED rather than run the handler.
        if ($isCancelled || $call->getDeadline()->isExpired()) {
            $reason = $isCancelled
                ? $cancellation->getReason()
                : CancellationReason::DEADLINE_EXCEEDED;

            return $response?->withStatus(Status::forReason($reason))
                ?? ServiceResponse::cancelled($reason);
        }

        if ($response?->isCancellation() === true) {
            return $response;
        }

        return null;
    }
}

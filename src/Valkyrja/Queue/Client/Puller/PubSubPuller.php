<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * (c) Melech Mizrachi <melechmizrachi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Valkyrja\Queue\Client\Puller;

use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\Subscription;
use JsonException;
use Override;
use Valkyrja\Queue\Client\Manager\Contract\ClientContract;
use Valkyrja\Queue\Client\Puller\Contract\PullerContract;
use Valkyrja\Queue\Client\Requeuer\Contract\RequeuerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;

/**
 * Consumes from a Google Cloud Pub/Sub subscription, and settles with it.
 *
 * This is both the puller and the re-queuer, for the same reason the AMQP and
 * SQS adapters are: settling means acting on the native delivery, and only the
 * thing that received it holds the acknowledgement id. The framework never sees
 * an ack id — it hands over an outcome enum and this translates it.
 *
 * A pull is a synchronous request that returns whatever is waiting, so an empty
 * subscription already returns at once and needs no separate wait.
 */
class PubSubPuller implements PullerContract, RequeuerContract
{
    /**
     * The delivery currently in flight, if any.
     *
     * A pull worker handles one job at a time, so a single slot is enough — and
     * it is cleared on settlement so a second settle cannot double-acknowledge.
     */
    protected Message|null $current = null;

    public function __construct(
        protected Subscription $subscription,
        protected JobFactoryContract $factory = new JobFactory(),
    ) {
    }

    /**
     * @inheritDoc
     *
     * Pub/Sub is a managed service, so there is no connection to open.
     */
    #[Override]
    public function connect(): void
    {
    }

    /**
     * @inheritDoc
     *
     * @throws JsonException
     */
    #[Override]
    public function receive(): JobContract|null
    {
        $messages = $this->subscription->pull(['maxMessages' => 1]);

        $message = $messages[0] ?? null;

        if (! $message instanceof Message) {
            return null;
        }

        $this->current = $message;

        return $this->factory->fromJson($message->data());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function disconnect(): void
    {
        // Anything still in flight was not completed, so hand it back rather
        // than letting it wait out the whole acknowledgement deadline
        $this->releaseCurrent();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function settle(JobContract $job, JobResult $result, ClientContract $client): void
    {
        $message = $this->current;

        if (! $message instanceof Message) {
            return;
        }

        $this->current = null;

        if ($result === JobResult::RETRY) {
            $this->release($message);

            return;
        }

        // A dead letter is terminal here as well. Pub/Sub moves a message to
        // the dead-letter topic on the delivery-attempt count, so the framework
        // acknowledging it is what stops the chain.
        $this->subscription->acknowledge($message);
    }

    /**
     * Hand any in-flight delivery back to the subscription.
     */
    protected function releaseCurrent(): void
    {
        $message = $this->current;

        if ($message instanceof Message) {
            $this->current = null;

            $this->release($message);
        }
    }

    /**
     * Make a delivery redeliverable at once.
     *
     * A zero deadline is Pub/Sub's nack: the message becomes available again
     * and its delivery-attempt count goes up.
     */
    protected function release(Message $message): void
    {
        $this->subscription->modifyAckDeadline($message, 0);
    }
}

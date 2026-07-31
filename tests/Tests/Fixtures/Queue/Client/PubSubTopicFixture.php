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

namespace Valkyrja\Tests\Fixtures\Queue\Client;

use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\Topic;
use Override;

/**
 * A recording stand-in for a Pub/Sub topic.
 *
 * The constructor is bypassed on purpose: a real topic demands a request
 * handler and a serializer, which is exactly what a unit test must not need.
 */
final class PubSubTopicFixture extends Topic
{
    /** @var array<int, Message|array<string, mixed>> Every message published, in order */
    public array $published = [];

    /**
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct()
    {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function publish($message, array $options = [])
    {
        $this->published[] = $message;

        return ['message-id-1'];
    }
}

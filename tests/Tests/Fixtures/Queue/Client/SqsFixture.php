<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Client;

use AsyncAws\Core\Result;
use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\Sqs\Result\ReceiveMessageResult;
use AsyncAws\Sqs\Result\SendMessageResult;
use AsyncAws\Sqs\SqsClient;
use AsyncAws\Sqs\ValueObject\Message;
use Override;

/**
 * A recording stand-in for the SQS client.
 *
 * The constructor is bypassed on purpose: a real client resolves credentials
 * and builds an HTTP client, which is exactly what a unit test must not need.
 */
final class SqsFixture extends SqsClient
{
    /** @var array<int, array{0: string, 1: mixed}> */
    public array $calls = [];

    /** @var Message[] The deliveries the next receive returns */
    public array $next = [];

    /**
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct()
    {
    }

    /**
     * Get the input of every call to a client method.
     *
     * @return array<int, mixed>
     */
    public function getCalls(string $method): array
    {
        $calls = [];

        foreach ($this->calls as [$name, $input]) {
            if ($name === $method) {
                $calls[] = $input;
            }
        }

        return $calls;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function sendMessage($input): SendMessageResult
    {
        $this->calls[] = ['sendMessage', $input];

        return ResultMockFactory::create(SendMessageResult::class);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function receiveMessage($input): ReceiveMessageResult
    {
        $this->calls[] = ['receiveMessage', $input];

        $next = $this->next;

        $this->next = [];

        return ResultMockFactory::create(ReceiveMessageResult::class, ['Messages' => $next]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function deleteMessage($input): Result
    {
        $this->calls[] = ['deleteMessage', $input];

        return ResultMockFactory::create(Result::class);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function changeMessageVisibility($input): Result
    {
        $this->calls[] = ['changeMessageVisibility', $input];

        return ResultMockFactory::create(Result::class);
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Queue\Message\Job\Factory;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Valkyrja\Queue\Message\Constant\EnvelopeField;
use Valkyrja\Queue\Message\Job\Factory\JobFactory;
use Valkyrja\Queue\Message\Job\Job;
use Valkyrja\Queue\Message\Payload\Payload;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidEnvelopeException;
use Valkyrja\Queue\Message\Throwable\Exception\QueueMessageInvalidPayloadParamException;
use Valkyrja\Support\Time\Microtime;
use Valkyrja\Tests\Unit\Abstract\TestCase;

use function json_decode;
use function json_encode;

final class JobFactoryTest extends TestCase
{
    /** @var non-empty-string */
    protected const string NAME = 'SendWelcomeEmail';

    /** @var int<0, max> */
    protected const int FROZEN_MS = 1768564798000;

    protected JobFactory $factory;

    /**
     * @return array<string, array{string}>
     */
    public static function nonObjectJsonProvider(): array
    {
        return [
            'number'  => ['5'],
            'boolean' => ['true'],
            'string'  => ['"text"'],
            'null'    => ['null'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function payloadShapeProvider(): array
    {
        return [
            'nested empty map'    => ['{"meta":{}}'],
            'nested empty list'   => ['{"ids":[]}'],
            'empty map in a list' => ['{"items":[{}]}'],
            'empty list in a map' => ['{"meta":{"ids":[]}}'],
            'non-empty list'      => ['{"ids":[1,2]}'],
            'non-empty map'       => ['{"user":{"id":42}}'],
        ];
    }

    /**
     * @return array<non-empty-string, mixed>
     */
    protected static function fullEnvelope(): array
    {
        return [
            EnvelopeField::ID                              => '01JABCDEF0123456789ABCDEFG',
            EnvelopeField::NAME                            => self::NAME,
            EnvelopeField::PRODUCER                        => 'AuthService php/26.2.3',
            EnvelopeField::ATTRIBUTES                      => ['tenant' => ['acme']],
            EnvelopeField::ATTEMPTS                        => 2,
            EnvelopeField::MAX_ATTEMPTS                    => 7,
            EnvelopeField::PRIORITY                        => 3,
            EnvelopeField::DELAY_MS                        => 100,
            EnvelopeField::RETRY_DELAY_MS                  => 250,
            EnvelopeField::RETRY_DELAY_MULTIPLY_BY_ATTEMPT => true,
            EnvelopeField::ENQUEUED_AT_MS                  => self::FROZEN_MS,
            EnvelopeField::ENQUEUED_AT_ISO                 => '2026-01-16T11:59:58.000Z',
            EnvelopeField::MODIFIED_AT_MS                  => self::FROZEN_MS,
            EnvelopeField::MODIFIED_AT_ISO                 => '2026-01-16T11:59:58.000Z',
            EnvelopeField::PAYLOAD                         => ['user_id' => 42],
        ];
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Microtime::freeze(1768564798.0);

        $this->factory = new JobFactory();
    }

    #[Override]
    protected function tearDown(): void
    {
        Microtime::unfreeze();

        parent::tearDown();
    }

    public function testFromArrayReadsEveryField(): void
    {
        $job = $this->factory->fromArray(self::fullEnvelope());

        self::assertSame('01JABCDEF0123456789ABCDEFG', $job->getId());
        self::assertSame(self::NAME, $job->getName());
        self::assertSame('AuthService php/26.2.3', $job->getProducer());
        self::assertSame(['tenant' => ['acme']], $job->getAttributes()->asArray());
        self::assertSame(2, $job->getAttempts());
        self::assertSame(7, $job->getMaxAttempts());
        self::assertSame(3, $job->getPriority());
        self::assertSame(100, $job->getDelayMs());
        self::assertSame(250, $job->getRetryDelayMs());
        self::assertTrue($job->getRetryDelayMultiplyByAttempt());
        self::assertSame(self::FROZEN_MS, $job->getEnqueuedAtMs());
        self::assertSame(self::FROZEN_MS, $job->getModifiedAtMs());
        self::assertSame(['user_id' => 42], $job->getPayload()->asArray());
    }

    public function testRoundTrip(): void
    {
        $envelope = self::fullEnvelope();

        self::assertSame($envelope, $this->factory->fromArray($envelope)->asArray());
    }

    public function testFromArrayRejectsAMissingName(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        $this->factory->fromArray([]);
    }

    public function testFromArrayRejectsAnEmptyName(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        $this->factory->fromArray([EnvelopeField::NAME => '']);
    }

    public function testFromArrayRejectsANonStringName(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        $this->factory->fromArray([EnvelopeField::NAME => 42]);
    }

    public function testFromArrayDefaultsEveryAbsentField(): void
    {
        // An older producer may not have sent fields this port now knows about
        $job = $this->factory->fromArray([EnvelopeField::NAME => self::NAME]);

        self::assertSame('', $job->getProducer());
        self::assertSame([], $job->getAttributes()->asArray());
        self::assertSame([], $job->getPayload()->asArray());
        self::assertSame(1, $job->getAttempts());
        self::assertSame(Job::DEFAULT_MAX_ATTEMPTS, $job->getMaxAttempts());
        self::assertSame(0, $job->getPriority());
        self::assertSame(0, $job->getDelayMs());
        self::assertSame(Job::DEFAULT_RETRY_DELAY_MS, $job->getRetryDelayMs());
        self::assertFalse($job->getRetryDelayMultiplyByAttempt());
        self::assertSame(self::FROZEN_MS, $job->getEnqueuedAtMs());
        self::assertSame(self::FROZEN_MS, $job->getModifiedAtMs());
        self::assertNotSame('', $job->getId());
    }

    public function testFromArrayIgnoresUnknownFields(): void
    {
        // The contract can gain fields over time without breaking this consumer
        $job = $this->factory->fromArray([EnvelopeField::NAME => self::NAME, 'some_future_field' => 'value']);

        self::assertSame(self::NAME, $job->getName());
    }

    public function testFromArrayDefaultsMistypedFields(): void
    {
        $job = $this->factory->fromArray([
            EnvelopeField::NAME                            => self::NAME,
            EnvelopeField::ID                              => 42,
            EnvelopeField::PRODUCER                        => [],
            EnvelopeField::ATTRIBUTES                      => 'not-an-object',
            EnvelopeField::PAYLOAD                         => 'not-an-object',
            EnvelopeField::ATTEMPTS                        => 'two',
            EnvelopeField::PRIORITY                        => 'three',
            EnvelopeField::RETRY_DELAY_MULTIPLY_BY_ATTEMPT => 'yes',
        ]);

        self::assertNotSame('42', $job->getId());
        self::assertSame('', $job->getProducer());
        self::assertSame([], $job->getAttributes()->asArray());
        self::assertSame([], $job->getPayload()->asArray());
        self::assertSame(1, $job->getAttempts());
        self::assertSame(0, $job->getPriority());
        self::assertFalse($job->getRetryDelayMultiplyByAttempt());
    }

    public function testFromArrayReadsAnExplicitFalseFlag(): void
    {
        // A wire `false` is a boolean the guard accepts, so it reaches the
        // second half of the test. Every other case fails the first half
        $job = $this->factory->fromArray([
            EnvelopeField::NAME                            => self::NAME,
            EnvelopeField::RETRY_DELAY_MULTIPLY_BY_ATTEMPT => false,
        ]);

        self::assertFalse($job->getRetryDelayMultiplyByAttempt());
    }

    public function testFromArrayDefaultsAnEmptyId(): void
    {
        $job = $this->factory->fromArray([EnvelopeField::NAME => self::NAME, EnvelopeField::ID => '']);

        self::assertNotSame('', $job->getId());
    }

    public function testFromArrayDefaultsOutOfRangeNumbers(): void
    {
        $job = $this->factory->fromArray([
            EnvelopeField::NAME           => self::NAME,
            EnvelopeField::ATTEMPTS       => 0,
            EnvelopeField::MAX_ATTEMPTS   => -1,
            EnvelopeField::DELAY_MS       => -1,
            EnvelopeField::RETRY_DELAY_MS => -1,
            EnvelopeField::ENQUEUED_AT_MS => -1,
            EnvelopeField::MODIFIED_AT_MS => -1,
        ]);

        self::assertSame(1, $job->getAttempts());
        self::assertSame(Job::DEFAULT_MAX_ATTEMPTS, $job->getMaxAttempts());
        self::assertSame(0, $job->getDelayMs());
        self::assertSame(Job::DEFAULT_RETRY_DELAY_MS, $job->getRetryDelayMs());
        self::assertSame(self::FROZEN_MS, $job->getEnqueuedAtMs());
        self::assertSame(self::FROZEN_MS, $job->getModifiedAtMs());
    }

    public function testFromArrayAcceptsZeroDelays(): void
    {
        $job = $this->factory->fromArray([
            EnvelopeField::NAME           => self::NAME,
            EnvelopeField::DELAY_MS       => 0,
            EnvelopeField::RETRY_DELAY_MS => 0,
            EnvelopeField::ENQUEUED_AT_MS => 0,
        ]);

        self::assertSame(0, $job->getDelayMs());
        self::assertSame(0, $job->getRetryDelayMs());
        self::assertSame(0, $job->getEnqueuedAtMs());
    }

    public function testToJson(): void
    {
        $job = new Job(
            name: self::NAME,
            id: '01JABCDEF0123456789ABCDEFG',
            enqueuedAtMs: self::FROZEN_MS,
        );

        self::assertJson($this->factory->toJson($job));
    }

    public function testToJsonWritesTheEmptyMapsAsObjects(): void
    {
        $job = new Job(
            name: self::NAME,
            id: '01JABCDEF0123456789ABCDEFG',
            enqueuedAtMs: self::FROZEN_MS,
        );

        $json = $this->factory->toJson($job);

        // The envelope types both fields as objects, and a strict consumer in
        // another language rejects [] where it expects a map
        self::assertStringContainsString('"attributes":{}', $json);
        self::assertStringContainsString('"payload":{}', $json);
    }

    public function testToJsonWritesANestedEmptyMapAsAnObject(): void
    {
        $job = new Job(
            name: self::NAME,
            payload: Payload::fromArray(['meta' => [], 'tags' => ['a', 'b']]),
        );

        $json = $this->factory->toJson($job);

        // A nested empty map is the same cross-language failure one level down
        self::assertStringContainsString('"meta":{}', $json);
        // A list keeps its integer keys, so it stays a JSON array
        self::assertStringContainsString('"tags":["a","b"]', $json);
    }

    public function testToJsonWritesANestedNonEmptyMapAsAnObject(): void
    {
        // The ordinary nested payload. Without the cast it goes out as a JSON
        // object anyway, so only a reader of the recursion can tell; the arm
        // that keeps it an object has to be reached for that to stay true.
        $job = new Job(
            name: self::NAME,
            payload: Payload::fromArray(['user' => ['id' => 42]]),
        );

        self::assertStringContainsString('"user":{"id":42}', $this->factory->toJson($job));
    }

    public function testANestedNonEmptyMapSurvivesARoundTrip(): void
    {
        $job = new Job(
            name: self::NAME,
            payload: Payload::fromArray(['user' => ['id' => 42, 'name' => 'Ada']]),
        );

        $read = $this->factory->fromJson($this->factory->toJson($job));

        // asArray() flattens a nested payload back to plain arrays, where
        // getAll() would hand back the nested PayloadContract
        self::assertSame(
            ['user' => ['id' => 42, 'name' => 'Ada']],
            $read->getPayload()->asArray()
        );
    }

    public function testANestedEmptyMapSurvivesARoundTrip(): void
    {
        $job = $this->factory->fromJson((string) json_encode([
            'name'    => self::NAME,
            'payload' => ['meta' => new stdClass()],
        ]));

        // Reading a {} and writing back a [] would change the envelope
        self::assertStringContainsString('"meta":{}', $this->factory->toJson($job));
    }

    public function testToJsonWritesAnIntegerKeyedMapAsAnObject(): void
    {
        $job = new Job(
            name: self::NAME,
            payload: Payload::fromArray([0 => 'first']),
        );

        self::assertStringContainsString('"payload":{"0":"first"}', $this->factory->toJson($job));
    }

    #[DataProvider('payloadShapeProvider')]
    public function testAPayloadShapeSurvivesTheWireUnchanged(string $payload): void
    {
        // `json_decode` with associative arrays turns a JSON object and a JSON
        // array into the same PHP array, so the shape has to be carried rather
        // than inferred from the keys
        $json = $this->factory->toJson($this->factory->fromJson('{"name":"X","payload":' . $payload . '}'));

        self::assertStringContainsString('"payload":' . $payload, $json);
    }

    public function testFromArrayIgnoresAnObjectThatIsNotADecodedJsonObject(): void
    {
        // Casting any object would turn its own properties into payload keys,
        // and a private one gets a NUL-mangled key that would reach the wire
        $job = $this->factory->fromArray(['name' => self::NAME, 'payload' => new DateTimeImmutable()]);

        self::assertSame([], $job->getPayload()->asArray());
    }

    public function testAnEnvelopeWithNoPayloadFieldReadsAsAnEmptyPayload(): void
    {
        $job = $this->factory->fromJson('{"name":"SendWelcomeEmail"}');

        self::assertSame([], $job->getPayload()->asArray());
    }

    public function testAnEnvelopeWhosePayloadIsAScalarReadsAsAnEmptyPayload(): void
    {
        $job = $this->factory->fromJson('{"name":"SendWelcomeEmail","payload":7}');

        self::assertSame([], $job->getPayload()->asArray());
    }

    public function testReplacingAMapNodeWithAListEncodesAsAnArray(): void
    {
        // The flag describes the params, so replacing them recomputes it, and
        // the encoded node is what a consumer in another language reads
        $node = Payload::fromJsonValue(json_decode('{"a":1}', false));

        $job = new Job(name: self::NAME, payload: new Payload(['items' => $node->with([1, 2])]));

        self::assertStringContainsString('"payload":{"items":[1,2]}', $this->factory->toJson($job));
    }

    public function testReplacingAListNodeWithAMapEncodesAsAnObject(): void
    {
        $node = Payload::fromJsonValue(json_decode('[1,2]', false));

        $job = new Job(name: self::NAME, payload: new Payload(['items' => $node->with(['a' => 1])]));

        self::assertStringContainsString('"payload":{"items":{"a":1}}', $this->factory->toJson($job));
    }

    public function testEmptyingAListNodeKeepsItAnArray(): void
    {
        // Emptying a node does not reshape it, and an empty list and an empty
        // map are different envelopes to a consumer in another language
        $node = Payload::fromJsonValue(json_decode('[1,2]', false));

        $job = new Job(name: self::NAME, payload: new Payload(['ids' => $node->with([])]));

        self::assertStringContainsString('"payload":{"ids":[]}', $this->factory->toJson($job));
    }

    public function testEmptyingAMapNodeKeepsItAnObject(): void
    {
        $node = Payload::fromJsonValue(json_decode('{"a":1}', false));

        $job = new Job(name: self::NAME, payload: new Payload(['meta' => $node->with([])]));

        self::assertStringContainsString('"payload":{"meta":{}}', $this->factory->toJson($job));
    }

    public function testAppendingToAListNodeKeepsItAnArray(): void
    {
        // The append decides whether the node re-encodes as a JSON array or as
        // an object with numeric string keys
        $node = Payload::fromJsonValue(json_decode('[1]', false));

        $job = new Job(name: self::NAME, payload: new Payload(['ids' => $node->withAdded([1 => 2])]));

        self::assertStringContainsString('"payload":{"ids":[1,2]}', $this->factory->toJson($job));
    }

    public function testAppendingAStringKeyToAListNodeMakesItAnObject(): void
    {
        $node = Payload::fromJsonValue(json_decode('[1]', false));

        $job = new Job(name: self::NAME, payload: new Payload(['ids' => $node->withAdded(['named' => 2])]));

        self::assertStringContainsString('"payload":{"ids":{"0":1,"named":2}}', $this->factory->toJson($job));
    }

    public function testFromArrayKeepsAnObjectOutOfANestedPayloadList(): void
    {
        // A `fromArray()` caller can hand over any object, and array-casting one
        // would put its own properties on the wire, NUL-mangled where private
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        $this->factory->fromArray([
            EnvelopeField::NAME    => self::NAME,
            EnvelopeField::PAYLOAD => [new DateTimeImmutable()],
        ]);
    }

    public function testAddingNothingToAnEmptyListNodeKeepsItAnArray(): void
    {
        $node = Payload::fromJsonValue(json_decode('[]', false));

        $job = new Job(name: self::NAME, payload: new Payload(['ids' => $node->withAdded([])]));

        self::assertStringContainsString('"payload":{"ids":[]}', $this->factory->toJson($job));
    }

    public function testAnEnvelopeWhosePayloadIsAnArrayOfObjectsIsReadable(): void
    {
        // The decode keeps a JSON object apart from a JSON array, so the
        // elements of a top-level array arrive as objects rather than arrays
        $job = $this->factory->fromJson('{"name":"X","payload":[{"id":1}]}');

        self::assertSame([0 => ['id' => 1]], $job->getPayload()->asArray());
    }

    public function testFromArrayAcceptsAnAlreadyBuiltPayload(): void
    {
        // The signature takes mixed, so a caller can hand over a payload it
        // already built. Casting it like a decoded object would read the
        // payload's own properties instead of its params.
        $payload = Payload::fromArray(['user_id' => 42]);

        $job = $this->factory->fromArray(['name' => self::NAME, 'payload' => $payload]);

        self::assertSame($payload, $job->getPayload());
        self::assertSame(['user_id' => 42], $job->getPayload()->asArray());
    }

    public function testThePayloadFieldIsAlwaysAnObject(): void
    {
        // Whatever its keys look like, the envelope's payload field is an object
        $json = $this->factory->toJson($this->factory->fromJson('{"name":"X","payload":[1,2]}'));

        self::assertStringContainsString('"payload":{"0":1,"1":2}', $json);
    }

    public function testJsonRoundTrip(): void
    {
        $job = $this->factory->fromArray(self::fullEnvelope());

        self::assertSame(
            $job->asArray(),
            $this->factory->fromJson($this->factory->toJson($job))->asArray()
        );
    }

    #[DataProvider('nonObjectJsonProvider')]
    public function testFromJsonRejectsValidJsonThatCarriesNoObject(string $json): void
    {
        // A consumer declares one failure for a body it cannot read, so this
        // cannot surface as a throwable from the array component
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        $this->factory->fromJson($json);
    }

    public function testFromArrayRejectsANonScalarAttributeValue(): void
    {
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        $this->factory->fromArray(['name' => 'SendWelcomeEmail', 'attributes' => ['a' => [null]]]);
    }

    public function testFromArrayRejectsANonScalarPayloadParam(): void
    {
        // The payload read sits inside the same guard as the attributes read,
        // so a param it cannot accept reads as a bad envelope too
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        $this->factory->fromArray(['name' => 'SendWelcomeEmail', 'payload' => ['a' => new stdClass()]]);
    }

    public function testFromArrayKeepsAnObjectOutOfADecodedObjectPayload(): void
    {
        // The stdClass narrowing holds at every depth, not only the top: a
        // decoded object payload rejects a foreign object the same way a plain
        // array payload does
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        $this->factory->fromArray([
            EnvelopeField::NAME    => self::NAME,
            EnvelopeField::PAYLOAD => (object) ['at' => new DateTimeImmutable()],
        ]);
    }

    public function testFromArrayKeepsABuiltPayloadInsideADecodedObjectPayload(): void
    {
        // A built payload is a valid param, so it passes through rather than
        // being array-cast, which would drop everything it holds
        $job = $this->factory->fromArray([
            EnvelopeField::NAME    => self::NAME,
            EnvelopeField::PAYLOAD => (object) ['user' => Payload::fromArray(['id' => 1])],
        ]);

        self::assertSame(['user' => ['id' => 1]], $job->getPayload()->asArray());
    }

    public function testCreateRejectsANonScalarPayloadParam(): void
    {
        // create() is producer-facing, so the payload throwable reaches the
        // caller as itself rather than as an envelope failure
        $this->expectException(QueueMessageInvalidPayloadParamException::class);

        $this->factory->create('SendWelcomeEmail', ['a' => new stdClass()]);
    }

    public function testFromArrayRejectsAnEmptyAttributeName(): void
    {
        // A bad name and a bad value are siblings under one abstract, so both
        // reach the caller as the one failure the contract declares
        $this->expectException(QueueMessageInvalidEnvelopeException::class);

        $this->factory->fromArray(['name' => 'SendWelcomeEmail', 'attributes' => ['' => ['v']]]);
    }
}

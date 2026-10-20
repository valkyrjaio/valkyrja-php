# Queue

## Introduction

The Queue component runs a job outside the request that produced it. A producer
dispatches a `JobContract` through a client. A consumer receives the same job,
runs it through a middleware pipeline, and settles the outcome with the
processor.

The pipeline takes a job in and returns a `JobResult` out. There is no response
envelope, because no caller waits for one.

This document describes how the contracts interact.

## Writing a Job Handler

A job names a route, and the route holds the handler that runs the job. Every
handler has this signature:

```php
callable(ContainerContract $container, RouteContract $route): JobResult
```

The router calls the handler with the container and the matched route. The
handler reads the job from the container, does the work, and returns the
outcome:

```php
use App\Notification\Contract\NotifierContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Routing\Attribute\Route;
use Valkyrja\Queue\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;

final class SendWelcomeNotificationHandler
{
    #[Route(name: 'SendWelcomeNotification', description: 'Send the welcome notification')]
    #[RouteHandler([self::class, 'handle'])]
    public static function handle(ContainerContract $container, RouteContract $route): JobResult
    {
        $payload = $container->getSingleton(JobContract::class)->getPayload();
        $message = $payload->get('message');

        if (!is_string($message)) {
            return JobResult::FAIL;
        }

        $notifier = $container->getSingleton(NotifierContract::class);
        $notifier->notify($message);

        return JobResult::ACK;
    }
}
```

The payload holds the data the producer sent. A retry cannot fix a bad payload,
so the handler returns `FAIL`.

The attributes hold the metadata that middleware and the handler read. The
middleware example below reads an attribute.

## Registering a Route

A route provider tells the application where the routes are. Implement
`QueueRouteProviderContract`. `getControllerClasses()` names the handler
classes that the collector reads routes from, and `getRoutes()` returns the
routes the application builds in code:

```php
use Valkyrja\Queue\Routing\Provider\Contract\QueueRouteProviderContract;

final class AppQueueRouteProvider implements QueueRouteProviderContract
{
    public function getControllerClasses(): array
    {
        return [SendWelcomeNotificationHandler::class];
    }

    public function getRoutes(): array
    {
        return [];
    }
}
```

A component provider gives the route provider to the application.
`QueueApplicationComponentProvider` registers the queue components, and an
application extends it to return its own route providers:

```php
use Override;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\QueueApplicationComponentProvider;

final class AppQueueComponentProvider extends QueueApplicationComponentProvider
{
    #[Override]
    public function getQueueProviders(ApplicationContract $app): array
    {
        return [new AppQueueRouteProvider()];
    }
}
```

The application config names that component provider, as the next section
shows.

## Returning an Outcome

`Valkyrja\Queue\Message\Enum\JobResult` holds the four outcomes:

| Outcome       | What it means                                                       |
| ------------- | ------------------------------------------------------------------- |
| `ACK`         | The job is done. The processor removes the job.                     |
| `RETRY`       | The job runs again after its retry delay.                           |
| `FAIL`        | The handler gives up. The job goes to the dead-letter destination.  |
| `DEAD_LETTER` | The retry chain ended. The job goes to the dead-letter destination. |

A handler returns `ACK`, `RETRY`, or `FAIL`. The framework returns
`DEAD_LETTER`. `FAIL` is the handler's own decision, and `DEAD_LETTER` is a
retry chain that reached the job's max attempts.

Two methods read an outcome. `JobResult::isTerminal()` reports that the job's
life is over, and every outcome but `RETRY` is terminal.
`JobResult::isDeadLettered()` reports that the job goes to the dead-letter
destination.

## Marking a Throwable Non-Retryable

The pipeline must not retry a throwable that carries
`Valkyrja\Queue\Throwable\Contract\QueueNonRetryableThrowable`. Add the
contract to an application throwable that a retry cannot fix:

```php
use Valkyrja\Queue\Throwable\Contract\QueueNonRetryableThrowable;
use Valkyrja\Throwable\Exception\Abstract\ValkyrjaRuntimeException;

final class InvalidRecipientException extends ValkyrjaRuntimeException implements QueueNonRetryableThrowable
{
}
```

## Writing Middleware

Each stage has its own contract in `Valkyrja\Queue\Middleware\Contract`. A
middleware class implements the contract of each stage it runs in:

| Contract                            | Runs when                                |
| ----------------------------------- | ---------------------------------------- |
| `JobReceivedMiddlewareContract`     | the job arrives, before routing          |
| `RouteMatchedMiddlewareContract`    | the router finds a route                 |
| `RouteNotMatchedMiddlewareContract` | the router finds no route                |
| `RouteDispatchedMiddlewareContract` | the handler returns                      |
| `ThrowableCaughtMiddlewareContract` | an earlier stage throws                  |
| `SettlingResultMiddlewareContract`  | before the outcome reaches the processor |
| `ResultSettledMiddlewareContract`   | after the outcome reaches the processor  |

Each middleware calls its handler to continue, and returns the type its own
stage declares. A `JobReceived` or a `RouteMatched` middleware returns a
`JobResult` instead, to stop the job before the handler runs:

```php
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Middleware\Contract\JobReceivedMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Contract\JobReceivedHandlerContract;

final class RequireTenantAttributeMiddleware implements JobReceivedMiddlewareContract
{
    public function jobReceived(
        JobContract $job,
        JobReceivedHandlerContract $handler
    ): JobContract|JobResult {
        if ($job->getAttributes()->getFirst('tenant') === null) {
            return JobResult::FAIL;
        }

        return $handler->jobReceived($job);
    }
}
```

## Configuration

`Valkyrja\Application\Data\Contract\QueueConfigContract` is the application
config a queue consumer boots from. It adds one middleware array for each stage
to the properties every application config carries. Each array holds the class
name of a middleware for that stage.

`Valkyrja\Application\Data\QueueConfig` implements that contract, and an
application config extends it and passes only the settings it changes:

```php
use Valkyrja\Application\Data\QueueConfig;

final class AppQueueConfig extends QueueConfig
{
    public function __construct()
    {
        parent::__construct(
            providers: [new AppQueueComponentProvider()],
            jobReceivedMiddleware: [RequireTenantAttributeMiddleware::class],
        );
    }
}
```

A property the constructor does not name keeps its default.
`throwableCaughtMiddleware` defaults to `LogThrowableCaughtMiddleware` and
`RetryPolicyThrowableCaughtMiddleware`, so an application that names that
property replaces both and the retry policy stops running.

`QueueConfig` carries the shared properties, and the
[Application README](../Application/README.md#configuration) covers each one.

## The Job

`Valkyrja\Queue\Message\Job\Contract\JobContract` is the one message class, and
it travels in both directions. A job is immutable: the framework builds a new
one through a `with*` method, the same way an HTTP request and a CLI input work.

The envelope is a cross-language contract, so every field is always present. A
millisecond field is authoritative, and the matching `_iso` field is derived
from it. `Valkyrja\Queue\Message\Constant\EnvelopeField` names each wire field.

A job carries a payload and a set of attributes.
`Valkyrja\Queue\Message\Payload\Contract\PayloadContract` holds the data the
handler reads. `Valkyrja\Queue\Message\Attributes\Contract\AttributesContract`
holds the metadata that middleware and the handler read. Both are immutable for
the same reason the job is.

`Valkyrja\Queue\Message\Job\Factory\Contract\JobFactoryContract` builds a job,
and renders one for the wire.

## The Pipeline

A job runs through seven middleware stages. Each stage has its own contract in
`Valkyrja\Queue\Middleware\Contract`, and
[Writing Middleware](#writing-middleware) lists each contract with the
condition it runs under.

Each stage has a matching handler contract in
`Valkyrja\Queue\Middleware\Handler\Contract`, which holds the middleware of
that stage and runs it in order.

`Valkyrja\Queue\Server\Handler\Contract\JobHandlerContract` runs a job through
those stages. An entry calls `run`, which returns the outcome, and calls
`resultSettled` last. Settling the outcome with the processor is the entry's
own work, and it belongs between the two calls. `run` is `handle` plus
`settlingResult`. An entry calls `handle` and `settlingResult` separately when
it has to change the outcome between them.

`JobReceived`, `SettlingResult`, and `ResultSettled` always run. The other four
are conditional. A `JobReceived` middleware that returns an outcome
short-circuits the pipeline, and the router never runs. The router otherwise
chooses `RouteMatched` or `RouteNotMatched`. `RouteDispatched` runs once the
handler returns, and `ThrowableCaught` runs when any earlier stage throws.

The outcome is one of four `JobResult` cases: `ACK`, `RETRY`, `FAIL`, or
`DEAD_LETTER`. A `ThrowableCaught` middleware dead-letters a throwable that
carries `Valkyrja\Queue\Throwable\Contract\QueueNonRetryableThrowable`, rather
than retrying it.

Route middleware is appended and never deduplicated. A middleware registered
twice runs twice.

## Throwables

`Valkyrja\Queue\Throwable\Contract\QueueThrowable` marks every throwable the
component raises, and each sub-component narrows it with its own marker, as
`QueueMessageThrowable` and `QueueServerThrowable` do. Each sub-component
marker has an abstract `*InvalidArgumentException` and `*RuntimeException`
pair. A concrete exception extends one of its own sub-component's pair, so a
caller can catch one sub-component or the whole component.
`QueueNonRetryableThrowable` narrows `QueueThrowable` as well, so
`catch (QueueThrowable)` catches a throwable that carries it. That marker is
cross-cutting rather than per sub-component. It marks a throwable the pipeline
must not retry, whatever raises it.

## The Client

`Valkyrja\Queue\Client\Manager\Contract\ClientContract` is the producer
surface. `push` enqueues a fresh job. `requeue` and `retry` settle a retry, the
first taking the job as dispatched and the second the already incremented copy.
`getPushed` returns the jobs handed to the client during one unit of work, and
`clearPushed` ends that unit of work.

`requeue` calls `retry` with the hold already supplied, and
[Redelivery](#redelivery) covers where that hold comes from. The framework
reaches neither on a processor that owns redelivery, because that processor's
entry answers the retry from its own settlement.

Both seams record the job in `getPushed`. `PullQueue` clears that record
before each job, so the record of the last job stays readable once the loop
exits. No other entry clears it, so an application that pushes through a client
of its own ends each unit of work itself.

## Redelivery

Each processor settles its own outcomes, so the entry of the processor owns
redelivery. A broker with a native retry loop translates the outcome into its
own signal. Redis and a database have no native retry, so their entry calls
`ClientContract::requeue()` instead.

`requeue()` builds a new job with the attempt incremented and the modification
time stamped, then publishes it. The hold comes from the job that was
dispatched, read before the increment, so the ramp is keyed to the attempt that
just failed.

A broker that redelivers a job itself owns the retry counter instead. A retry is
signaled by the consumer — a nack, a new visibility timeout, a release — and
never by publishing the job again. Publishing again would duplicate the message,
because the original delivery is still unacknowledged. The entry of such a
broker answers it directly from `settle`, and never calls
`ClientContract::requeue()`.

The entry still supplies the hold, where the broker accepts one. `SqsQueue` sets
the visibility timeout from the ramp, `PubSubQueue` sets the acknowledgement
deadline from it, and `BeanstalkdQueue` passes the same ramp to the release, so
the hold is the job's own and not the queue's default.
`AmqpQueue` passes none, because a nack gives the broker no place to put one, so
that broker redelivers on its own schedule.

Warning: the count such a broker reports has to reach the ceiling, or nothing
dead-letters. A classic AMQP queue reports only that a delivery is a
redelivery, not which one, so `AmqpQueue` cannot count past the second attempt.
Pub/Sub reports its count only on a subscription that carries a dead-letter
policy, and without one `PubSubQueue` never advances the attempt at all.

A quorum queue reports every attempt, so use one when the AMQP ceiling has to
hold. Set the vhost's `default_queue_type` to `quorum` to get it. The framework
declares the queue with no `x-queue-type`, so a queue the operator declared as
quorum answers `PRECONDITION_FAILED` on the next declare and the worker cannot
start; the vhost default carries no such argument and so cannot collide. The
same holds for `x-max-priority`, `x-message-ttl`, and `x-dead-letter-exchange`
set as queue arguments.

A dead-letter exchange alone does not end a retry chain: a classic queue
dead-letters on a nack that does not requeue, on a message TTL, or on a length
overflow, and a retry answers with a nack that does requeue. A `max_attempts` of
2 also holds, because the second attempt is the one a classic queue can report.

Warning: the default of `max_attempts` is 5, and a classic queue reports an
attempt count of 2 at most, so a job that always fails on a classic queue
retries until something else stops it, with no hold between attempts. Push to a
quorum queue, or give the job a `max_attempts` of 2.

## Clients

| Client             | Broker     | Redelivery |
| ------------------ | ---------- | ---------- |
| `SyncClient`       | none       | framework  |
| `DeferredClient`   | none       | framework  |
| `InMemoryClient`   | none       | framework  |
| `RedisClient`      | Redis      | framework  |
| `AmqpClient`       | AMQP       | processor  |
| `SqsClient`        | SQS        | processor  |
| `BeanstalkdClient` | beanstalkd | processor  |
| `DatabaseClient`   | a database | framework  |
| `PubSubClient`     | Pub/Sub    | processor  |

`SyncClient` and `DeferredClient` hand each job to the `InternalQueue` entry of
the application. The entry runs a separate queue application, so the job runs
the same way that a job from a broker runs.

`SyncClient` runs the job inline and blocks, and it runs the whole retry chain.
There is no durable place to hold a retry delay, so the delay is skipped and the
incremented job runs again at once. Only the timing differs from production, and
the retry count is identical.

Warning: a `SyncClient` push throws on a terminal `FAIL` or `DEAD_LETTER`. The
caller blocks until the job finishes, so the caller is still there to be told.
Every other client throws only on an enqueue error. This is the one deliberate
difference between the clients.

`DeferredClient` buffers the job, and the application drains the buffer itself
from the terminate stage of its host. Nothing in the framework calls `drain()`,
so a buffer that nobody drains never runs. It is not durable, and it needs a
host runtime that can keep working after the response.

`AmqpClient` refuses `retry` and `requeue` with
`QueueClientRedeliveryNotSupportedException`. The broker still holds the
unacknowledged delivery, so the entry answers a retry from its own settlement
and the client has nothing to hand back.

`SqsClient` refuses `retry` and `requeue` with the same throwable, and for the
same reason: the queue still holds the delivery, so the entry answers a retry by
changing its visibility rather than by publishing again.

`BeanstalkdClient` refuses both as well. The job stays reserved, so the entry
answers a retry by releasing it with a priority and a hold.

`PubSubClient` refuses both for the same reason. The delivery is still
unacknowledged, so the entry answers a retry by moving its acknowledgement
deadline rather than by publishing again.

`AmqpClient` and `PubSubClient` publish without a hold, because neither broker
carries a per-message delay. Giving AMQP one needs a delay queue and a
dead-letter exchange that the broker owner declares rather than the client, and
Cloud Pub/Sub has no equivalent at all. A job pushed with `delay_ms` is
therefore consumable as soon as it lands on either. The Redis, SQS, beanstalkd,
and database clients each apply the hold at enqueue.

`SqsClient` takes whole seconds, so a `delay_ms` below one second becomes no
delay at all and the job is consumable at once. A retry hold rounds the other
way: `SqsQueue` raises a sub-second hold to one second, because a visibility of
zero would return the delivery with no hold.

`BeanstalkdClient` rounds a sub-second hold up to one second instead, on the
publish and on the release alike, so a beanstalkd job never loses a hold the
producer asked for.

beanstalkd counts priority the other way around from the job: 0 is the most
urgent and a larger number is less urgent, so the client subtracts the job's
priority from 1024. A job priority is clamped to 1024 first, so every priority
of 1024 or more arrives as the most urgent one.

`AmqpClient` carries the job's priority onto the message, and a classic queue
orders by it only when the broker gave the queue a priority bound. The framework
declares no bound, so a default setup delivers in publish order and carries the
priority without effect.

Warning: `AmqpQueue` rejects a body no factory can read without requeueing it,
so the broker decides where it goes. A queue with a dead-letter exchange keeps
it, and a queue without one drops it. The framework declares no such exchange,
because the queue argument that names one belongs to the broker owner, so a
deployment that must keep an unreadable body declares the policy itself. This is
the discard that `RedisQueue`'s unreadable list avoids, and AMQP's own
redelivery is why the entry answers the broker rather than parking the bytes.

Warning: RabbitMQ carries no redelivery backoff, and `AmqpQueue` answers a retry
with a nack that requeues at once. A job on that broker retries with no hold at
all, as fast as the worker can run it, and `retry_delay_ms` and
`retry_delay_multiply_by_attempt` do nothing for this adapter.

Warning: a client scopes `getPushed` to one request, one command, or one job. A
client that keeps a process-global record leaks in a long-running server, and it
gives one request the deferred jobs of the request before it.

## The Database Table

`DatabaseClient` and `DatabaseQueue` read and write one table. The application
owns the table, so the application creates it. Every statement the adapter
issues is portable across the shipped ORM managers, but the table definition is
not, so this one is MySQL and a note below gives the columns that differ:

```sql
-- MySQL
CREATE TABLE queue_jobs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    queue           VARCHAR(255)    NOT NULL,
    envelope        LONGTEXT        NOT NULL,
    priority        INT             NOT NULL DEFAULT 0,
    available_at_ms BIGINT          NOT NULL,
    reserved_at_ms  BIGINT          NULL,
    INDEX queue_jobs_claim (queue, available_at_ms, priority)
);
```

On PostgreSQL the same table reads `BIGSERIAL PRIMARY KEY` for `id`, `TEXT` for
`envelope`, `BIGINT` for the two millisecond columns, and the index becomes its
own statement, because `AUTO_INCREMENT`, `BIGINT UNSIGNED`, `LONGTEXT`, and an
inline `INDEX` clause are each rejected:

```sql
-- PostgreSQL
CREATE TABLE queue_jobs (
    id              BIGSERIAL    PRIMARY KEY,
    queue           VARCHAR(255) NOT NULL,
    envelope        TEXT         NOT NULL,
    priority        INT          NOT NULL DEFAULT 0,
    available_at_ms BIGINT       NOT NULL,
    reserved_at_ms  BIGINT       NULL
);

CREATE INDEX queue_jobs_claim ON queue_jobs (queue, available_at_ms, priority);
```

The index narrows on `queue` by equality and then on `available_at_ms` by range.
It cannot serve the `ORDER BY priority DESC, id ASC` that the claim select ends
with, because a b-tree stops narrowing at the first column carrying a range, so
the database sorts the eligible rows on every poll. Tune the index for the
queue's own shape when the eligible set grows large enough to matter.

`id` may be any key the driver reads back as an integer or a string, so a
`CHAR(36)` UUID works. The column has to supply its own value, because the
client never writes it: `INSERT` names `queue`, `envelope`, `priority`,
`available_at_ms`, and `reserved_at_ms` only. `AUTO_INCREMENT` supplies it
above, and a UUID key needs a database-side default such as `DEFAULT (UUID())`
or `gen_random_uuid()`.

Warning: give the key a default, and give it `NOT NULL`. A column that permits
a null takes the push without complaint, and sqlite permits one on a primary
key unless the column is `INTEGER PRIMARY KEY` or the table is
`WITHOUT ROWID`. Nothing names a row with a null key, so the entry parks every
such row in the queue rather than running it. The entry claims, parks and
deletes by an integer or a string key, so a key the driver reads back as any
other type raises `QueueServerInvalidRowKeyException`: the row can be given
none of the three, and the entry stops rather than hand it back on every poll.

Warning: a key unrelated to insertion time gives up the first-in-first-out
tiebreak, because the claim orders by `id ASC` among equal priorities. A random
UUID sorts lexically rather than by age, so equal-priority jobs run in no
particular order. The queue still drains.

`DatabaseQueue` claims a row by stamping `reserved_at_ms`, which is what stops
two workers taking the same job: the claim is one conditional update, so the
second worker writes nothing. A reservation older than
`queueDatabaseClientReservationTimeoutMs` counts as free, so a row that a
crashed worker abandoned returns to the queue.

A settlement deletes the row only while this worker still holds the claim it
stamped. A worker whose window lapsed therefore takes nothing from the worker
that reclaimed the row. A retry writes the new row before the old one goes, so
a failure between the two leaves a duplicate rather than no job at all.

A row the entry cannot run is parked rather than deleted. An unreadable
`envelope` is parked by its own key, and a null key is parked by a predicate on
the whole keyless set, which is the one write such a row can be given. The
entry stamps both `reserved_at_ms` and `available_at_ms` with the last
whole second of the year 9999. No staleness window reaches back to that
reservation, and the availability stamp takes the row out of the range the claim
index narrows to, so a parked row costs no later poll anything and keeps its
bytes. Select on that stamp to find what a worker could not read. A settled job
is deleted as usual, so the table holds only live work and whatever was parked.

Nothing drains the parked rows. A deploy that changes the envelope shape parks
every row it cannot read, in one pass, so treat a growing parked count as the
signal it is — and delete them once you have read them.

## Entry Points

| Entry             | Runs                                                  |
| ----------------- | ----------------------------------------------------- |
| `Queue`           | one job, then exits                                   |
| `PullQueue`       | the poll loop that a processor entry extends          |
| `RedisQueue`      | a worker that takes jobs from a redis list            |
| `AmqpQueue`       | a worker that consumes an AMQP queue                  |
| `SqsQueue`        | a worker that long-polls an SQS queue                 |
| `BeanstalkdQueue` | a worker that reserves jobs from a tube               |
| `DatabaseQueue`   | a worker that claims rows from a table                |
| `PubSubQueue`     | a worker that pulls a Pub/Sub subscription            |
| `PushQueue`       | one job that a broker delivers over HTTP              |
| `InternalQueue`   | each job that `SyncClient` or `DeferredClient` pushes |

`PullQueue` is abstract, because polling and settling are specific to one
processor. An entry such as `RedisQueue` implements `connect`, `receive`,
`disconnect`, and `settle`, and inherits the loop. `PushQueue` is concrete,
because the default envelope needs no processor-specific mapping.

Redis owns no delivery acknowledgement, so `RedisQueue` keeps the ownership
window itself: a receive moves the envelope onto an in-flight list in the same
step that takes it off the ready list, and a settlement removes it from there
last, so an interrupted settlement leaves a duplicate delivery rather than none.
An envelope no factory can read is parked on an unreadable list instead of being
discarded. A broker that owns its own acknowledgement returns an unacknowledged
delivery by itself, so only redis needs the list.

A job with a hold waits on a sorted set rather than the ready list, scored by
the instant it becomes eligible. A sorted set keeps one copy of a member, so
each member is a uniqueness token, a `|`, and then the envelope. Without the
token, two pushes of one job inside a single millisecond write the same member
twice and the queue delivers it once.

The promote script reads the token by its own alphabet, which holds no brace.
An envelope opens with one, so a member an older client wrote carries no token
the script can read and promotes whole, however many `|` its payload holds.

Warning: upgrade the workers before the producers. A worker reads the token
with the script it was deployed with, so a worker still running an older one
hands a tokenized member to the ready list whole, and the token in front of the
envelope is what makes it unreadable. Taking the workers first leaves them
ready for both shapes, because the older shape promotes whole either way.

Warning: an SQS redrive policy never sees a job the framework dead-letters.
`SqsQueue` deletes the message on `FAIL` and on `DEAD_LETTER`, and SQS routes to
a dead-letter queue on the receive count at receive time, so a message deleted
here never reaches one. The attempt ceiling of the job is what ends the chain,
and the entry leaves the rest to the queue rather than handing a job back that
the framework already called dead. A deployment that needs the copy sets
`maxReceiveCount` below the job's `max_attempts`, so SQS routes it first.

Warning: `BeanstalkdQueue` buries a body no factory can read. A buried job
stays on the tube for inspection, so nothing is lost, but nothing in the
framework kicks or deletes it either: a producer that emits bad bytes
accumulates buried jobs until an operator kicks or deletes them.

Warning: a Pub/Sub dead-letter topic never sees a job the framework
dead-letters. `PubSubQueue` acknowledges the message on `FAIL` and on
`DEAD_LETTER`, and Pub/Sub routes to a dead-letter topic on the delivery
attempt, so a message acknowledged here never reaches one. This is sharper than
it reads: the attempt ceiling needs that same dead-letter policy to count at
all, so the policy an operator adds for the ceiling is the one the ceiling then
bypasses. A deployment that needs the copy sets `maxDeliveryAttempts` below the
job's `max_attempts`, so Pub/Sub routes it first.

Warning: that only works above the default. Pub/Sub takes a
`maxDeliveryAttempts` between 5 and 100, and a job's `max_attempts` defaults to
5, so there is no value below it to set. Raise `max_attempts` past 5 on the job
before the dead-letter topic can see anything.

`PubSubQueue` pulls the subscription named after the topic. An application
whose subscription carries another name sets `PubSubQueue::$subscriptionName`
on its own entry, and sets `PubSubQueue::$timeoutMs` there to change how long
one pull waits. A name that matches no subscription fails the first pull.

Warning: on the REST transport the entry cannot tell an idle poll from an
endpoint that drops traffic. The deadline it sets bounds the whole transfer, so
a connect that never completes times out at the same cURL code and the same
instant as a pull that found nothing. Such a worker keeps polling an endpoint
that answers nothing, and keeps reporting healthy. The gRPC transport names the
deadline and does not have this problem, so prefer it where a silent outage
costs more than the dependency.

Warning: `PubSubQueue` acknowledges a body no factory can read rather than
parking it, as `SqsQueue` does, and nothing keeps a copy. A dead-letter topic
does not help here: it takes a message on the delivery attempt, and an
acknowledgement removes the message before any attempt count can route it. A
producer that emits bytes no factory reads therefore loses them, so read the
envelope at the producer rather than relying on the subscription to hold it.

Warning: `SqsQueue` deletes a body no factory can read rather than parking it,
and nothing keeps a copy. A redrive policy does not help here: it moves a
message on the receive count, and a delete removes the message before that
count can route it. Nothing settles an unreadable body, so the delete is what
stops SQS handing the same message back on every visibility timeout. A producer
that emits bytes no factory reads therefore loses them, so read the envelope at
the producer rather than relying on the queue to hold it.

The in-flight list belongs to one worker slot, named by
`queueRedisClientWorkerName`. That
slot reads its own list and no other, so a worker returns its held envelope to
the ready list on the way out and takes back whatever a previous run of the same
slot left behind on the way in. A graceful stop therefore leaves nothing in
flight, and a worker killed mid-job redelivers when that slot restarts.

Warning: give each worker its own `queueRedisClientWorkerName` — a pod ordinal, a
supervisor slot number. Two workers sharing a name share an in-flight list, and
then a restart of one hands the other's running job back to the ready list as a
second delivery. A slot that is retired rather than restarted leaves its list
for an operator to re-enqueue, because nothing tracks whether a slot returns.

Warning: a reclaim rewrites nothing, so a redelivered job keeps the attempt
count it had. A job that kills its worker every time — an out-of-memory, or an
execution timeout — comes back every time, and no attempt ceiling ends it. The
reclaim pushes onto the tail so the rest of the queue still drains around it,
and the `:unreadable` list holds only what the factory rejected, not this. Watch
the in-flight list of a slot that keeps restarting.

`Queue` is single-shot, so a host that pushes repeatedly pays a full boot per
push. It settles nothing, because the signal that settles an outcome belongs to
a processor. `WorkerQueue` boots the application once and then gives each job a
fresh child container, which is the shape a real broker worker loops over.

Every job runs through an entry point, never through `JobHandler` directly. An
entry that extends `WorkerQueue` gives each job a fresh child container, so one
job cannot reach what the job before it bound. `Queue` and `PushQueue` run their
one job in the container of the application, because each call builds a fresh
application and a fresh container of its own.

An application extends `InternalQueue` and returns its queue config from
`getConfig()`. The client boots the queue application on the first job, and it
runs every later job in a fresh child container. The entry restores the base
path and the timezone of the host after each step, and it leaves the exception
handler of the host in place.

```php
use App\Queue\Config;
use Valkyrja\Application\Data\Contract\QueueConfigContract;
use Valkyrja\Application\Entry\Abstract\InternalQueue;

final class InternalApp extends InternalQueue
{
    public static function getConfig(): QueueConfigContract
    {
        return new Config();
    }
}
```

## Routing

A job names a route, and
`Valkyrja\Queue\Routing\Dispatcher\Contract\RouterContract` turns that name
into the handler that runs it.
`Valkyrja\Queue\Routing\Collection\Contract\RouteCollectionContract` holds the
routes. `Valkyrja\Queue\Routing\Collector\Contract\RouteCollectorContract`
gathers them from the classes an application names, and
`Valkyrja\Queue\Routing\Provider\Contract\QueueRouteProviderContract` is how an
application names those classes and contributes routes in code.

A component provider returns its queue route providers from its
`getQueueProviders()` method.

A controller declares a route with `Valkyrja\Queue\Routing\Attribute\Route`
on a method. The attribute takes the job name, a description, the handler, and
a middleware array for each stage a route supports. `JobReceived` runs before
routing and `RouteNotMatched` runs when there is no route, so those two take
their middleware from the application config instead.

Three modifier attributes keep a route with several settings readable, each on
its own line. `Route\RouteHandler` names the handler. `Route\Middleware` adds
a middleware class to every stage whose contract that class implements.
`Route\Name` on the class prefixes the job name, and on the method it
suffixes it.

A route names its handler with `Route\RouteHandler` or with the `handler`
argument of `Route`. A `RouteHandler` wins when a route carries both. The
collector rejects a route that names neither, and the failure names the class
and the method, so a forgotten handler stops the boot rather than acknowledging
each job it receives.

```php
#[Name('acme')]
final class SendWelcomeEmailHandler
{
    public static function handle(ContainerContract $container, RouteContract $route): JobResult
    {
        return JobResult::ACK;
    }

    #[Route(name: 'SendWelcomeEmail', description: 'Send the welcome email')]
    #[RouteHandler([self::class, 'handle'])]
    public function sendWelcomeEmail(): JobResult
    {
        return JobResult::ACK;
    }
}
```

`Valkyrja\Queue\Routing\Data\QueueRoutingData` is the generated cache of that
collection, so a production boot reads the routes from a data class instead of
gathering them again.

The same route runs the job whatever client produced it, and whatever entry
received it.

## Client Configuration

`Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract` names the
default client, and an application config may implement it to choose one.
`QueueConfigContract` is a separate thing: it configures a queue application
rather than naming a client.

A client stamps the `applicationName` of the application that pushes the job
into the producer field of the job.

The service provider binds `ClientContract` to the client that
`QueueClientConfigContract::$defaultQueueClient` names, and the default is
`RedisClient`. Each client reads its own config contract, and an application
config implements the contract of each client that the application uses.

`SyncClient` and `DeferredClient` have no default, because the entry names the
queue config of the application. The service provider throws
`QueueClientConfigNotFoundException` when it builds one of the two clients for
an application config that does not implement its contract.

### `QueueClientConfigContract`

| Property             | Default              | Description                               |
| :------------------- | :------------------- | :---------------------------------------- |
| `defaultQueueClient` | `RedisClient::class` | The client that `ClientContract` binds to |

### `QueueSyncClientConfigContract`

| Property    | Default | Description                                  |
| :---------- | :------ | :------------------------------------------- |
| `syncEntry` | none    | The `InternalQueue` entry that runs each job |

### `QueueDeferredClientConfigContract`

| Property        | Default | Description                                  |
| :-------------- | :------ | :------------------------------------------- |
| `deferredEntry` | none    | The `InternalQueue` entry that runs each job |

### `QueueRedisClientConfigContract`

| Property                     | Default            | Description                                                 |
| :--------------------------- | :----------------- | :---------------------------------------------------------- |
| `queueRedisClientHost`       | `'127.0.0.1'`      | Redis host                                                  |
| `queueRedisClientPort`       | `6379`             | Redis port                                                  |
| `queueRedisClientPrefix`     | `''`               | Prepended to every key this client and its worker build     |
| `queueRedisClientQueue`      | `'queues:default'` | The list key jobs are pushed onto                           |
| `queueRedisClientWorkerName` | `'default'`        | Names this worker's slot, which owns its own in-flight list |

The properties carry the component and the subcomponent because Cache configures
redis too, and `CacheRedisConfigContract` holds the bare `redisHost`. An
application config that implements `CacheRedisConfigContract` and not this one
gets this contract built from that redis: the host, the port, and the prefix come
across, so one redis serves the cache and the queue without being named twice.
The worker name does not come across, because it names a slot rather than a
connection.

#### `QueueAmqpClientConfigContract`

| Property                  | Default            | Description                                            |
| :------------------------ | :----------------- | :----------------------------------------------------- |
| `queueAmqpClientHost`     | `'127.0.0.1'`      | AMQP host                                              |
| `queueAmqpClientPort`     | `5672`             | AMQP port                                              |
| `queueAmqpClientUser`     | `'guest'`          | The user to connect as                                 |
| `queueAmqpClientPassword` | `'guest'`          | The password of the user                               |
| `queueAmqpClientVhost`    | `'/'`              | The virtual host to connect to                         |
| `queueAmqpClientQueue`    | `'queues.default'` | The queue jobs are published to                        |
| `queueAmqpClientExchange` | `''`               | The exchange to publish through; empty for the default |

Warning: the worker declares the queue when it connects, and nothing declares
an exchange or binds one to it. A producer that starts before any worker
publishes to a queue the broker does not hold yet, and the broker discards the
message while `push()` still returns. Call `AmqpClient::declareQueue()` first in
that case. A non-empty `queueAmqpClientExchange` with no binding for the queue's name has
the same effect, so an application that sets it declares and binds the exchange
itself.

#### `QueueSqsClientConfigContract`

| Property                        | Default                                                      | Description                                                                 |
| :------------------------------ | :----------------------------------------------------------- | :-------------------------------------------------------------------------- |
| `queueSqsClientRegion`          | `'us-east-1'`                                                | The AWS region                                                              |
| `queueSqsClientEndpoint`        | `null`                                                       | The endpoint to send to; null for the AWS endpoint of the region            |
| `queueSqsClientAccessKeyId`     | `null`                                                       | The access key id; null for the AWS credential chain                        |
| `queueSqsClientAccessKeySecret` | `null`                                                       | The access key secret; null for the AWS credential chain                    |
| `queueSqsClientQueueUrl`        | `'https://sqs.us-east-1.amazonaws.com/000000000000/default'` | The URL of the queue that jobs are sent to                                  |
| `queueSqsClientWaitTimeSeconds` | `20`                                                         | The long-poll wait in seconds, from 1 to 20; 20 is the longest and cheapest |

Warning: the long-poll wait is also how long a worker can overrun `maxSeconds`,
because the loop reads its bounds only between receives. Lower it when a worker
has to stop promptly, and pay for the extra receives.

Warning: the receive sends no visibility timeout, because the queue's own
setting belongs to the operator. The SQS default is 30 seconds, so a job that
runs longer than that loses its delivery: SQS hands the same message to a second
worker while the first still runs, and both settle it. Set the queue's
visibility timeout above the longest job it carries.

#### `QueueBeanstalkdClientConfigContract`

| Property                              | Default       | Description                                                  |
| :------------------------------------ | :------------ | :----------------------------------------------------------- |
| `queueBeanstalkdClientHost`           | `'127.0.0.1'` | The host to connect to                                       |
| `queueBeanstalkdClientPort`           | `11300`       | The port to connect to                                       |
| `queueBeanstalkdClientTube`           | `'default'`   | The tube that jobs are put on                                |
| `queueBeanstalkdClientTimeToRelease`  | `60`          | The seconds a producer gives a worker before the job returns |
| `queueBeanstalkdClientReserveTimeout` | `1`           | The seconds a reserve waits for a job                        |

Warning: the time-to-release travels with the job from the producer, so a worker
deployment cannot change it. A job that runs longer than it loses its
reservation: beanstalkd gives the same job to a second worker while the first
still runs, and the first worker's settlement then answers
`JobNotFoundException`. Set it above the longest job the tube carries. The entry
does not extend it, so a long job needs the producer to publish a longer one.

Warning: the reserve timeout is also how long a worker can overrun `maxSeconds`,
because the loop reads its bounds only between reserves. Lower it when a worker
has to stop promptly, and pay for the extra reserves.

#### `QueueDatabaseClientConfigContract`

| Property                                  | Default        | Description                           |
| :---------------------------------------- | :------------- | :------------------------------------ |
| `queueDatabaseClientQueue`                | `'default'`    | The queue that jobs are written under |
| `queueDatabaseClientTable`                | `'queue_jobs'` | The table that jobs are written to    |
| `queueDatabaseClientReservationTimeoutMs` | `300000`       | The milliseconds a claim holds a row  |

Warning: a job that runs longer than the reservation timeout loses its claim.
The row counts as free, a second worker claims and runs it while the first is
still going, and both settle it. The first worker's delete is fenced on its own
claim stamp, so it takes nothing the second one owns, but the job has run twice
by then. Set the value above the longest job the table carries.

#### `QueuePubSubClientConfigContract`

| Property                     | Default      | Description                          |
| :--------------------------- | :----------- | :----------------------------------- |
| `queuePubSubClientProjectId` | `'valkyrja'` | The Google Cloud project             |
| `queuePubSubClientTopic`     | `'default'`  | The topic that jobs are published to |

Warning: a job that runs longer than the acknowledgement deadline loses the
delivery. Pub/Sub hands the message to a second worker while the first is still
going, and the job runs twice. The deadline belongs to the subscription rather
than to this config, so set it there, above the longest job the topic carries.
A worker that finishes late also finds its acknowledgement refused, and
`settle()` does not catch that, so the refusal ends the worker.

Warning: the Google client reads the Application Default Credentials when the
service provider builds the client. An application that resolves `PubSubClient`
needs credentials in its environment, such as `GOOGLE_APPLICATION_CREDENTIALS`.

A host application registers `QueueClientComponentProvider` itself. `HttpConfig`
defaults its providers to the HTTP component provider alone, which does not
publish the client services, so an application that only implements the two
contracts cannot resolve `ClientContract`. The list is the complete set rather
than an addition to the default, so a host application names both providers.

```php
use App\Queue\InternalApp;
use Valkyrja\Application\Data\HttpConfig;
use Valkyrja\Application\Provider\HttpApplicationComponentProvider;
use Valkyrja\Queue\Client\Data\Contract\QueueClientConfigContract;
use Valkyrja\Queue\Client\Data\Contract\QueueSyncClientConfigContract;
use Valkyrja\Queue\Client\Manager\SyncClient;
use Valkyrja\Queue\Client\Provider\QueueClientComponentProvider;

final class AppHttpConfig extends HttpConfig implements QueueClientConfigContract, QueueSyncClientConfigContract
{
    public string $defaultQueueClient = SyncClient::class;

    public string $syncEntry = InternalApp::class;

    public function __construct()
    {
        parent::__construct(
            providers: [
                new HttpApplicationComponentProvider(),
                new QueueClientComponentProvider(),
            ],
        );
    }
}
```

An application that uses a second client binds its own contract, which extends
`ClientContract`, to that client in its own service provider.

## Service Registration

`QueueMessageServiceProvider`, `QueueMiddlewareServiceProvider`,
`QueueRoutingServiceProvider`, `QueueServerServiceProvider`, and
`QueueClientServiceProvider` publish the container bindings.

Each middleware stage handler is a shared singleton, so the `Router` and the
`JobHandler` register and invoke the same instance.

`QueueClientServiceProvider` publishes each client config contract and each
client class. It binds `ClientContract` to the client that `defaultQueueClient`
names.

## Optional Dependencies

A broker adapter needs its own package, and the framework does not require one:

| Adapter    | Package                   |
| ---------- | ------------------------- |
| Redis      | `predis/predis`           |
| AMQP       | `php-amqplib/php-amqplib` |
| SQS        | `async-aws/sqs`           |
| beanstalkd | `pda/pheanstalk`          |
| Pub/Sub    | `google/cloud-pubsub`     |

`RedisQueue` moves an envelope between two lists in one step with `BLMOVE` and
`LMOVE`, so a redis server of 6.2 or newer is required to consume. `RedisClient`
publishes with `RPUSH` and `ZADD` alone, so a producer has no such floor.

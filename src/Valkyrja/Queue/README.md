# Queue

## Introduction

The Queue component runs a job outside the request that produced it. A producer
dispatches a `JobContract` through a client. A consumer receives the same job,
runs it through a middleware pipeline, and settles the outcome with the broker.

The pipeline takes a job in and returns a `JobResult` out. There is no response
envelope, because no caller waits for one.

## The Job

`Valkyrja\Queue\Message\Job\Contract\JobContract` is the one message class, and
it travels in both directions. A job is immutable: the framework builds a new
one through a `with*` method, the same way an HTTP request and a CLI input work.

The envelope is a cross-language contract, so every field is always present. A
millisecond field is authoritative, and the matching `_iso` field is derived
from it. `Valkyrja\Queue\Message\Constant\EnvelopeField` names each wire field.

A job carries a payload and a set of attributes.
`Valkyrja\Queue\Message\Payload\Contract\PayloadContract` holds the data the
handler reads, and
`Valkyrja\Queue\Message\Attributes\Contract\AttributesContract` holds the
metadata a processor reads. Both are immutable for the same reason the job is.

`JobFactory` builds a job. The job is a data object, so it holds no static
method: construction belongs to the factory, and a rendering belongs to a
support class.

## The Pipeline

`JobHandler` runs one job through seven middleware stages, and each stage has
its own contract in `Valkyrja\Queue\Middleware\Contract`:

| Stage             | Runs when                             |
| ----------------- | ------------------------------------- |
| `JobReceived`     | the job arrives, before routing       |
| `RouteMatched`    | the router finds a route              |
| `RouteNotMatched` | the router finds no route             |
| `RouteDispatched` | the handler returns                   |
| `ThrowableCaught` | the handler throws                    |
| `SettlingResult`  | before the outcome reaches the broker |
| `ResultSettled`   | after the outcome reaches the broker  |

Each stage has a matching handler contract in
`Valkyrja\Queue\Middleware\Handler\Contract`, which holds the middleware of
that stage and runs it in order. `JobReceived`, `SettlingResult`, and
`ResultSettled` always run. The other four are conditional: routing decides
between `RouteMatched` and `RouteNotMatched`, and the handler decides between
`RouteDispatched` and `ThrowableCaught`.

The handler returns one of four outcomes: `ACK`, `RETRY`, `FAIL`, or
`DEAD_LETTER`. `RetryPolicyThrowableCaughtMiddleware` converts a throwable into
one of them, and it dead-letters at once when the throwable carries
`QueueNonRetryableThrowable`.

Route middleware is appended and never deduplicated. A middleware registered
twice runs twice.

## Throwables

`Valkyrja\Queue\Throwable\Contract\QueueThrowable` marks every throwable the
component raises, and each sub-component narrows it: `QueueMessageThrowable`,
`QueueMiddlewareThrowable`, and `QueueRoutingThrowable`. Each marker has an
abstract `*InvalidArgumentException` and `*RuntimeException` pair that a
concrete exception extends, so a caller can catch a whole sub-component or the
whole component.

## Redelivery

Each processor settles its own outcomes, so the entry of the processor owns
redelivery. A broker with a native retry loop translates the outcome into its
own signal. Redis and a database have no native retry, so their entry calls
`ClientContract::requeue()` instead.

`requeue()` builds a new job with the attempt incremented and publishes it. The
hold comes from the job that was dispatched, read before the increment, so the
ramp is keyed to the attempt that just failed.

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

`AmqpClient` and `PubSubClient` publish without a hold, because neither broker
carries a per-message delay. Giving AMQP one needs a delay queue and a
dead-letter exchange that the broker owner declares rather than the client, and
Cloud Pub/Sub has no equivalent at all. A job pushed with `delay_ms` is
therefore consumable as soon as it lands on either. The Redis, SQS, beanstalkd,
and database clients each apply the hold at enqueue.

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
or `gen_random_uuid()`. Without one every push fails on the missing column.

Warning: a key unrelated to insertion time gives up the first-in-first-out
tiebreak, because the claim orders by `id ASC` among equal priorities. A random
UUID sorts lexically rather than by age, so equal-priority jobs run in no
particular order. The queue still drains.

`DatabaseQueue` claims a row by stamping `reserved_at_ms`, which is what stops
two workers taking the same job. A reservation older than the timeout counts as
free, so a row that a crashed worker abandoned returns to the queue.

A row whose `envelope` no factory can read is parked rather than deleted: the
entry stamps both `reserved_at_ms` and `available_at_ms` with the last
millisecond of the year 9999. No staleness window reaches back to that
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
discarded. Every other processor has the broker return an unacknowledged
delivery on its own.

The in-flight list belongs to one worker slot, named by `redisWorkerName`. That
slot reads its own list and no other, so a worker returns its held envelope to
the ready list on the way out and takes back whatever a previous run of the same
slot left behind on the way in. A graceful stop therefore leaves nothing in
flight, and a worker killed mid-job redelivers when that slot restarts.

Warning: give each worker its own `redisWorkerName` — a pod ordinal, a
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

Every job runs through an entry point, never through `JobHandler` directly. The
entry gives the job an isolated container, so an in-process development run
behaves the same as a standalone production worker.

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

A job routes by name. `#[Route]` marks a controller method, and
`AttributeRouteCollector` collects the routes at runtime. `sindri` generates the
same routes into `AppQueueRoutingData` for a cached boot.

The same route runs a job that arrives from an external broker, an in-process
`sync` push, a `deferred` drain, or an `inmemory` test.

## Configuration

`Valkyrja\Application\Data\Contract\QueueConfigContract` holds the middleware
for each of the seven pipeline stages. `QueueConfig` is the framework default,
and an application config that implements `QueueConfigContract` replaces it.

A client stamps the `applicationName` of the application that pushes the job
into the producer field of the job.

### Client Configuration

The service provider binds `ClientContract` to the client that
`QueueClientConfigContract::$defaultQueueClient` names, and the default is
`RedisClient`. Each client reads its own config contract, and an application
config implements the contract of each client that the application uses.

`SyncClient` and `DeferredClient` have no default, because the entry names the
queue config of the application. The service provider throws
`QueueClientConfigNotFoundException` when it builds one of the two clients for
an application config that does not implement its contract.

#### `QueueClientConfigContract`

| Property             | Default              | Description                               |
| :------------------- | :------------------- | :---------------------------------------- |
| `defaultQueueClient` | `RedisClient::class` | The client that `ClientContract` binds to |

#### `QueueSyncClientConfigContract`

| Property    | Default | Description                                  |
| :---------- | :------ | :------------------------------------------- |
| `syncEntry` | none    | The `InternalQueue` entry that runs each job |

#### `QueueDeferredClientConfigContract`

| Property        | Default | Description                                  |
| :-------------- | :------ | :------------------------------------------- |
| `deferredEntry` | none    | The `InternalQueue` entry that runs each job |

#### `QueueRedisClientConfigContract`

| Property          | Default            | Description                                                 |
| :---------------- | :----------------- | :---------------------------------------------------------- |
| `redisHost`       | `'127.0.0.1'`      | Redis host                                                  |
| `redisPort`       | `6379`             | Redis port                                                  |
| `redisQueue`      | `'queues:default'` | The list key jobs are pushed onto                           |
| `redisWorkerName` | `'default'`        | Names this worker's slot, which owns its own in-flight list |

#### `QueueAmqpClientConfigContract`

| Property       | Default            | Description                                            |
| :------------- | :----------------- | :----------------------------------------------------- |
| `amqpHost`     | `'127.0.0.1'`      | AMQP host                                              |
| `amqpPort`     | `5672`             | AMQP port                                              |
| `amqpUser`     | `'guest'`          | The user to connect as                                 |
| `amqpPassword` | `'guest'`          | The password of the user                               |
| `amqpVhost`    | `'/'`              | The virtual host to connect to                         |
| `amqpQueue`    | `'queues.default'` | The queue jobs are published to                        |
| `amqpExchange` | `''`               | The exchange to publish through; empty for the default |

Warning: the worker declares the queue when it connects, and nothing declares
an exchange or binds one to it. A producer that starts before any worker
publishes to a queue the broker does not hold yet, and the broker discards the
message while `push()` still returns. Call `AmqpClient::declareQueue()` first in
that case. A non-empty `amqpExchange` with no binding for the queue's name has
the same effect, so an application that sets it declares and binds the exchange
itself.

#### `QueueSqsClientConfigContract`

| Property             | Default                                                      | Description                                                                 |
| :------------------- | :----------------------------------------------------------- | :-------------------------------------------------------------------------- |
| `sqsRegion`          | `'us-east-1'`                                                | The AWS region                                                              |
| `sqsEndpoint`        | `null`                                                       | The endpoint to send to; null for the AWS endpoint of the region            |
| `sqsAccessKeyId`     | `null`                                                       | The access key id; null for the AWS credential chain                        |
| `sqsAccessKeySecret` | `null`                                                       | The access key secret; null for the AWS credential chain                    |
| `sqsQueueUrl`        | `'https://sqs.us-east-1.amazonaws.com/000000000000/default'` | The URL of the queue that jobs are sent to                                  |
| `sqsWaitTimeSeconds` | `20`                                                         | The long-poll wait in seconds, from 1 to 20; 20 is the longest and cheapest |

Warning: the long-poll wait is also how long a worker can overrun `maxSeconds`,
because the loop reads its bounds only between receives. Lower it when a worker
has to stop promptly, and pay for the extra receives.

#### `QueueBeanstalkdClientConfigContract`

| Property                  | Default       | Description                                                    |
| :------------------------ | :------------ | :------------------------------------------------------------- |
| `beanstalkdHost`          | `'127.0.0.1'` | The host to connect to                                         |
| `beanstalkdPort`          | `11300`       | The port to connect to                                         |
| `beanstalkdTube`          | `'default'`   | The tube that jobs are put on                                  |
| `beanstalkdTimeToRelease` | `60`          | The seconds a worker holds a job before beanstalkd releases it |

#### `QueueDatabaseClientConfigContract`

| Property        | Default        | Description                           |
| :-------------- | :------------- | :------------------------------------ |
| `databaseQueue` | `'default'`    | The queue that jobs are written under |
| `databaseTable` | `'queue_jobs'` | The table that jobs are written to    |

#### `QueuePubSubClientConfigContract`

| Property          | Default      | Description                          |
| :---------------- | :----------- | :----------------------------------- |
| `pubSubProjectId` | `'valkyrja'` | The Google Cloud project             |
| `pubSubTopic`     | `'default'`  | The topic that jobs are published to |

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

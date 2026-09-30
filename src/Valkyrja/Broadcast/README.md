# Broadcast

## Introduction

The Broadcast component sends real-time events to channels
via [Pusher](https://pusher.com/). An encrypted variant, a log implementation
for development, and a null implementation for testing are included alongside
the default Pusher backend.

## The Broadcaster Contract

`Valkyrja\Broadcast\Contract\BroadcasterContract` defines a single method:

```php
public function send(MessageContract $message): void;
```

## Messages

A broadcast message carries the channel name, event name, event data, and an
optional text message. Build one with the fluent immutable API on
`Valkyrja\Broadcast\Data\Message`:

```php
use Valkyrja\Broadcast\Data\Message;

$message = (new Message())
    ->withChannel('orders')
    ->withEvent('order.placed')
    ->withData(['order_id' => 42, 'total' => '99.00'])
    ->withMessage('A new order was placed.');
```

`MessageContract` methods:

```php
public function getChannel(): string;
public function withChannel(string $channel): static;
public function getEvent(): string;
public function withEvent(string $event): static;
public function getData(): array;
public function withData(array $data): static;
public function getMessage(): string;
public function withMessage(string $message): static;
```

## Implementations

| Class                    | Description                                         |
| :----------------------- | :-------------------------------------------------- |
| `PusherBroadcaster`      | Sends events to Pusher                              |
| `CryptPusherBroadcaster` | Encrypts the payload before sending to Pusher       |
| `LogBroadcaster`         | Logs broadcast events; useful for local development |
| `NullBroadcaster`        | No-op; discards all messages silently               |

The active implementation is resolved from the container as
`BroadcasterContract`. Configure the default through `BroadcastConfigContract`.

## Configuration

The component reads three config contracts. Your application config class
implements only the contracts for the adapters that it uses. Each adapter
contract prefixes its properties with the adapter name, so one class can
implement several of them at once.

### `BroadcastConfigContract`

| Property             | Default                    | Description                                   |
| :------------------- | :------------------------- | :-------------------------------------------- |
| `defaultBroadcaster` | `PusherBroadcaster::class` | Implementation bound to `BroadcasterContract` |

### `BroadcastPusherConfigContract`

| Property        | Default           | Description                               |
| :-------------- | :---------------- | :---------------------------------------- |
| `pusherKey`     | `'pusher-key'`    | Pusher application key                    |
| `pusherSecret`  | `'pusher-secret'` | Pusher application secret                 |
| `pusherId`      | `'pusher-id'`     | Pusher application ID                     |
| `pusherCluster` | `'us1'`           | Pusher cluster region                     |
| `pusherUseTls`  | `true`            | Whether to use TLS for Pusher connections |

### `BroadcastLogConfigContract`

| Property             | Default                 | Description                     |
| :------------------- | :---------------------- | :------------------------------ |
| `broadcastLogLogger` | `LoggerContract::class` | Logger used by `LogBroadcaster` |

The property name starts with the component name, because the log adapter of
each component has a logger setting. One config class can then set a different
logger for each component.

## Service Registration

The Broadcast service provider registers the following singletons:

| Contract / Class                | Description                                       |
| :------------------------------ | :------------------------------------------------ |
| `BroadcastConfigContract`       | Component config                                  |
| `BroadcastPusherConfigContract` | Pusher adapter config                             |
| `BroadcastLogConfigContract`    | Log adapter config                                |
| `BroadcasterContract`           | Active broadcaster (default: `PusherBroadcaster`) |
| `PusherBroadcaster`             | Pusher implementation                             |
| `CryptPusherBroadcaster`        | Encrypted Pusher implementation                   |
| `LogBroadcaster`                | Log implementation                                |
| `NullBroadcaster`               | No-op implementation                              |
| `Pusher`                        | Configured Pusher SDK instance                    |

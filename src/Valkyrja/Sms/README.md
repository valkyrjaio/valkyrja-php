# SMS

## Introduction

The SMS component sends text messages via [Vonage](https://www.vonage.com/) (
formerly Nexmo). Log and null implementations are included for development and
testing.

## The Messenger Contract

`Valkyrja\Sms\Contract\MessengerContract` defines a single method:

```php
public function send(MessageContract $message): void;
```

## Messages

Build a message using `Valkyrja\Sms\Data\Message`. All `with*` methods return a
new instance:

```php
use Valkyrja\Sms\Data\Message;

$message = (new Message())
    ->withTo('+15551234567')
    ->withFrom('+15559876543')
    ->withText('Your verification code is 123456.')
    ->withIsUnicode();
```

`MessageContract` methods:

```php
public function getTo(): string;
public function withTo(string $to): static;
public function getFrom(): string;
public function withFrom(string $from): static;
public function getText(): string;
public function withText(string $text): static;
public function isUnicode(): bool;
public function withIsUnicode(bool $isUnicode = true): static;
```

The `VonageMessenger` detects unicode content automatically and sets the message
type accordingly.

## Implementations

| Class             | Description                             |
| :---------------- | :-------------------------------------- |
| `VonageMessenger` | Sends via the Vonage API                |
| `LogMessenger`    | Logs message details instead of sending |
| `NullMessenger`   | No-op; discards all messages silently   |

The active implementation is resolved from the container as `MessengerContract`.
Configure the default through `SmsConfigContract`.

## Configuration

The component reads three config contracts. Your application config class
implements only the contracts for the adapters that it uses. Each contract
prefixes its properties with the adapter name, so one class can implement
several of them at once.

### `SmsConfigContract`

| Property           | Default                  | Description                                 |
| :----------------- | :----------------------- | :------------------------------------------ |
| `defaultMessenger` | `VonageMessenger::class` | Implementation bound to `MessengerContract` |

### `SmsVonageConfigContract`

| Property       | Default           | Description       |
| :------------- | :---------------- | :---------------- |
| `vonageKey`    | `'vonage-key'`    | Vonage API key    |
| `vonageSecret` | `'vonage-secret'` | Vonage API secret |

### `SmsLogConfigContract`

| Property       | Default                 | Description                   |
| :------------- | :---------------------- | :---------------------------- |
| `smsLogLogger` | `LoggerContract::class` | Logger used by `LogMessenger` |

The property name starts with the component name, because the log adapter of
each component has a logger setting. One config class can then set a different
logger for each component.

## Service Registration

The SMS service provider registers the following singletons:

| Contract / Class          | Description                                   |
| :------------------------ | :-------------------------------------------- |
| `SmsConfigContract`       | Component config                              |
| `SmsVonageConfigContract` | Vonage adapter config                         |
| `SmsLogConfigContract`    | Log adapter config                            |
| `MessengerContract`       | Active messenger (default: `VonageMessenger`) |
| `VonageMessenger`         | Vonage implementation                         |
| `LogMessenger`            | Log implementation                            |
| `NullMessenger`           | No-op implementation                          |
| `Vonage\Client`           | Configured Vonage HTTP client                 |
| `CredentialsInterface`    | Vonage Basic auth credentials                 |

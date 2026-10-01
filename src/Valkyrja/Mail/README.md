# Mail

## Introduction

The Mail component sends email messages via Mailgun or PHPMailer SMTP. Log and
null implementations are included for development and testing. Messages are
built with a fluent immutable API supporting recipients, CC, BCC, reply-to,
attachments, HTML, and plain text alternatives.

## The Mailer Contract

`Valkyrja\Mail\Contract\MailerContract` defines a single method:

```php
public function send(MessageContract $message): void;
```

## Messages

Build a message using `Valkyrja\Mail\Data\Message`. All `with*` methods return a
new instance:

```php
use Valkyrja\Mail\Data\Message;
use Valkyrja\Mail\Data\Recipient;
use Valkyrja\Mail\Data\Attachment;

$message = (new Message())
    ->withFrom(new Recipient('sender@example.com', 'Sender Name'))
    ->withAddedRecipient(new Recipient('user@example.com', 'User Name'))
    ->withAddedCopyRecipient(new Recipient('manager@example.com'))
    ->withSubject('Welcome!')
    ->withBody('<h1>Hello</h1>')
    ->withIsHtml()
    ->withPlainBody('Hello')
    ->withAddedAttachment(new Attachment('/path/to/file.pdf', 'Document'));
```

### MessageContract

```php
public function getFrom(): RecipientContract;
public function withFrom(RecipientContract $from): static;

public function getRecipients(): array;                                  // To
public function withAddedRecipient(RecipientContract $recipient): static;

public function getReplyToRecipients(): array;
public function withAddedReplyToRecipient(RecipientContract $recipient): static;

public function getCopyRecipients(): array;                              // CC
public function withAddedCopyRecipient(RecipientContract $recipient): static;

public function getBlindCopyRecipients(): array;                         // BCC
public function withAddedBlindCopyRecipient(RecipientContract $recipient): static;

public function getAttachments(): array;
public function withAddedAttachment(AttachmentContract $attachment): static;

public function getSubject(): string;
public function withSubject(string $subject): static;

public function getBody(): string;
public function withBody(string $body): static;

public function isHtml(): bool;
public function withIsHtml(bool $isHtml = true): static;

public function hasPlainBody(): bool;
public function getPlainBody(): string;
public function withPlainBody(string $plainBody): static;
```

### RecipientContract

```php
public function getEmail(): string;
public function withEmail(string $email): static;
public function hasName(): bool;
public function getName(): string;
public function withName(string $name): static;
```

### AttachmentContract

```php
public function getPath(): string;
public function withPath(string $path): static;
public function hasName(): bool;
public function getName(): string;
public function withName(string $name): static;
```

## Implementations

| Class           | Description                                |
| :-------------- | :----------------------------------------- |
| `MailgunMailer` | Sends via Mailgun's batch message API      |
| `PhpMailer`     | Sends via SMTP using the PHPMailer library |
| `LogMailer`     | Logs message details instead of sending    |
| `NullMailer`    | No-op; discards all messages silently      |

The active implementation is resolved from the container as `MailerContract`.
Configure the default through `MailConfigContract`.

## Configuration

The component reads four config contracts. Your application config class
implements only the contracts for the adapters that it uses. Each adapter
contract prefixes its properties with the adapter name, so one class can
implement several of them at once.

### `MailConfigContract`

| Property        | Default                | Description                              |
| :-------------- | :--------------------- | :--------------------------------------- |
| `defaultMailer` | `MailgunMailer::class` | Implementation bound to `MailerContract` |

### `MailMailgunConfigContract`

| Property        | Default     | Description     |
| :-------------- | :---------- | :-------------- |
| `mailgunDomain` | `'domain'`  | Mailgun domain  |
| `mailgunApiKey` | `'api-key'` | Mailgun API key |

### `MailPhpMailerConfigContract`

| Property              | Default      | Description          |
| :-------------------- | :----------- | :------------------- |
| `phpMailerHost`       | `'host'`     | SMTP server hostname |
| `phpMailerPort`       | `25`         | SMTP server port     |
| `phpMailerUsername`   | `'username'` | SMTP username        |
| `phpMailerPassword`   | `'password'` | SMTP password        |
| `phpMailerEncryption` | `'ssl'`      | Encryption type      |

### `MailLogConfigContract`

| Property        | Default                 | Description                |
| :-------------- | :---------------------- | :------------------------- |
| `mailLogLogger` | `LoggerContract::class` | Logger used by `LogMailer` |

The property name starts with the component name, because the log adapter of
each component has a logger setting. One config class can then set a different
logger for each component.

## Service Registration

The Mail service provider registers the following singletons:

| Contract / Class              | Description                                     |
| :---------------------------- | :---------------------------------------------- |
| `MailConfigContract`          | Component config                                |
| `MailMailgunConfigContract`   | Mailgun adapter config                          |
| `MailPhpMailerConfigContract` | PHPMailer adapter config                        |
| `MailLogConfigContract`       | Log adapter config                              |
| `MailerContract`              | Active mailer (default: `MailgunMailer`)        |
| `MailgunMailer`               | Mailgun implementation                          |
| `PhpMailer`                   | PHPMailer SMTP implementation                   |
| `LogMailer`                   | Log implementation                              |
| `NullMailer`                  | No-op implementation                            |
| `Mailgun`                     | Configured Mailgun client instance              |
| `HttpClientConfigurator`      | Configured HTTP client configurator for Mailgun |
| `PHPMailerClient`             | Configured PHPMailer SMTP instance              |

# Session

## Introduction

The Session component manages user session data with support for PHP's native
session mechanism, cache-backed storage, cookie sessions, JWT sessions, token
sessions, and encrypted variants of each. CSRF token generation and validation
are built in.

## The Session Contract

`Valkyrja\Session\Contract\SessionContract` defines the full session API:

```php
// Lifecycle
public function start(): void;
public function isActive(): bool;
public function clear(): void;
public function destroy(): void;

// Identity
public function getId(): string;
public function setId(string $id): void;
public function getName(): string;
public function setName(string $name): void;

// Data access
public function has(string $id): bool;
public function get(string $id, mixed $default = null): mixed;
public function set(string $id, mixed $value): void;
public function remove(string $id): bool;
public function all(): array;

// CSRF
public function generateCsrfToken(string $id): string;
public function validateCsrfToken(string $id, string $token): void;  // throws on invalid
public function isCsrfTokenValid(string $id, string $token): bool;
```

### CSRF Tokens

`generateCsrfToken()` creates a 128-character hexadecimal token from 64
cryptographically random bytes and stores it in the session under the given ID.
`validateCsrfToken()` compares the stored token against the provided one using
`hash_equals()` for timing-safe comparison and throws `InvalidCsrfToken` on
mismatch. `isCsrfTokenValid()` returns a boolean instead of throwing.

## Implementations

Valkyrja ships with a wide range of session backends to cover both HTTP and CLI
contexts:

### HTTP Sessions

| Class                         | Description                                            |
| :---------------------------- | :----------------------------------------------------- |
| `PhpSession`                  | PHP native `$_SESSION` with configurable cookie params |
| `CookieSession`               | Session data stored in an HTTP cookie                  |
| `EncryptedCookieSession`      | Cookie session with encrypted payload                  |
| `HeaderJwtSession`            | Session stored as a JWT in the `Authorization` header  |
| `EncryptedHeaderJwtSession`   | JWT header session with encrypted payload              |
| `HeaderTokenSession`          | Session stored as a plain token in an HTTP header      |
| `EncryptedHeaderTokenSession` | Token header session with encrypted payload            |

### CLI Sessions

| Class                         | Description                                     |
| :---------------------------- | :---------------------------------------------- |
| `OptionJwtSession`            | Session stored as a JWT in a CLI option         |
| `EncryptedOptionJwtSession`   | JWT CLI session with encrypted payload          |
| `OptionTokenSession`          | Session stored as a plain token in a CLI option |
| `EncryptedOptionTokenSession` | Token CLI session with encrypted payload        |

### Other

| Class          | Description                                |
| :------------- | :----------------------------------------- |
| `CacheSession` | Session data backed by the Cache component |
| `LogSession`   | Logs all session operations                |
| `NullSession`  | No-op session for testing                  |

The active implementation is resolved from the container as `SessionContract`.
Configure the default through `SessionConfigContract`.

## Configuration

The component reads five config contracts. Your application config class
implements only the contracts for the adapters that it uses. Each contract
prefixes its properties with the adapter name, so one class can implement
several of them at once.

### `SessionConfigContract`

Every session manager reads these settings.

| Property         | Default             | Description                               |
| :--------------- | :------------------ | :---------------------------------------- |
| `defaultSession` | `PhpSession::class` | Implementation bound to `SessionContract` |
| `sessionId`      | `null`              | The session id                            |
| `sessionName`    | `null`              | The session name                          |

### `SessionPhpConfigContract`

`PhpSession` reads these settings and gives them to
`session_set_cookie_params()`.

| Property            | Default          | Description                              |
| :------------------ | :--------------- | :--------------------------------------- |
| `phpCookiePath`     | `'/'`            | Cookie path                              |
| `phpCookieDomain`   | `null`           | Cookie domain                            |
| `phpCookieLifetime` | `0`              | Cookie lifetime in seconds (0 = session) |
| `phpCookieSecure`   | `false`          | HTTPS only                               |
| `phpCookieHttpOnly` | `false`          | HTTP only (not accessible to JS)         |
| `phpCookieSameSite` | `SameSite::NONE` | SameSite cookie policy                   |

### `SessionJwtConfigContract`

| Property        | Default | Description                       |
| :-------------- | :------ | :-------------------------------- |
| `jwtOptionName` | `null`  | CLI option name for JWT sessions  |
| `jwtHeaderName` | `null`  | HTTP header name for JWT sessions |

### `SessionTokenConfigContract`

| Property          | Default | Description                         |
| :---------------- | :------ | :---------------------------------- |
| `tokenOptionName` | `null`  | CLI option name for token sessions  |
| `tokenHeaderName` | `null`  | HTTP header name for token sessions |

### `SessionLogConfigContract`

| Property           | Default                 | Description                 |
| :----------------- | :---------------------- | :-------------------------- |
| `sessionLogLogger` | `LoggerContract::class` | Logger used by `LogSession` |

The property name starts with the component name, because the log adapter of
each component has a logger setting. One config class can then set a different
logger for each component.

## Service Registration

The Session service provider registers `SessionContract`, the five config
contracts, and all session implementation singletons.

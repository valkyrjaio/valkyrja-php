# Auth

## Introduction

The Auth component handles user authentication, session management, password
hashing, and user retrieval. It is built around three primary contracts: the
authenticator (coordinates the login flow), the store (retrieves and persists
users), and the password hasher. User entities, retrieval criteria, and attempt
data objects are all defined by contracts, making every piece of the system
replaceable.

The default implementation stores the authenticated user in the session and
retrieves users from the ORM.

## Core Contracts

### AuthenticatorContract

`Valkyrja\Auth\Authenticator\Contract\AuthenticatorContract` is the main entry
point for authentication:

```php
public function isAuthenticated(): bool;
public function getAuthenticated(): UserContract;
public function getImpersonated(): UserContract;
public function getAuthenticatedUsers(): AuthenticatedUsersContract;
public function setAuthenticatedUsers(AuthenticatedUsersContract $authenticatedUsers): static;
public function authenticate(AuthenticationAttemptContract $attempt): UserContract;
public function unauthenticate(string|int $id): static;
```

`authenticate()` takes an `AuthenticationAttemptContract`, retrieves the
matching user from the store, verifies the password, and returns the
authenticated user. `unauthenticate()` removes a user from the current
authenticated set by ID.

### StoreContract

`Valkyrja\Auth\Store\Contract\StoreContract` abstracts user persistence:

```php
public function hasRetrievable(RetrievalContract $retrieval, string $user): bool;
public function retrieve(RetrievalContract $retrieval, string $user): UserContract;
public function create(UserContract $user): void;
public function update(UserContract $user): void;
```

### PasswordHasherContract

`Valkyrja\Auth\Hasher\Contract\PasswordHasherContract` handles password hashing:

```php
public function hashPassword(string $password): string;
public function confirmPassword(string $password, string $hashedPassword): bool;
```

The default `PhpPasswordHasher` uses PHP's native `password_hash()` and
`password_verify()`.

## Authentication Attempts

An `AuthenticationAttemptContract` bundles a retrieval strategy with a password:

```php
public function getRetrieval(): RetrievalContract;
public function getPassword(): string;
```

The concrete `AuthenticationAttempt` class implements this contract. Additional
attempt types cover other flows:

| Class                   | Description                                           |
| :---------------------- | :---------------------------------------------------- |
| `AuthenticationAttempt` | Standard login (retrieval + password)                 |
| `ForgotPasswordAttempt` | Initiates a password reset (retrieval only)           |
| `ResetPasswordAttempt`  | Completes a password reset (retrieval + new password) |
| `LockAttempt`           | Locks a user account                                  |
| `UnlockAttempt`         | Unlocks a user account                                |

## User Retrieval

`RetrievalContract` defines how a user is looked up:

```php
public function getRetrievalFields(string $user): array;
```

Built-in retrieval strategies:

| Class                      | Description                       |
| :------------------------- | :-------------------------------- |
| `RetrievalByUsername`      | Looks up a user by username field |
| `RetrievalById`            | Looks up a user by primary key    |
| `RetrievalByIdAndUsername` | Looks up by both ID and username  |
| `RetrievalByResetToken`    | Looks up by password reset token  |

## Authenticated Users

`AuthenticatedUsersContract` tracks which users are authenticated in the current
session and supports impersonation:

```php
public function hasCurrent(): bool;
public function getCurrent(): string|int;
public function setCurrent(string|int $id): static;

public function isImpersonating(): bool;
public function getImpersonated(): string|int;
public function setImpersonated(string|int $id): static;

public function isUserAuthenticated(string|int $id): bool;
public function add(string|int $id): static;
public function remove(string|int $id): static;
public function all(): array;
```

The `SessionAuthenticator` serializes the `AuthenticatedUsers` collection into
the session under the key `auth.users` and deserializes it on subsequent
requests.

## User Entities

All user entities extend `UserContract`, which itself extends the ORM's
`EntityContract`. The base contract requires:

```php
public static function getUsernameField(): string;
public static function getPasswordField(): string;
public static function getResetTokenField(): string;
public function getUsernameValue(): string;
public function getPasswordValue(): string;
```

Valkyrja ships several base user entity classes that can be extended:

| Class            | Adds                                                                       |
| :--------------- | :------------------------------------------------------------------------- |
| `User`           | Base user with username, password, and reset token fields                  |
| `MailableUser`   | Adds `getEmailField()` via `MailableUserContract`                          |
| `VerifiableUser` | Adds `getIsVerifiedField()` via `VerifiableUserContract`                   |
| `LockableUser`   | Adds lockout fields and `getMaxLoginAttempts()` via `LockableUserContract` |

### Optional User Contracts

Additional user capability contracts can be composed as needed:

| Contract                          | Adds                                                                     |
| :-------------------------------- | :----------------------------------------------------------------------- |
| `MailableUserContract`            | `getEmailField()`                                                        |
| `VerifiableUserContract`          | `getIsVerifiedField()` (extends `MailableUserContract`)                  |
| `LockableUserContract`            | `getMaxLoginAttempts()`, `getLoginAttemptsField()`, `getIsLockedField()` |
| `PermissibleUserContract`         | `isAllowed(string $permission)`, `isDenied(string $permission)`          |
| `TwoFactorUserContract`           | `getTwoFactorCodeField()`, `getDateTwoFactorCodeGeneratedField()`        |
| `PinUserContract`                 | PIN-based authentication fields                                          |
| `AntiPhishCodeUserContract`       | Anti-phishing code fields                                                |
| `DeviceAuthenticatedUserContract` | Device authentication fields                                             |
| `LastOnlineUserContract`          | Last-online timestamp field                                              |
| `UserRecoveryCodeContract`        | Recovery code fields                                                     |
| `UserDeviceContract`              | Per-device authentication fields                                         |

## Implementations

### Authenticators

| Class                  | Description                                      |
| :--------------------- | :----------------------------------------------- |
| `SessionAuthenticator` | Stores authenticated user IDs in the PHP session |

### Stores

| Class           | Description                                        |
| :-------------- | :------------------------------------------------- |
| `OrmStore`      | Retrieves and persists users via the ORM component |
| `InMemoryStore` | Stores users in memory (for testing)               |
| `NullStore`     | No-op store; discards all operations               |

## Session Storage

`SessionAuthenticator` serializes the `AuthenticatedUsers` object and stores it
in the session under the `sessionItemId` key. The key is `auth.users` by
default. On construction, the authenticator reads and deserializes this value.
It accepts only the classes in `sessionAllowedClasses`, which prevents unsafe
deserialization.

The `auth.passwordConfirmedTimestamp` session key is also reserved for
password-confirmation flows.

## Configuration

The component reads two config contracts. Your application config class
implements only the contracts for the adapters that it uses. Each adapter
contract prefixes its properties with the adapter name, so one class can
implement several of them at once.

### `AuthConfigContract`

| Property               | Default                       | Description                                     |
| :--------------------- | :---------------------------- | :---------------------------------------------- |
| `defaultAuthenticator` | `SessionAuthenticator::class` | Implementation bound to `AuthenticatorContract` |
| `defaultStore`         | `OrmStore::class`             | Implementation bound to `StoreContract`         |
| `defaultUserEntity`    | `User::class`                 | Default user entity class                       |

### `AuthSessionConfigContract`

| Property                | Default                       | Description                                        |
| :---------------------- | :---------------------------- | :------------------------------------------------- |
| `sessionItemId`         | `'auth.users'`                | Session key for the authenticated users collection |
| `sessionAllowedClasses` | `[AuthenticatedUsers::class]` | Allowed classes during session deserialization     |

## Service Registration

The Auth service provider registers the following singletons:

| Contract / Class            | Description                                            |
| :-------------------------- | :----------------------------------------------------- |
| `AuthConfigContract`        | Component config                                       |
| `AuthSessionConfigContract` | Session authenticator config                           |
| `AuthenticatorContract`     | Active authenticator (default: `SessionAuthenticator`) |
| `SessionAuthenticator`      | Session-backed authenticator                           |
| `StoreContract`             | Active store (default: `OrmStore`)                     |
| `OrmStore`                  | ORM-backed user store                                  |
| `InMemoryStore`             | In-memory user store                                   |
| `NullStore`                 | No-op user store                                       |
| `PasswordHasherContract`    | Password hasher (default: `PhpPasswordHasher`)         |

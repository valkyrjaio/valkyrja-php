# Crypt

## Introduction

The Crypt component provides symmetric authenticated encryption and decryption
using PHP's libsodium extension. It supports encrypting plain strings, arrays,
and objects, and includes a null implementation for testing.

The default implementation uses `sodium_crypto_secretbox`, which provides
authenticated encryption with a MAC. `CryptSodiumConfigContract` supplies the
key as a hex string, and `SodiumCrypt` converts the key to raw bytes at run
time. `sodium_memzero` zeroes each key from memory after use.

## The CryptContract

`Valkyrja\Crypt\Manager\Contract\CryptContract` defines the full encryption API:

```php
// Validate
public function isValidEncryptedMessage(string $encrypted): bool;

// Strings
public function encrypt(string $message, string|null $key = null): string;
public function decrypt(string $encrypted, string|null $key = null): string;

// Arrays
public function encryptArray(array $array, string|null $key = null): string;
public function decryptArray(string $encrypted, string|null $key = null): array;

// Objects
public function encryptObject(object $object, string|null $key = null): string;
public function decryptObject(string $encrypted, string|null $key = null): object;
```

All `$key` parameters accept `#[SensitiveParameter]` values. When a `$key` is
`null`, `SodiumCrypt` uses the key from `CryptSodiumConfigContract`. All methods
throw `CryptException` on failure.

`isValidEncryptedMessage()` returns `false` rather than throwing when the
message is invalid.

## Encryption Details

`SodiumCrypt` uses the following process:

1. A random nonce is generated using
   `random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)`.
2. The message is encrypted with
   `sodium_crypto_secretbox($message, $nonce, $key)`.
3. The nonce is prepended to the ciphertext and the result is hex-encoded with
   `bin2hex`.
4. `sodium_memzero()` is called on the plaintext message and key bytes
   immediately after use.

Decryption reverses the process: `hex2bin` decodes the ciphertext, the nonce is
extracted from the leading bytes, and `sodium_crypto_secretbox_open`
authenticates and decrypts.

Array and object variants serialize the value to JSON before encrypting and
deserialize after decrypting.

## Implementations

| Class         | Description                                      |
| :------------ | :----------------------------------------------- |
| `SodiumCrypt` | Authenticated encryption via libsodium secretbox |
| `NullCrypt`   | No-op; returns input unchanged (for testing)     |

The active implementation is resolved from the container as `CryptContract`.
Configure the default through `CryptConfigContract`.

## Configuration

The component reads two config contracts. Your application config class
implements only the contracts for the adapters that it uses. Each adapter
contract prefixes its properties with the adapter name, so one class can
implement several of them at once.

### `CryptConfigContract`

| Property       | Default              | Description                             |
| :------------- | :------------------- | :-------------------------------------- |
| `defaultCrypt` | `SodiumCrypt::class` | Implementation bound to `CryptContract` |

### `CryptSodiumConfigContract`

| Property    | Default        | Description                 |
| :---------- | :------------- | :-------------------------- |
| `sodiumKey` | `Config::$key` | Key that `SodiumCrypt` uses |

The key must be a hex-encoded string of the raw key bytes that libsodium
expects. A key has no safe default value, so `CryptSodiumConfig` has none. When
the application config does not implement the contract, the service provider
gives `CryptSodiumConfig` the application key from `Config::$key`.

## Service Registration

The Crypt service provider registers the following singletons:

| Contract / Class            | Description                                    |
| :-------------------------- | :--------------------------------------------- |
| `CryptConfigContract`       | Component config                               |
| `CryptSodiumConfigContract` | Sodium adapter config                          |
| `CryptContract`             | Active implementation (default: `SodiumCrypt`) |
| `SodiumCrypt`               | libsodium secretbox implementation             |
| `NullCrypt`                 | No-op implementation                           |

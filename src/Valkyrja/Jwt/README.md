# JWT

## Introduction

The JWT component encodes and decodes JSON Web Tokens. It wraps
the [firebase/php-jwt](https://github.com/firebase/php-jwt) library and exposes
a minimal two-method contract, keeping algorithm and key configuration in your
application config class. A null implementation is included for testing.

## The JWT Contract

`Valkyrja\Jwt\Contract\JwtContract` defines the full API:

```php
public function encode(array $payload): string;
public function decode(string $jwt): array;
```

`encode()` takes a plain PHP array and returns a signed JWT string. `decode()`
takes a JWT string, verifies the signature, and returns the decoded payload
array.

## Supported Algorithms

`Valkyrja\Jwt\Enum\Algorithm` covers all algorithms supported by
firebase/php-jwt:

| Family | Cases                               |
| :----- | :---------------------------------- |
| HMAC   | `HS256`, `HS384`, `HS512`           |
| RSA    | `RS256`, `RS384`, `RS512`           |
| PSA    | `PS256`, `PS384`, `PS512`           |
| ECDSA  | `ES256`, `ES256K`, `ES384`, `ES512` |
| EdDSA  | `EdDSA`                             |

## Implementations

| Class         | Description                                             |
| :------------ | :------------------------------------------------------ |
| `FirebaseJwt` | Delegates to firebase/php-jwt                           |
| `NullJwt`     | No-op; `encode()` returns `''`, `decode()` returns `[]` |

The active implementation is resolved from the container as `JwtContract`.
Configure the default through `JwtConfigContract`.

## Configuration

The component reads five config contracts. Your application config class
implements only the contracts for the algorithms that it signs with. Each
algorithm contract prefixes its properties with the algorithm name, so one class
can implement several of them at once.

`publishFirebaseJwt` resolves only the key config that the algorithm needs. An
application that signs with HMAC never constructs the RSA keys.

### `JwtConfigContract`

| Property     | Default              | Description                           |
| :----------- | :------------------- | :------------------------------------ |
| `defaultJwt` | `FirebaseJwt::class` | Implementation bound to `JwtContract` |

### `JwtFirebaseConfigContract`

| Property            | Default            | Description                                          |
| :------------------ | :----------------- | :--------------------------------------------------- |
| `firebaseAlgorithm` | `Algorithm::HS256` | Algorithm that `FirebaseJwt` signs and verifies with |

### `JwtHsConfigContract`

| Property | Default | Description                    |
| :------- | :------ | :----------------------------- |
| `hsKey`  | `'key'` | Secret key for HMAC algorithms |

### `JwtRsConfigContract`

| Property       | Default         | Description                    |
| :------------- | :-------------- | :----------------------------- |
| `rsPrivateKey` | `'private-key'` | Private key for RSA algorithms |
| `rsPublicKey`  | `'public-key'`  | Public key for RSA algorithms  |

### `JwtEdDsaConfigContract`

| Property          | Default         | Description           |
| :---------------- | :-------------- | :-------------------- |
| `edDsaPrivateKey` | `'private-key'` | Private key for EdDSA |
| `edDsaPublicKey`  | `'public-key'`  | Public key for EdDSA  |

An algorithm outside these three families falls back to the application-level
`Config::$key` for both the encode key and the decode key.

## Service Registration

The JWT service provider registers the following singletons:

| Contract / Class            | Description                                 |
| :-------------------------- | :------------------------------------------ |
| `JwtConfigContract`         | Component config                            |
| `JwtHsConfigContract`       | HMAC key config                             |
| `JwtRsConfigContract`       | RSA key config                              |
| `JwtEdDsaConfigContract`    | EdDSA key config                            |
| `JwtFirebaseConfigContract` | Firebase adapter config                     |
| `JwtContract`               | Active JWT manager (default: `FirebaseJwt`) |
| `FirebaseJwt`               | Firebase JWT implementation                 |
| `NullJwt`                   | No-op implementation                        |

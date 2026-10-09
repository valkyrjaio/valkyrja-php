# Support

## Introduction

The Support component provides small, focused utilities used across the
framework and in application code. `Time` and `Microtime` read the clock, and
each one freezes for a test. `Rfc3339` renders an instant for the wire.

## Time

`Valkyrja\Support\Time\Time` is a static time provider that supports freezing
for tests.

```php
Time::freeze(int $time): void      // Freeze time at the given Unix timestamp
Time::unfreeze(): void             // Resume real time
Time::get(): int                   // Return the frozen time, or time() if not frozen
```

`Valkyrja\Support\Time\Microtime` mirrors the same API at microsecond
precision, and adds a millisecond reader:

```php
Microtime::freeze(float $microtime): void
Microtime::unfreeze(): void
Microtime::get(): float            // Returns frozen microtime, or microtime(true) if not frozen
Microtime::getMilliseconds(): int  // The same instant as whole epoch milliseconds, never negative
```

`Valkyrja\Support\Time\Rfc3339` renders epoch milliseconds as an RFC 3339
instant in UTC, with millisecond precision. The value must not be negative.

```php
Rfc3339::fromMilliseconds(int $milliseconds): string
```

`Time` and `Microtime` are designed to be extended. Override `time()` or
`microtime()` in a subclass to substitute a custom time source.

The primary use case is deterministic testing. Code that calls `Time::get()`
instead of `time()` directly can be tested with a fixed timestamp:

```php
Time::freeze(1_700_000_000);

// ... exercise code that reads Time::get() ...

Time::unfreeze();
```

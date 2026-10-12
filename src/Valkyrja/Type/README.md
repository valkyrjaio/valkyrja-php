# Type

## Introduction

The Type component provides typed value objects. Each type wraps a PHP value,
validates the value on construction, and serializes cleanly. Every type
implements `TypeContract`. The component also contains a model system with
property casting, a collection, JSON wrappers, identifier types, support for
the UUID, ULID, and VLID formats, and static factory and support classes for
strings, arrays, objects, and enums.

## TypeContract

`Valkyrja\Type\Contract\TypeContract` is the root contract for all types:

```php
public static function fromValue(mixed $value): static;    // factory
public function asValue(): mixed;                          // the wrapped value
public function asFlatValue(): string|int|float|bool|null; // flattened for storage or transport
public function modify(callable $closure): static;         // returns a new, modified instance
```

`modify()` passes the current value through the callable and builds a new
instance with `fromValue()`; the original is unchanged:

```php
use Valkyrja\Type\String\StringT;

$name  = new StringT('valkyrja');
$upper = $name->modify(static fn (string $value): string => strtoupper($value));

$upper->asValue(); // 'VALKYRJA'
$name->asValue();  // 'valkyrja'
```

Every type also implements `JsonSerializable`, so `json_encode()` accepts a
type directly and encodes `asValue()`.

## Primitive Wrappers

| Class              | Wraps    | Note                                                |
| :----------------- | :------- | :-------------------------------------------------- |
| `StringT`          | `string` | String operations, see below                        |
| `NonEmptyString`   | `string` | Throws `StringInvalidEmptyStringException` for `''` |
| `BoolT`            | `bool`   | `fromValue()` applies the `(bool)` cast             |
| `TrueT`            | `true`   | The constructor takes no argument                   |
| `FalseT`           | `false`  | The constructor takes no argument                   |
| `IntT`             | `int`    |                                                     |
| `FloatT`           | `float`  |                                                     |
| `NullT`            | `null`   | The constructor takes no argument                   |
| `ArrayT`           | `array`  | `asFlatValue()` returns the JSON-encoded array      |
| `NonEmptyArray`    | `array`  | Throws `ArrayInvalidNonEmptyException` for `[]`     |
| `ObjectT`          | `object` | `asFlatValue()` returns the JSON-encoded object     |
| `SerializedObject` | `object` | `asFlatValue()` returns the `serialize()` output    |

## Strings

Transformations on `StringT` return a new instance; checks return `bool`:

```php
use Valkyrja\Type\String\StringT;

$string = new StringT('Hello World');

$string->toSnakeCase()->asValue();  // 'hello_world'
$string->toSlug()->asValue();       // 'hello-world'
$string->toStudlyCase()->asValue(); // 'HelloWorld'
$string->contains('World');         // true
$string->min(5);                    // true — the length is at least 5
$string->max(5);                    // false — the length is over 5
```

Transformations: `replace()`, `replaceAll()`, `replaceAllWith()`, `substr()`,
`toUpperCase()`, `toLowerCase()`, `toTitleCase()`, `toCapitalized()`,
`toCapitalizedWords()`, `toSnakeCase()`, `toSlug()`, `toStudlyCase()`,
`ucFirstLetter()`.

Checks: `startsWith()`, `startsWithAny()`, `endsWith()`, `endsWithAny()`,
`contains()`, `containsAny()`, `isEmail()`, `isAlphabetic()`,
`isLowercase()`, `isUppercase()`, `min()`, `max()`.

The length checks `min()` and `max()` return `bool` and never throw.

### String Factories

Every `StringT` operation is also a static method, so the operations work
without a wrapper instance. The checks and the replacements are on
`Valkyrja\Type\String\Factory\StringFactory`. The case conversions are on
`StringCaseFactory`. The factories add a few extras:

```php
use Valkyrja\Type\String\Factory\StringCaseFactory;
use Valkyrja\Type\String\Factory\StringFactory;

StringFactory::random(16);      // random hex string; the argument is the byte count
StringFactory::randomMd5();     // MD5 of a random string
StringFactory::randomBase64();  // base64 of a random string
StringFactory::fromMixed($value); // stringify any value; arrays and objects JSON-encode

StringCaseFactory::allToSnakeCase('appName', 'appEnv'); // ['app_name', 'app_env']
```

Each `StringCaseFactory` conversion has an `allTo*` variant that converts
many strings at once. The variant of `ucFirstLetter()` is the exception, and
that variant has the name `allUcFirstLetter()`. `MbStringFactory` extends
`StringFactory` with multibyte-safe (UTF-8) `substr()`, `toTitleCase()`,
`toLowerCase()`, and `toUpperCase()`.

## Numbers

`IntT` and `FloatT` wrap an `int` and a `float`. `fromValue()` accepts the
native type, a string, a bool, or the other numeric type, and applies the
matching PHP cast; an array becomes `1`/`0` by emptiness. Any other value
throws `IntInvalidFromValueException` or `FloatInvalidFromValueException`:

```php
use Valkyrja\Type\Int\IntT;

IntT::fromValue('42')->asValue(); // 42
```

## Booleans and Null

`BoolT::fromValue()` applies the `(bool)` cast. `TrueT`, `FalseT`, and
`NullT` always hold their fixed value; their `fromValue()` ignores the given
value.

## Arrays

`ArrayT::fromValue()` decodes a JSON string and casts any other value with
`(array)`. `asFlatValue()` returns the JSON-encoded string:

```php
use Valkyrja\Type\Array\ArrayT;

new ArrayT([1, 2])->asFlatValue(); // '[1,2]'
```

### ArrayFactory

`Valkyrja\Type\Array\Factory\ArrayFactory` provides the static array helpers
that the wrappers and models build on:

```php
use Valkyrja\Type\Array\Factory\ArrayFactory;

ArrayFactory::toString(['a' => 1]);              // '{"a":1}'
ArrayFactory::fromString('{"a":1}');             // ['a' => 1]
ArrayFactory::fromMixed($value);                 // string decodes as JSON, other values cast
ArrayFactory::newWithoutNull(['a' => 1, 'b' => null]); // ['a' => 1]
ArrayFactory::filterEmptyStrings('a', '', 'b');  // [0 => 'a', 2 => 'b'] — keys are kept
ArrayFactory::validateKeysAreStrings($array);    // throws ArrayInvalidStringKeysException
ArrayFactory::determineIfKeysAreStrings($array); // bool

// Read a nested value by dot notation, with an optional default and separator
ArrayFactory::getValueDotNotation(['app' => ['name' => 'valkyrja']], 'app.name'); // 'valkyrja'
```

### ArrayOf Guards

`Valkyrja\Type\Array\Support\ArrayOf` asserts that variadic values share one
type. `strings()`, `ints()`, `floats()`, `booleans()`, `arrays()`,
`objects()`, `enums()`, and `backedEnums()` rely on their parameter types, so
a wrong value throws a `TypeError` under `strict_types`. `true()`, `false()`,
and `null()` check each value and throw `ArrayInvalidTrueValueException`,
`ArrayInvalidFalseValueException`, or `ArrayInvalidNullValueException`.

## Objects

`ObjectT::fromValue()` decodes a JSON string and casts any other value with
`(object)`. `SerializedObject::fromValue()` unserializes a string. Pass the
allowed classes, or the result is a `__PHP_Incomplete_Class`:

```php
use stdClass;
use Valkyrja\Type\Object\SerializedObject;

$type = SerializedObject::fromValue($serialized, [stdClass::class]);
```

`modify()` on both clones the wrapped object before the closure runs, so the
original object is never mutated.

### ObjectFactory

```php
use Valkyrja\Type\Object\Enum\PropertyVisibilityFilter;
use Valkyrja\Type\Object\Factory\ObjectFactory;

ObjectFactory::toString($object);           // JSON encode
ObjectFactory::fromString('{"a":1}');       // decode to an object
ObjectFactory::toSerializedString($object); // serialize()
ObjectFactory::fromSerializedString($string, [stdClass::class]);
ObjectFactory::getProperties($object);      // the public properties
ObjectFactory::getAllProperties($object, PropertyVisibilityFilter::PRIVATE_PROTECTED);
ObjectFactory::toDeepArray($object);        // nested objects become arrays
ObjectFactory::getValueDotNotation($object, 'address.city');
```

`PropertyVisibilityFilter` selects the visibilities that
`getAllProperties()` returns: `ALL`, `PUBLIC`, `PROTECTED`, `PRIVATE`,
`PUBLIC_PROTECTED`, `PUBLIC_PRIVATE`, or `PRIVATE_PROTECTED`.

### Cls

`Valkyrja\Type\Object\Support\Cls` provides class-name helpers:

```php
use Valkyrja\Type\Json\Json;
use Valkyrja\Type\Object\Support\Cls;

Cls::inherits($class, $parentOrInterface);   // is_a() by name
Cls::validateInherits($class, $parent);      // throws InvalidObjectProvidedException
Cls::hasProperty($class, 'name');            // property_exists()
Cls::validateHasProperty($class, 'name');    // throws InvalidObjectPropertyProvidedException
Cls::getName(Json::class);                   // 'Json'
Cls::getNiceName(Json::class);               // 'ValkyrjaTypeJsonJson'
```

## Identifiers

| Class      | Contract           | Wraps         |
| :--------- | :----------------- | :------------ |
| `Id`       | `IdContract`       | `string\|int` |
| `StringId` | `StringIdContract` | `string`      |
| `IntId`    | `IntIdContract`    | `int`         |

The contracts live in `Valkyrja\Type\Id\Contract`. Each one extends
`TypeContract` directly. `StringIdContract` and `IntIdContract` do not extend
`IdContract`. `fromValue()` accepts a string, an int, or a float and casts to
the wrapped type (`IntId` also accepts a bool); any other value throws
`IdInvalidFromValueException`.

## Enums

The `Valkyrja\Type\Enum\Trait\Enumerable` trait supplies the methods that
`Valkyrja\Type\Enum\Contract\EnumContract` declares. The contract extends
`TypeContract` and PHP's `UnitEnum`, and the enum declares the contract:

```php
use Valkyrja\Type\Enum\Contract\ArrayableContract;
use Valkyrja\Type\Enum\Contract\EnumContract;
use Valkyrja\Type\Enum\Trait\Arrayable;
use Valkyrja\Type\Enum\Trait\Enumerable;

enum Status: string implements EnumContract, ArrayableContract
{
    use Arrayable;
    use Enumerable;

    case active   = 'active';
    case inactive = 'inactive';
}

Status::fromValue('active');   // Status::active
Status::active->asFlatValue(); // 'active'
json_encode(Status::active);   // '"active"'
```

`fromValue()` returns a given case unchanged. It throws
`EnumInvalidValueException` for a value that is not a string or an int, and
for a unit enum name that matches no case. A backed enum delegates to
`::from()`, so an unknown value throws PHP's `ValueError`. `asFlatValue()`
returns the backed value, or the case name for a unit enum. `modify()` throws
`EnumCannotModifyException`.

The `Arrayable` trait is a separate opt-in that supplies the methods that
`ArrayableContract` declares, with cached introspection. The `Status` enum
above uses the trait and declares the contract:

```php
Status::names();          // ['active', 'inactive']
Status::values();         // ['active', 'inactive'] — the names for a unit enum
Status::asArray();        // name => value
Status::asReverseArray(); // value => name
```

The same helpers exist statically on `Valkyrja\Type\Enum\Support\Enumerable`
for any enum class. `Enumerable::names(Status::class)` is one example. The
`Valkyrja\Type\Enum\Trait\JsonSerializable` trait gives an enum a
`jsonSerialize()` that returns the backed value or name without the rest of
`EnumContract`. `Valkyrja\Type\Enum\Type` is a plain enum of the primitive
kind names (`array`, `object`, `string`, `int`, `float`, `bool`, `true`,
`false`, `null`).

## Collections

`Valkyrja\Type\Collection\Collection` implements `CollectionContract`, an
array-backed collection. Keys are `string|int`. Values are
`string|int|float|bool|array|object|null`, shown as `TValue` below:

```php
public function setAll(array $collection): static;
public function all(): array;
public function keys(): array;
public function get(string|int $key, TValue $default = null): TValue;
public function set(string|int $key, TValue $value): static;
public function has(string|int $key): bool;
public function remove(string|int $key): static;
public function exists(mixed $value): bool;
public function count(): int;
public function isEmpty(): bool;
public function __toString(): string; // the JSON-encoded collection
```

The magic methods `__get()`, `__set()`, `__isset()`, and `__unset()` mirror
`get()`, `set()`, `has()`, and `remove()`:

```php
use Valkyrja\Type\Collection\Collection;

$collection = new Collection(['a' => 1]);
$collection->set('b', 2);

$collection->get('a');    // 1
$collection->get('c', 0); // 0 — the default
$collection->exists(2);   // true — search by value
$collection->b;           // 2
(string) $collection;     // '{"a":1,"b":2}'
```

## JSON

`Json` wraps a decoded JSON array, and `JsonObject` wraps a decoded JSON
object. For both, `fromValue()` decodes a JSON string, and `asFlatValue()`
returns the JSON-encoded string. The contracts are `JsonContract` and
`JsonObjectContract` in `Valkyrja\Type\Json\Contract`:

```php
use Valkyrja\Type\Json\Json;

$json = Json::fromValue('{"name":"Alice"}');
$json->asValue();     // ['name' => 'Alice']
$json->asFlatValue(); // '{"name":"Alice"}'
```

## Models

`Valkyrja\Type\Model` provides a base model class with property access, mass
assignment, array serialization, change tracking, and optional type casting.
See the [Model README](Model/README.md).

## UUID, ULID, and VLID

The component provides typed identifiers for the UUID, ULID, and VLID
(Valkyrja Universally Unique Lexicographically Sortable Identifier) formats,
each with factories, validation, and version-specific exceptions. See the
[UUID](Uuid/README.md), [ULID](Ulid/README.md), and [VLID](Vlid/README.md)
READMEs. `Valkyrja\Type\Uid` holds the shared base: `UidFactory` (with
`isValid()`, `validate()`, and the permissive `\w+` regex) and the `Uid`
value object.

## Casts

`Valkyrja\Type\Data\Cast` records the type to convert a value to, and how to
return the result. `Cast::$type` is a `class-string`, and PHP holds a class
string as a container binding key, so a reader of a cast resolves the type
through the container or calls the named constructor of the type.

`Valkyrja\Type\Enum\CastType` names a type for each primitive that this
component ships. `Valkyrja\Type\Provider\TypeServiceProvider` binds every one
of them, and `TypeComponentProvider` returns that service provider. An
application that resolves a cast type through the container registers that
component provider.

`Valkyrja\Type\Constant\CastArgument::VALUE` names the argument that carries
the raw value. A binding of a cast type reads the value under that key.

Warning: register a cast type with `bind`. A reader that resolves a cast type
calls `getService()`, which reads only a service binding, so an alias and an
instance that `setSingleton` holds each raise
`ContainerInvalidReferenceException`.

`getService()` also skips the singleton cache, so the container builds one type
for each value. That is not the lifetime that `bindSingleton` states, which is
the second reason a cast type takes `bind`.

Warning: a binding of a cast type takes the raw value under
`CastArgument::VALUE`. A call that passes no such argument raises
`TypeMissingCastArgumentException`, because a type built from a value the caller
never gave hides the mistake.

Warning: a binding of a cast type returns a `TypeContract`. A reader calls
`asValue()` on the object that the container builds, and an object without that
method raises an `Error`.

## Exceptions

Every exception in the component implements
`Valkyrja\Type\Throwable\Contract\TypeThrowable`. Five subcomponents add
their own marker contract. The five subcomponents are Object, Uid, Ulid,
Uuid, and Vlid. `Valkyrja\Type\Uid\Throwable\Contract\UidThrowable` is one
example, and the UUID, ULID, and VLID exceptions also implement that
contract. The exceptions of the other subcomponents implement `TypeThrowable`
only. Catch a marker contract to handle a whole family:

```php
use Valkyrja\Type\Throwable\Contract\TypeThrowable;
use Valkyrja\Type\Uuid\UuidV4;

try {
    $uuid = UuidV4::fromValue($value);
} catch (TypeThrowable $exception) {
    // every Type component failure lands here
}
```

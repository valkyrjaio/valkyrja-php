# ORM

## Introduction

The ORM component provides a PDO data access layer for MySQL, PostgreSQL, and SQLite. It includes an entity system, a repository for typed data access, an immutable query builder, raw statement execution, and migration base classes. The `NullManager` supports testing.

An entity extends the Model system from the Type component. That system gives the entity property casting, exposure control, and storable array serialization.

## The Manager

`Valkyrja\Orm\Manager\Contract\ManagerContract` is the entry point of the component. The container resolves the active manager:

```php
use Valkyrja\Orm\Manager\Contract\ManagerContract;

$orm = $container->getSingleton(ManagerContract::class);
```

The binding exists when the application lists `OrmComponentProvider` in its config `providers` array ([Service Registration](#service-registration)).

The contract declares every entry point:

```php
// Repositories and query builders
public function createRepository(string $entity): RepositoryContract;
public function createQueryBuilder(): QueryBuilderFactoryContract;

// Transactions
public function beginTransaction(): bool;
public function inTransaction(): bool;
public function ensureTransaction(): void;
public function commit(): bool;
public function rollback(): bool;

// Raw queries
public function prepare(string $query): StatementContract;
public function query(string $query): StatementContract;

// Identity
public function lastInsertId(string $table, string $idField): string;
```

`prepare()` returns a statement that is not yet executed, so the caller binds values and calls `execute()`. `query()` prepares the statement and executes it in one step, and throws an `OrmExecuteException` when the execution fails. `lastInsertId()` returns the id of the last inserted row, and throws an `OrmNoLastIdException` when the connection reports none.

| Class           | Description                      |
| :-------------- | :------------------------------- |
| `MysqlManager`  | PDO connection to MySQL          |
| `PgsqlManager`  | PDO connection to PostgreSQL     |
| `SqliteManager` | PDO connection to SQLite         |
| `NullManager`   | No-op implementation for testing |

The `defaultManager` config property selects the implementation that the container binds to `ManagerContract`.

`PgsqlManager` overrides `lastInsertId()` to read the PostgreSQL sequence named `{table}_{idField}_seq`, which is the name that `SERIAL` and `BIGSERIAL` columns create. A table with a different sequence name cannot use the method.

The `NullManager` supports testing: `prepare()` and `query()` return a `NullStatement`, the transaction methods report success, and `lastInsertId()` returns `'id'`. A `NullStatement` accepts every bind, executes to `true`, and fetches no rows, so repository code runs with no database.

## Entities

### The Entity Contract

`Valkyrja\Orm\Entity\Contract\EntityContract` extends both `CastableModelContract` and `ExposableModelContract` from the Type component:

```php
// Table and identity
public static function getTableName(): string;
public static function getIdField(): string;
public static function getRepository(): string;       // RepositoryContract class

// Field metadata
public static function getRelationshipProperties(): array;  // Properties to exclude from DB writes
public static function getUnStorableFields(): array;        // Fields never written to the DB

// Values
public function getIdValue(): string|int;
public function asStorableArray(string ...$properties): array;
public function asStorableChangedArray(): array;             // Only changed properties
```

`asStorableArray()` returns the storable properties as a column-to-value array, and an optional property list narrows the result. `asStorableChangedArray()` returns only the properties that changed since hydration, so an update writes only those columns. `getIdValue()` returns the value of the id field, and throws an `OrmUnexpectedIdValueException` when the value is not an int and not a non-empty string. `getRepository()` names the repository class that `createRepository()` resolves for the entity.

### Defining an Entity

Extend the abstract `Entity` base class and implement `getTableName()`. The base class defaults `getIdField()` to `id` and `getRepository()` to the `Repository` class:

```php
use Override;
use Valkyrja\Orm\Entity\Abstract\Entity;

class Post extends Entity
{
    public int $id;
    public string $title;
    public string $status = 'draft';
    public string|null $body = null;

    #[Override]
    public static function getTableName(): string
    {
        return 'posts';
    }
}
```

Override the other static methods when the entity departs from the defaults. `getRelationshipProperties()` names properties that hold related entities, and `getUnStorableFields()` names properties that never reach the database. Both lists are excluded from `asStorableArray()`:

```php
use Override;
use Valkyrja\Orm\Entity\Abstract\Entity;

class Post extends Entity
{
    public int $post_id;
    public string $title;
    public Author|null $author = null;
    public string|null $editorNote = null;

    #[Override]
    public static function getTableName(): string
    {
        return 'posts';
    }

    #[Override]
    public static function getIdField(): string
    {
        return 'post_id';
    }

    #[Override]
    public static function getRelationshipProperties(): array
    {
        return ['author'];
    }

    #[Override]
    public static function getUnStorableFields(): array
    {
        return ['editorNote'];
    }
}
```

### Property Casts

An entity declares casts through the Type component's `getCastings()`. A cast converts the property through a Type class on hydration, and `asStorableArray()` writes the flat value back:

```php
use Override;
use Valkyrja\Orm\Entity\Abstract\Entity;
use Valkyrja\Type\Data\Cast;
use Valkyrja\Type\Int\IntT;

class Score extends Entity
{
    public int $id;
    public int|IntT|null $score = null;
    public array|string|null $history = null;

    #[Override]
    public static function getTableName(): string
    {
        return 'scores';
    }

    #[Override]
    public static function getCastings(): array
    {
        return [
            'score'   => new Cast(IntT::class),
            'history' => new Cast(IntT::class, isArray: true),
        ];
    }
}
```

An array cast serializes the array to a string for storage, and throws an `OrmArrayCastingException` when the property value is not an array.

`EntityCast` extends the Type component's `Cast` for entity casting:

```php
use Valkyrja\Orm\Data\EntityCast;

new EntityCast(
    type: SomeEntity::class,   // Entity class or CastType
    column: 'foreign_key',     // Optional: column to use for retrieval
    convert: true,
    isArray: false,
);
```

The cast holds no relationship list, and the ORM loads no related entity. Add a method to a [custom repository](#custom-repositories) to load a relationship. The repository is where the application chooses the join type, or chooses to read the related entity from its own repository.

The `EntityRouteMatchedMiddleware` reads an `EntityCast` on a route parameter, finds the entity through its repository, and replaces the parameter value with the entity.

### Dated and Soft Delete Entities

Implement one of these contracts to tell the repository to stamp a date. Each contract declares no method:

| Contract                   | The repository then                                                                              |
| :------------------------- | :----------------------------------------------------------------------------------------------- |
| `DatedEntityContract`      | stamps the created date and the modified date on `create()`, and the modified date on `update()` |
| `SoftDeleteEntityContract` | stamps the deleted date on `delete()` and keeps the row                                          |

The abstract base classes `DatedEntity` and `SoftDeleteEntity` implement the contract and add the fields. The traits `DatedFields` and `SoftDeleteFields` add the fields alone. `DatedFields` declares `created_at` and `updated_at`, and `SoftDeleteFields` declares `deleted_at`:

```php
use Override;
use Valkyrja\Orm\Entity\Abstract\DatedEntity;

class Post extends DatedEntity
{
    public int $id;
    public string $title;
    // created_at and updated_at come from the DatedFields trait.

    #[Override]
    public static function getTableName(): string
    {
        return 'posts';
    }
}
```

An entity that needs both behaviors extends one base class and adds the other contract with its trait:

```php
use Override;
use Valkyrja\Orm\Entity\Abstract\DatedEntity;
use Valkyrja\Orm\Entity\Contract\SoftDeleteEntityContract;
use Valkyrja\Orm\Entity\Trait\SoftDeleteFields;

class Post extends DatedEntity implements SoftDeleteEntityContract
{
    use SoftDeleteFields;

    public int $id;
    public string $title;

    #[Override]
    public static function getTableName(): string
    {
        return 'posts';
    }
}
```

### Registering Entity Metadata

The date format and the field names come from `Valkyrja\Orm\Registry\Contract\EntityMetadataRegistryContract`, because a data object holds no static method. The registry maps an entity class to an `EntityMetadata` data object through `has()`, `get()`, and `withEntity()`. The registry infers nothing: `get()` throws an `OrmUnregisteredEntityException` when the entity is not registered, and the repository throws the same exception when a contract implementation has no matching metadata.

The registry is immutable. `withEntity()` returns a new registry, so an application registers an entity in a service provider and replaces the singleton:

```php
$registry = $container->getSingleton(EntityMetadataRegistryContract::class);

$container->setSingleton(
    EntityMetadataRegistryContract::class,
    $registry->withEntity(
        Post::class,
        new EntityMetadata(dated: new DatedMetadata(), softDelete: new SoftDeleteMetadata()),
    ),
);
```

Each part of `EntityMetadata` is optional, and each part's arguments default to the common names:

```php
new EntityMetadata(
    dated: new DatedMetadata(
        format: DateFormat::MICROSECOND,
        dateCreatedField: 'created_at',
        dateModifiedField: 'updated_at',
    ),
    softDelete: new SoftDeleteMetadata(
        format: DateFormat::DEFAULT,
        dateDeletedField: 'deleted_at',
    ),
);
```

An application with a different schema registers its own names:

```php
new EntityMetadata(
    dated: new DatedMetadata(dateCreatedField: 'date_created', dateModifiedField: 'date_modified'),
);
```

Warning: the entity must declare a property with the registered name. `asStorableArray()` reads the object properties, so a registered name that the entity does not declare becomes a dynamic property. Use your own entity, and not the `DatedFields` or `SoftDeleteFields` trait, when your names are not the defaults.

### The Stored Date

`DateFormat` holds the three formats that the ORM writes. Each one puts the largest unit first, so a text sort of the column is a date sort and the date functions of the database read the value:

| Constant                  | Format          | Column type   |
| :------------------------ | :-------------- | :------------ |
| `DateFormat::DEFAULT`     | `Y-m-d H:i:s`   | `DATETIME`    |
| `DateFormat::MILLISECOND` | `Y-m-d H:i:s.v` | `DATETIME(3)` |
| `DateFormat::MICROSECOND` | `Y-m-d H:i:s.u` | `DATETIME(6)` |

**The ORM stores UTC.** `DateFactory` builds each time with the offset `+00:00`, and no format holds a timezone, because the same characters would repeat on every row. Convert the value to a local time when you show it to a person, and never assume the column holds a local time.

## Repositories

`Valkyrja\Orm\Repository\Contract\RepositoryContract` provides the CRUD interface for a single entity type:

```php
public function find(string|int $id): EntityContract|null;
public function findBy(Where ...$where): EntityContract|null;
public function all(): array;
public function allBy(Where ...$where): array;
public function create(EntityContract $entity): void;
public function update(EntityContract $entity): void;
public function delete(EntityContract $entity): void;
public function forceDelete(EntityContract $entity): void;
```

Obtain a repository through the manager:

```php
$repository = $orm->createRepository(Post::class);
```

Each write method builds a query, prepares it, binds the values, and executes it immediately. The component holds no unit of work, so there is no separate flush step. Wrap several writes in a [transaction](#transactions) when they must land together.

### Retrieving Entities

`find()` matches the id field and returns the entity, or `null` when no row matches:

```php
$post = $repository->find(1);
```

`findBy()` takes where clauses and returns the first matching entity, or `null`:

```php
use Valkyrja\Orm\Data\Value;
use Valkyrja\Orm\Data\Where;

$post = $repository->findBy(new Where(new Value('slug', 'intro-to-orm')));
```

`findBy()` renders no `LIMIT`, so the statement fetches and hydrates every matching row before the repository returns the first. When the filter matches many rows, use the [query builder](#query-builder) with `withLimit(1)` instead.

`all()` returns every row as an entity array, and `allBy()` filters with where clauses:

```php
use Valkyrja\Orm\Data\Value;
use Valkyrja\Orm\Data\Where;
use Valkyrja\Orm\Data\Where\AndWhere;

$posts = $repository->all();

$published = $repository->allBy(
    new Where(new Value('status', 'published')),
    new AndWhere(new Value('author_id', 7)),
);
```

The clauses render in order. Open the list with a `Where` or a `NotWhere`, and give each later clause a joining type (`AndWhere`, `OrWhere`). The [where clause](#where-clauses) section lists every type and states the placement rule. That section also covers `WhereGroup`, but `findBy()` and `allBy()` accept only `Where` arguments, so a grouped condition needs the [query builder](#query-builder).

### Creating an Entity

`create()` writes every storable property as an `INSERT`, reads `lastInsertId()`, and writes the new id back onto the entity.

Warning: PDO reports the last insert id as a string, and the repository writes that string onto the id property through `__set()`. A string assigned to a typed `int` property raises a `TypeError`. Declare a set callable when the property is an `int`, so the assignment converts the value:

```php
public function setId(string|int $id): void
{
    $this->id = (int) $id;
}

#[Override]
protected function internalSetCallables(): array
{
    return [
        'id' => [$this, 'setId'],
    ];
}
```

The `Post` in [Defining an Entity](#defining-an-entity) declares `public int $id;`, so it needs this callable. With the callable declared, the flow runs:

```php
$post = new Post();
$post->title = 'Intro to the ORM';

$repository->create($post);

$id = $post->id; // The id of the new row
```

For a `DatedEntityContract` entity the repository stamps the created date and the modified date before the write.

### Updating an Entity

`update()` writes only the properties that changed since hydration, as an `UPDATE` filtered on the id field:

```php
$post = $repository->find(1);
$post->title = 'A better title';

$repository->update($post);
// UPDATE posts SET title = :title WHERE id = :id
```

For a `DatedEntityContract` entity the repository stamps the modified date before the write. The repository never writes the deleted date here, so an update does not delete an entity, and an update does not restore a deleted entity.

Warning: `update()` on an entity with no changed property renders an empty `SET` list, and no driver accepts the statement:

```php
$post = $repository->find(1);

$repository->update($post);
// UPDATE posts SET  WHERE id = :id
```

The modified-date stamp counts as a change only when the new stamp differs from the stored value. `DateFormat::DEFAULT` holds second precision, so a `DatedEntityContract` entity fetched and updated inside the same second, with no other changed property, also reaches the failure. Call `update()` only after a property changed.

### Deleting an Entity

`delete()` removes the row of an entity. For a `SoftDeleteEntityContract` entity it stamps the deleted date and keeps the row instead:

```php
$repository->delete($post);
```

`forceDelete()` always removes the row. Warning: the method destroys the data of a soft delete entity. Use the method only when a law or a data policy requires the removal, such as an erasure request:

```php
$repository->forceDelete($post);
```

A read returns a soft-deleted row. The repository adds no filter, so exclude the row yourself when you want only the live rows.

Warning: a `Where` cannot render a bare `IS NULL`, because every clause renders a bind parameter, and MySQL and PostgreSQL do not accept a parameter after `IS`. Filter the live rows with a raw statement:

```php
$live = $orm->query('SELECT * FROM posts WHERE deleted_at IS NULL')
    ->fetchAllEntities(Post::class);
```

On MySQL, `Comparison::NULL_SAFE_EQUALS` also works: `new Where(new Value('deleted_at', null), Comparison::NULL_SAFE_EQUALS)` renders `deleted_at <=> :deleted_at`, and the null-safe equality matches the null rows. On SQLite, `Comparison::IS` works, because SQLite accepts a parameter after `IS`.

### Custom Repositories

`getRepository()` on the entity names the repository class. Extend `Repository` to add entity-specific methods, and name the subclass on the entity:

```php
use Valkyrja\Orm\Data\Value;
use Valkyrja\Orm\Data\Where;
use Valkyrja\Orm\Repository\Repository;

final class PostRepository extends Repository
{
    /**
     * Get the published posts.
     *
     * @return Post[]
     */
    public function published(): array
    {
        return $this->allBy(new Where(new Value('status', 'published')));
    }
}
```

```php
#[Override]
public static function getRepository(): string
{
    return PostRepository::class;
}
```

The PDO managers resolve the class from the container with the manager, the entity class, and the metadata registry as arguments. The [service provider](#service-registration) binds only the base `Repository`, and the container does not construct an unbound class, so every custom repository needs its own binding. The container throws a `ContainerInvalidReferenceException` for a repository class that has no binding. Bind the subclass with a factory that reads the three arguments:

```php
use Valkyrja\Container\Manager\Contract\ContainerContract;

$container->bind(
    PostRepository::class,
    static function (ContainerContract $container, array $arguments): PostRepository {
        [$manager, $entity, $registry] = $arguments;

        return new PostRepository($manager, $entity, $registry);
    }
);
```

Warning: the `NullManager` constructs the base `Repository` directly and never reads `getRepository()`, so a custom repository's methods are not available on a repository from the `NullManager`.

## Query Builder

`createQueryBuilder()` returns a factory with one method per statement type. Each method takes the table name and returns a typed builder:

```php
$factory = $orm->createQueryBuilder();

$select = $factory->select('posts');
$insert = $factory->insert('posts');
$update = $factory->update('posts');
$delete = $factory->delete('posts');
```

Every `with*` method returns a new instance, so a builder never mutates, and a shared base query stays safe to branch. Every builder implements `Stringable`, so a cast to `string` renders the SQL.

All four builders share the methods of `QueryBuilderContract`:

```php
->withFrom(string $table): static
->withAlias(string $alias): static
->withJoin(Join ...$joins): static
->withAddedJoin(Join ...$joins): static
->withWhere(Where|WhereGroup ...$where): static
->withAddedWhere(Where|WhereGroup ...$where): static
```

Each `with*` method replaces the current list, and each `withAdded*` method appends to it.

`withAlias()` renders the alias after the table name in the insert, update, and delete builders. The select builder stores the alias and does not render it.

### Select

`SelectQueryBuilderContract` adds columns, group by, order by, limit, and offset:

```php
->withColumns(string ...$columns): static
->withAddedColumns(string ...$columns): static
->withGroupBy(string ...$groupBy): static
->withAddedGroupBy(string ...$groupBy): static
->withOrderBy(OrderBy ...$orderBy): static
->withAddedOrderBy(OrderBy ...$orderBy): static
->withLimit(int $limit): static
->withOffset(int $offset): static
```

The columns default to `*`:

```php
use Valkyrja\Orm\Data\Join\LeftJoin;
use Valkyrja\Orm\Data\OrderBy;
use Valkyrja\Orm\Data\Value;
use Valkyrja\Orm\Data\Where;
use Valkyrja\Orm\Enum\Comparison;
use Valkyrja\Orm\Enum\JoinOperator;
use Valkyrja\Orm\Enum\SortOrder;

$select = $orm->createQueryBuilder()
    ->select('posts')
    ->withColumns('posts.*')
    ->withJoin(new LeftJoin(
        table: 'comments',
        column: 'posts.id',
        joinColumn: 'comments.post_id',
        comparison: Comparison::EQUALS,
        operator: JoinOperator::ON,
    ))
    ->withWhere(new Where(new Value('status', 'published')))
    ->withOrderBy(new OrderBy('created_at', SortOrder::DESC))
    ->withLimit(10);

// (string) $select renders, on one line:
// SELECT posts.* FROM posts LEFT JOIN comments ON posts.id = comments.post_id
//     WHERE status = :status ORDER BY created_at DESC LIMIT 10
```

### Insert

`InsertQueryBuilderContract` adds `withSet()` and `withAddedSet()`. Each `Value` name is a column, and each value renders as a bind parameter:

```php
use Valkyrja\Orm\Data\Value;

$insert = $orm->createQueryBuilder()
    ->insert('posts')
    ->withSet(
        new Value('title', 'Intro to the ORM'),
        new Value('status', 'published'),
    );

// (string) $insert renders:
// INSERT INTO posts (title, status) VALUES (:title, :status)
```

### Update

`UpdateQueryBuilderContract` adds the same `withSet()` and `withAddedSet()`. Each `Value` renders as a `column = :column` assignment:

```php
use Valkyrja\Orm\Data\Value;
use Valkyrja\Orm\Data\Where;

$update = $orm->createQueryBuilder()
    ->update('posts')
    ->withSet(new Value('status', 'archived'))
    ->withWhere(new Where(new Value('id', 1)));

// (string) $update renders:
// UPDATE posts SET status = :status WHERE id = :id
```

### Delete

The delete builder adds nothing to the shared methods:

```php
use Valkyrja\Orm\Data\Value;
use Valkyrja\Orm\Data\Where;

$delete = $orm->createQueryBuilder()
    ->delete('posts')
    ->withWhere(new Where(new Value('id', 1)));

// (string) $delete renders:
// DELETE FROM posts WHERE id = :id
```

### Joins

`Join` renders a join clause from a table, the two columns, a `Comparison`, a `JoinOperator` (`ON` or `WHERE`), and a `JoinType`. One subclass per join type sets the type for you:

| Class           | Renders as                                                |
| :-------------- | :-------------------------------------------------------- |
| `InnerJoin`     | `INNER JOIN comments ON posts.id = comments.post_id`      |
| `LeftJoin`      | `LEFT JOIN comments ON posts.id = comments.post_id`       |
| `RightJoin`     | `RIGHT JOIN comments ON posts.id = comments.post_id`      |
| `OuterJoin`     | `OUTER JOIN comments ON posts.id = comments.post_id`      |
| `FullOuterJoin` | `FULL OUTER JOIN comments ON posts.id = comments.post_id` |

Each subclass takes the same arguments:

```php
use Valkyrja\Orm\Data\Join\InnerJoin;
use Valkyrja\Orm\Enum\Comparison;
use Valkyrja\Orm\Enum\JoinOperator;

new InnerJoin(
    table: 'comments',
    column: 'posts.id',
    joinColumn: 'comments.post_id',
    comparison: Comparison::EQUALS,
    operator: JoinOperator::ON,
)
// Renders as: INNER JOIN comments ON posts.id = comments.post_id
```

`JoinOperator` holds `ON` and `WHERE`, and the operator renders between the table and the first column. The base `Join` also takes a `JoinType`, and its `JoinType::DEFAULT` renders a bare `JOIN`. Use a subclass unless you build the type dynamically.

### Values

`Value` binds a named parameter with its value. The name is both the column name and the bind parameter in the rendered clause:

```php
new Value(name: 'status', value: 'published')
// Renders as :status with PDO binding
```

The value may be a scalar, an array (renders as `(:name0, :name1, ...)`), or a nested `QueryBuilderContract` (renders as a subquery). An array's parameter suffixes come from its keys, so a list renders `:name0, :name1, ...` and the statement binds one parameter per element. The `Id` subclass defaults the name to `id`, so `new Id(1)` equals `new Value('id', 1)`.

### Where Clauses

`Where` wraps a `Value` with a comparison operator and a clause type. The clause renders as the column name, the operator, and the bind parameter:

```php
use Valkyrja\Orm\Data\Value;
use Valkyrja\Orm\Data\Where;
use Valkyrja\Orm\Enum\Comparison;

new Where(new Value('status', 'published'))                        // status = :status
new Where(new Value('id', [1, 2, 3]), Comparison::IN)              // id IN (:id0, :id1, :id2)
new Where(new Value('score', 50), Comparison::GREATER_THAN_EQUAL)  // score >= :score
new Where(new Value('title', 'intro%'), Comparison::LIKE)          // title LIKE :title
```

One subclass per clause type prefixes the rendered clause:

| Class         | Renders as                 |
| :------------ | :------------------------- |
| `AndWhere`    | `AND status = :status`     |
| `OrWhere`     | `OR status = :status`      |
| `NotWhere`    | `NOT status = :status`     |
| `AndNotWhere` | `AND NOT status = :status` |
| `OrNotWhere`  | `OR NOT status = :status`  |

The builder renders `WHERE` once, then each clause in order, separated by spaces. Open the list with a `Where` or a `NotWhere`. A joining type cannot open the list, because a leading `AndWhere` renders `WHERE AND`:

```php
$select = $orm->createQueryBuilder()
    ->select('posts')
    ->withWhere(
        new Where(new Value('status', 'published')),
        new AndWhere(new Value('author_id', 7)),
    );

// (string) $select renders:
// SELECT * FROM posts WHERE status = :status AND author_id = :author_id
```

`WhereGroup` groups clauses into a parenthesized block:

```php
use Valkyrja\Orm\Data\Value;
use Valkyrja\Orm\Data\Where;
use Valkyrja\Orm\Data\Where\AndWhere;
use Valkyrja\Orm\Data\Where\OrWhere;
use Valkyrja\Orm\Data\WhereGroup;
use Valkyrja\Orm\Enum\Comparison;

$select = $orm->createQueryBuilder()
    ->select('posts')
    ->withWhere(
        new WhereGroup(
            new Where(new Value('title', '%orm%'), Comparison::LIKE),
            new OrWhere(new Value('body', '%orm%'), Comparison::LIKE),
        ),
        new AndWhere(new Value('author_id', 7)),
        new AndWhere(new Value('status', 'published')),
    );

// The where part renders:
// WHERE (title LIKE :title OR body LIKE :body) AND author_id = :author_id AND status = :status
```

Each `Where` class has one place in a list. A `Where` or a `NotWhere` opens a
list. Either class opens the inner list of a group the same way. Every later
clause is an `AndWhere`, an `OrWhere`, an `AndNotWhere`, or an `OrNotWhere`.
Those clause types render as a prefix that joins the clause to the one before
it.

A group follows the same rule. One class per clause type prefixes the rendered
group:

| Class              | Renders as                   |
| :----------------- | :--------------------------- |
| `WhereGroup`       | `(status = :status)`         |
| `AndWhereGroup`    | `AND (status = :status)`     |
| `OrWhereGroup`     | `OR (status = :status)`      |
| `NotWhereGroup`    | `NOT (status = :status)`     |
| `AndNotWhereGroup` | `AND NOT (status = :status)` |
| `OrNotWhereGroup`  | `OR NOT (status = :status)`  |

A `WhereGroup` or a `NotWhereGroup` opens a list. An `AndWhereGroup`, an
`OrWhereGroup`, an `AndNotWhereGroup`, or an `OrNotWhereGroup` follows another
clause. Those clause types render as a prefix that joins the group to the
clause before it:

```php
use Valkyrja\Orm\Data\Value;
use Valkyrja\Orm\Data\Where;
use Valkyrja\Orm\Data\Where\OrWhere;
use Valkyrja\Orm\Data\WhereGroup\AndWhereGroup;
use Valkyrja\Orm\Enum\Comparison;

$select = $orm->createQueryBuilder()
    ->select('posts')
    ->withWhere(
        new Where(new Value('status', 'published')),
        new AndWhereGroup(
            new Where(new Value('title', '%orm%'), Comparison::LIKE),
            new OrWhere(new Value('body', '%orm%'), Comparison::LIKE),
        ),
    );

// The where part renders:
// WHERE status = :status AND (title LIKE :title OR body LIKE :body)
```

Warning: a group in the wrong position renders SQL that the database rejects.
A joining group cannot open the list, because a leading `AndWhereGroup` renders
`WHERE AND (…)`. A `WhereGroup` or a `NotWhereGroup` cannot follow another
clause, because neither one renders a joining operator before the parentheses.

Warning: a group that holds no clause renders empty parentheses, and the
database rejects that SQL. Give every group one `Where` or more.

The six classes cover every `WhereType` case, so the class name is the only
place a group states its clause type. To pick the type at run time, select the
class:

```php
use Valkyrja\Orm\Data\WhereGroup;
use Valkyrja\Orm\Data\WhereGroup\AndNotWhereGroup;
use Valkyrja\Orm\Data\WhereGroup\AndWhereGroup;
use Valkyrja\Orm\Data\WhereGroup\NotWhereGroup;
use Valkyrja\Orm\Data\WhereGroup\OrNotWhereGroup;
use Valkyrja\Orm\Data\WhereGroup\OrWhereGroup;
use Valkyrja\Orm\Enum\WhereType;

$group = match ($whereType) {
    WhereType::AND     => new AndWhereGroup(...$where),
    WhereType::OR      => new OrWhereGroup(...$where),
    WhereType::NOT     => new NotWhereGroup(...$where),
    WhereType::AND_NOT => new AndNotWhereGroup(...$where),
    WhereType::OR_NOT  => new OrNotWhereGroup(...$where),
    WhereType::DEFAULT => new WhereGroup(...$where),
};
```

A `Value` that holds a `QueryBuilderContract` renders as a subquery:

```php
$active = $orm->createQueryBuilder()
    ->select('authors')
    ->withColumns('id')
    ->withWhere(new Where(new Value('active', true)));

$select = $orm->createQueryBuilder()
    ->select('posts')
    ->withWhere(new Where(new Value('author_id', $active), Comparison::IN));

// (string) $select renders, on one line:
// SELECT * FROM posts WHERE author_id IN
//     (SELECT id FROM authors WHERE active = :active)
```

`bindValue()` skips a subquery `Value`, so bind the subquery's own values on the statement yourself. In the example above, bind `new Value('active', true)`.

### The Comparison Enum

`Valkyrja\Orm\Enum\Comparison` holds the 27 comparison operators:

| Case                 | Renders as    |
| :------------------- | :------------ |
| `EQUALS`             | `=`           |
| `NULL_SAFE_EQUALS`   | `<=>`         |
| `NOT_EQUAL`          | `!=`          |
| `NOT_EQUAL_ALT`      | `<>`          |
| `IN`                 | `IN`          |
| `NOT_IN`             | `NOT IN`      |
| `LIKE`               | `LIKE`        |
| `NOT_LIKE`           | `NOT LIKE`    |
| `SOUNDS_LIKE`        | `SOUNDS LIKE` |
| `RLIKE`              | `RLIKE`       |
| `IS`                 | `IS`          |
| `IS_NOT`             | `IS NOT`      |
| `MOD`                | `%`           |
| `MOD_ALT`            | `MOD`         |
| `GREATER_THAN`       | `>`           |
| `GREATER_THAN_EQUAL` | `>=`          |
| `LESS_THAN`          | `<`           |
| `LESS_THAN_EQUAL`    | `<=`          |
| `RIGHT_SHIFT`        | `>>`          |
| `LEFT_SHIFT`         | `<<`          |
| `MEMBER_OF`          | `MEMBER OF`   |
| `REGEXP`             | `REGEXP`      |
| `NOT_REGEXP`         | `NOT REGEXP`  |
| `BITWISE_XOR`        | `^`           |
| `LOGICAL_XOR`        | `XOR`         |
| `BITWISE_OR`         | `\|`          |
| `BITWISE_INVERSION`  | `~`           |

Warning: a `MEMBER_OF` clause renders an operand that MySQL's `MEMBER OF(json_array)` grammar does not accept, so the rendered clause fails to parse. Use a raw statement for a `MEMBER OF` filter.

### Group By, Order By, Limit, and Offset

`withGroupBy()` takes column names, and `withOrderBy()` takes `OrderBy` objects. `OrderBy` pairs a field with a `SortOrder` (`ASC` or `DESC`), and the order defaults to `ASC`:

```php
use Valkyrja\Orm\Data\OrderBy;
use Valkyrja\Orm\Enum\SortOrder;

$select = $orm->createQueryBuilder()
    ->select('posts')
    ->withColumns('author_id', 'COUNT(*)')
    ->withGroupBy('author_id')
    ->withOrderBy(new OrderBy('author_id', SortOrder::ASC))
    ->withLimit(10)
    ->withOffset(20);

// (string) $select renders, on one line:
// SELECT author_id, COUNT(*) FROM posts GROUP BY author_id
//     ORDER BY author_id ASC LIMIT 10 OFFSET 20
```

### Counting Rows

When the first column starts with `COUNT`, the select builder omits `GROUP BY`, `ORDER BY`, `LIMIT`, and `OFFSET`:

```php
$count = $orm->createQueryBuilder()
    ->select('posts')
    ->withColumns('COUNT(*)')
    ->withWhere(new Where(new Value('status', 'published')));

// (string) $count renders:
// SELECT COUNT(*) FROM posts WHERE status = :status
```

Execute the query and read the count from the statement. `getCount()` reads the `COUNT(*)` column, or the `count` column that PostgreSQL returns:

```php
$statement = $orm->prepare((string) $count);
$statement->bindValue(new Value('status', 'published'));
$statement->execute();

$total = $statement->getCount();
```

### Binding and Executing a Built Query

A builder renders SQL with named bind parameters, and it executes nothing. Prepare the rendered string, bind each `Value`, and execute. Reuse the same `Value` objects that built the query, so the names and the values stay in one place:

```php
$where  = new Where(new Value('status', 'published'));
$select = $orm->createQueryBuilder()
    ->select('posts')
    ->withWhere($where);

$statement = $orm->prepare((string) $select);
$statement->bindValue($where->value);
$statement->execute();

$posts = $statement->fetchAllEntities(Post::class);
```

## Statements

`Valkyrja\Orm\Statement\Contract\StatementContract` wraps a prepared PDO statement:

```php
public function bindValue(Value $value): bool;
public function execute(): bool;

// Fetch results
public function fetch(): array;                              // Single row as array
public function fetchEntity(string $entity): EntityContract; // Single row as entity
public function fetchColumn(int $columnNumber = 0): mixed;
public function fetchAll(): array;                           // All rows as arrays
public function fetchAllEntities(string $entity): array;     // All rows as entities

// Metadata
public function getCount(): int;
public function getRowCount(): int;
public function getColumnCount(): int;
public function getColumnMeta(int $columnNumber): array;

// Errors
public function hasError(): bool;
public function getErrorCode(): string;
public function getErrorMessage(): string;
```

`bindValue()` binds the parameter with the PDO type that matches the PHP type. That PDO type is int, bool, null, or string. An array `Value` binds one parameter per element (`:name0`, `:name1`, ...). The full flow:

```php
$statement = $orm->prepare('SELECT * FROM posts WHERE status = :status');
$statement->bindValue(new Value('status', 'published'));
$statement->execute();

$posts = $statement->fetchAllEntities(Post::class);
```

`fetch()` and `fetchEntity()` read one row, and throw an `OrmFetchException` when no row remains. `fetchAll()` and `fetchAllEntities()` return an empty array when no row matches. A failed `execute()` throws a `PDOException` under the default `PDO::ERRMODE_EXCEPTION` options ([Configuration](#configuration)). `execute()` returns `false`, with `getErrorMessage()` holding the reason, only when the application overrides `PDO::ATTR_ERRMODE`.

`query()` shortens the flow for a query with no bind parameters:

```php
$posts = $orm->query('SELECT * FROM posts')->fetchAllEntities(Post::class);
```

## Transactions

The manager controls the transaction of the underlying connection. Wrap dependent writes so they land together or not at all:

```php
$orm->beginTransaction();

try {
    $repository->create($post);
    $repository->create($comment);

    $orm->commit();
} catch (Throwable $exception) {
    $orm->rollback();

    throw $exception;
}
```

`ensureTransaction()` begins a transaction only when none is in progress, so a helper method joins the caller's transaction instead of failing to nest one. `inTransaction()` reports the current state:

```php
$orm->ensureTransaction(); // Begins one, or joins the one in progress
```

## Route Entity Binding

`EntityRouteMatchedMiddleware` is a route matched middleware from the Http Middleware component. It inspects each parameter of a matched dynamic route, and it acts when the parameter's cast type is an entity class:

- A value that is already an entity passes through.
- A non-empty string or int value loads the entity: an `EntityCast` with a `column` finds by that column, and any other cast finds by id.
- A missing entity returns a 404 response, rendered from the `errors/404` view template.
- A value of any other type returns a 400 response, rendered from the `errors/400` view template.

On success the middleware replaces the parameter value with the loaded entity, so the route handler receives the entity instead of the raw id:

```php
use Valkyrja\Orm\Data\EntityCast;

new EntityCast(type: Post::class, column: 'slug')
// The route parameter value loads through findBy(new Where(new Value('slug', $value)))
```

The middleware does not run on its own: no service provider publishes it, and the container does not autowire its three constructor arguments. The middleware needs three steps:

1. Bind it in a service provider.
2. Register that provider through a component provider in the config `providers` array.
3. Name the class in `routeMatchedMiddleware`. Use `HttpConfig` to apply the middleware to every route, or the `routeMatchedMiddleware` parameter of `#[Route]` to apply the middleware to one route.

```php
use Override;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;
use Valkyrja\Orm\Manager\Contract\ManagerContract;
use Valkyrja\Orm\Middleware\EntityRouteMatchedMiddleware;
use Valkyrja\View\Factory\Contract\ViewResponseFactoryContract;

class AppServiceProvider implements ServiceProviderContract
{
    #[Override]
    public function publishers(): array
    {
        return [
            EntityRouteMatchedMiddleware::class => [self::class, 'publishEntityRouteMatchedMiddleware'],
        ];
    }

    public static function publishEntityRouteMatchedMiddleware(ContainerContract $container): void
    {
        $container->setSingleton(
            EntityRouteMatchedMiddleware::class,
            new EntityRouteMatchedMiddleware(
                $container,
                $container->getSingleton(ManagerContract::class),
                $container->getSingleton(ViewResponseFactoryContract::class),
            ),
        );
    }
}
```

`ComponentProviderContract` declares six methods, and no base class supplies a default, so `AppComponentProvider` implements all six:

```php
use Override;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\Contract\ComponentProviderContract;

class AppComponentProvider implements ComponentProviderContract
{
    #[Override]
    public function getComponentProviders(ApplicationContract $app): array
    {
        return [];
    }

    #[Override]
    public function getContainerProviders(ApplicationContract $app): array
    {
        return [new AppServiceProvider()];
    }

    #[Override]
    public function getEventProviders(ApplicationContract $app): array
    {
        return [];
    }

    #[Override]
    public function getCliProviders(ApplicationContract $app): array
    {
        return [];
    }

    #[Override]
    public function getHttpProviders(ApplicationContract $app): array
    {
        return [];
    }

    #[Override]
    public function getGrpcProviders(ApplicationContract $app): array
    {
        return [];
    }
}
```

A service provider reaches the container only through a `ComponentProviderContract` in the config `providers` array. The [Application README](../Application/README.md#component-providers) documents the mechanism. The config below names three component providers:

- `HttpApplicationComponentProvider` — the framework HTTP components. The default `providers` value supplies it.
- `OrmComponentProvider` — the ORM services, including `ManagerContract`.
- `AppComponentProvider` — the class above, which returns `new AppServiceProvider()` from `getContainerProviders()`.

```php
use Valkyrja\Application\Data\HttpConfig;
use Valkyrja\Application\Provider\HttpApplicationComponentProvider;
use Valkyrja\Orm\Middleware\EntityRouteMatchedMiddleware;
use Valkyrja\Orm\Provider\OrmComponentProvider;

new HttpConfig(
    providers: [
        new HttpApplicationComponentProvider(),
        new OrmComponentProvider(),
        new AppComponentProvider(),
    ],
    routeMatchedMiddleware: [EntityRouteMatchedMiddleware::class],
);
```

## Exceptions

Every exception in the component implements `Valkyrja\Orm\Throwable\Contract\OrmThrowable`, so one catch covers the whole component:

```php
use Valkyrja\Orm\Throwable\Contract\OrmThrowable;

try {
    $repository->create($post);
} catch (OrmThrowable $exception) {
    // Every failure the component throws lands here
}
```

The ORM throwables cover the component's own checks. A driver-level failure arrives as a `PDOException` instead, because every adapter's default options set `PDO::ERRMODE_EXCEPTION` ([Configuration](#configuration)), and `PDOException` does not implement `OrmThrowable`. A duplicate key and a bad column are two driver-level failures. Catch `PDOException` separately when you handle driver failures.

The exceptions you meet in normal use:

| Exception                                 | Thrown when                                                              |
| :---------------------------------------- | :----------------------------------------------------------------------- |
| `OrmStatementPreparationFailureException` | `prepare()` fails to prepare the statement                               |
| `OrmExecuteException`                     | `query()` or a repository call fails to execute                          |
| `OrmFetchException`                       | `fetch()` or `fetchEntity()` finds no row                                |
| `OrmNoLastIdException`                    | `lastInsertId()` finds no id (`OrmNoPgsqlLastIdException` on PostgreSQL) |
| `OrmUnregisteredEntityException`          | the registry holds no metadata for a dated or soft delete entity         |
| `OrmUnexpectedIdValueException`           | `getIdValue()` finds a value that is not an int or a non-empty string    |
| `OrmArrayCastingException`                | an array cast meets a value that is not an array                         |
| `OrmInvalidColumnNumberException`         | `getColumnMeta()` receives a column number the result does not hold      |
| `OrmUnsupportedCountException`            | `getCount()` reads a count that is not an int and not a string           |
| `OrmInvalidMigrationFileException`        | a `SqlFileMigration` file path does not read                             |
| `OrmMigrationExecutionException`          | a `SqlFileMigration` statement fails                                     |

## Schema and Migrations

A migration implements `MigrationContract`, which declares `run()` and `rollback()`. Three abstract base classes cover the common shapes:

| Class                    | Description                                                              |
| :----------------------- | :----------------------------------------------------------------------- |
| `Migration`              | Receives the manager; override `run()` and `rollback()`                  |
| `TransactionalMigration` | Wraps each direction in a transaction, and rolls back on a `Throwable`   |
| `SqlFileMigration`       | Executes SQL files; provide the run file path and the rollback file path |

### Migration

`Migration` receives the manager as `$this->orm`. Override both directions:

```php
use Override;
use Valkyrja\Orm\Schema\Abstract\Migration;

final class CreatePostsTable extends Migration
{
    #[Override]
    public function run(): void
    {
        $this->orm->query('CREATE TABLE posts (id INT PRIMARY KEY, title VARCHAR(255))');
    }

    #[Override]
    public function rollback(): void
    {
        $this->orm->query('DROP TABLE posts');
    }
}
```

### TransactionalMigration

`TransactionalMigration` implements `run()` and `rollback()` for you. Each direction ensures a transaction, runs your migration, and commits. On a `Throwable` it rolls back, calls the matching failure hook, and rethrows. Override the protected migration methods instead:

```php
use Override;
use Valkyrja\Orm\Schema\Abstract\TransactionalMigration;

final class SeedStatuses extends TransactionalMigration
{
    #[Override]
    protected function runMigration(): void
    {
        $this->orm->query("INSERT INTO statuses (name) VALUES ('draft')");
    }

    #[Override]
    protected function rollbackMigration(): void
    {
        $this->orm->query("DELETE FROM statuses WHERE name = 'draft'");
    }
}
```

The hooks `runFailure(Throwable $exception)` and `rollbackFailure(Throwable $exception)` default to nothing. Override one to log or clean up before the rethrow.

### SqlFileMigration

`SqlFileMigration` extends `TransactionalMigration`, so each direction runs in a transaction. Provide the two file paths. The base class splits each file on `;` and executes each statement in order:

```php
use Override;
use Valkyrja\Orm\Schema\Abstract\SqlFileMigration;

final class CreateSchema extends SqlFileMigration
{
    #[Override]
    protected function getRunMigrationFilePath(): string
    {
        return __DIR__ . '/sql/create-schema.sql';
    }

    #[Override]
    protected function getRollbackMigrationFilePath(): string
    {
        return __DIR__ . '/sql/drop-schema.sql';
    }
}
```

An unreadable file throws an `OrmInvalidMigrationFileException`, and a failed statement throws an `OrmMigrationExecutionException`.

### Schema Contracts

The `Schema/Contract/` directory also declares a schema builder API. That API has five contracts: `SchemaContract`, `TableContract`, `ColumnContract`, `IndexContract`, and `ConstraintContract`. This component ships no implementation of those contracts.

## Configuration

The component reads four config contracts. Your application config class implements only the contracts for the connections that it uses. Each connection contract prefixes its properties with the connection name, so one class can implement several of them at once. When the application config singleton implements a contract, the service provider registers it; otherwise the provider registers the default config class.

### `OrmConfigContract`

| Property         | Default               | Description                               |
| :--------------- | :-------------------- | :---------------------------------------- |
| `defaultManager` | `MysqlManager::class` | Implementation bound to `ManagerContract` |

Select the manager by naming its class:

```php
use Valkyrja\Orm\Data\OrmConfig;
use Valkyrja\Orm\Manager\PgsqlManager;

new OrmConfig(defaultManager: PgsqlManager::class);
```

### `OrmMysqlConfigContract`

| Property        | Default            | Description         |
| :-------------- | :----------------- | :------------------ |
| `mysqlDb`       | `'valkyrja'`       | Database name       |
| `mysqlHost`     | `'127.0.0.1'`      | Host                |
| `mysqlPort`     | `3306`             | Port                |
| `mysqlUser`     | `'valkyrja'`       | Username            |
| `mysqlPassword` | `'mysql-password'` | Password            |
| `mysqlCharset`  | `'utf8mb4'`        | Character set       |
| `mysqlStrict`   | `null`             | Strict mode         |
| `mysqlEngine`   | `null`             | Storage engine      |
| `mysqlOptions`  | PDO defaults       | PDO attribute array |

`mysqlStrict` and `mysqlEngine` append `;strict=` and `;engine=` to the DSN when set, and a `null` leaves the flag out.

### `OrmPgsqlConfigContract`

| Property        | Default            | Description         |
| :-------------- | :----------------- | :------------------ |
| `pgsqlDb`       | `'valkyrja'`       | Database name       |
| `pgsqlHost`     | `'127.0.0.1'`      | Host                |
| `pgsqlPort`     | `6379`             | Port                |
| `pgsqlUser`     | `'valkyrja'`       | Username            |
| `pgsqlPassword` | `'pgsql-password'` | Password            |
| `pgsqlCharset`  | `'utf8'`           | Character encoding  |
| `pgsqlSchema`   | `'public'`         | Search path schema  |
| `pgsqlSslMode`  | `'prefer'`         | SSL mode            |
| `pgsqlOptions`  | PDO defaults       | PDO attribute array |

The provider applies `pgsqlSchema` with `SET search_path` after the connection opens. The default `pgsqlOptions` include `PDO::ATTR_PERSISTENT => true`, so PostgreSQL connections persist across requests unless you override the options.

### `OrmSqliteConfigContract`

| Property         | Default             | Description         |
| :--------------- | :------------------ | :------------------ |
| `sqliteDb`       | `'valkyrja'`        | Database name       |
| `sqliteHost`     | `'127.0.0.1'`       | Host                |
| `sqlitePort`     | `3306`              | Port                |
| `sqliteUser`     | `'valkyrja'`        | Username            |
| `sqlitePassword` | `'sqlite-password'` | Password            |
| `sqliteCharset`  | `'utf8'`            | Character encoding  |
| `sqliteOptions`  | PDO defaults        | PDO attribute array |

Every adapter's default options set `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, so a PDO-level failure throws instead of failing silently. The MySQL and SQLite defaults also disable emulated prepares, and every adapter disables stringified fetches, so an int column fetches as an int.

## Service Registration

The ORM service provider registers the following:

| Contract / Class                 | Description                              |
| :------------------------------- | :--------------------------------------- |
| `OrmConfigContract`              | Component config                         |
| `OrmMysqlConfigContract`         | MySQL connection config                  |
| `OrmPgsqlConfigContract`         | PostgreSQL connection config             |
| `OrmSqliteConfigContract`        | SQLite connection config                 |
| `ManagerContract`                | Active manager (default: `MysqlManager`) |
| `MysqlManager`                   | MySQL PDO manager                        |
| `PgsqlManager`                   | PostgreSQL PDO manager                   |
| `SqliteManager`                  | SQLite PDO manager                       |
| `NullManager`                    | No-op manager                            |
| `EntityMetadataRegistryContract` | Empty entity metadata registry           |
| `PDO`                            | PDO factory (bound with `bind()`)        |
| `Repository`                     | Repository factory (bound with `bind()`) |

Every entry is a singleton except `PDO` and `Repository`. The provider registers those two with `bind()`, so each resolution invokes the factory callable and returns a fresh instance with the provided arguments. The provider resolves the `PDO` binding when it constructs each manager, and a PDO manager resolves the `Repository` binding when it creates a repository.

The provider itself reaches the container through `Valkyrja\Orm\Provider\OrmComponentProvider`, which returns it from `getContainerProviders()`. The default config `providers` value does not include the ORM, so add `new OrmComponentProvider()` to the `providers` array to activate these registrations. The [Application README](../Application/README.md#component-providers) documents the mechanism.

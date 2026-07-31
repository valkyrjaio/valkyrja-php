<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Fixtures\Queue\Client;

use BadMethodCallException;
use Override;
use Valkyrja\Orm\Entity\Contract\EntityContract;
use Valkyrja\Orm\Manager\Contract\ManagerContract;
use Valkyrja\Orm\QueryBuilder\Factory\Contract\QueryBuilderFactoryContract;
use Valkyrja\Orm\QueryBuilder\Factory\SqlQueryBuilderFactory;
use Valkyrja\Orm\Repository\Contract\RepositoryContract;
use Valkyrja\Orm\Statement\Contract\StatementContract;

use function array_shift;
use function str_starts_with;

/**
 * A recording stand-in for an ORM manager.
 *
 * It hands out a statement fixture per prepared query and keeps them in order,
 * so a test can read back the SQL, the bound values, and the row counts.
 */
final class DatabaseManagerFixture implements ManagerContract
{
    /** @var DatabaseStatementFixture[] Every statement handed out, in order */
    public array $statements = [];

    /** @var array<int, array<string, scalar|null>> The rows the next select returns, in order */
    public array $rows = [];

    /** @var int[] The row count each write reports, in order */
    public array $rowCounts = [];

    /**
     * Get every statement whose query starts with the given keyword.
     *
     * @return DatabaseStatementFixture[]
     */
    public function getStatements(string $keyword): array
    {
        $matched = [];

        foreach ($this->statements as $statement) {
            if (str_starts_with($statement->query, $keyword)) {
                $matched[] = $statement;
            }
        }

        return $matched;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function prepare(string $query): StatementContract
    {
        $statement = new DatabaseStatementFixture(
            query: $query,
            row: array_shift($this->rows) ?? [],
            rowCount: array_shift($this->rowCounts) ?? 1,
        );

        $this->statements[] = $statement;

        return $statement;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function query(string $query): StatementContract
    {
        return $this->prepare($query);
    }

    /**
     * @inheritDoc
     *
     * @template T of EntityContract
     *
     * @param class-string<T> $entity The entity
     *
     * @return RepositoryContract<T>
     */
    #[Override]
    public function createRepository(string $entity): RepositoryContract
    {
        throw new BadMethodCallException('The queue adapter never builds a repository');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function createQueryBuilder(): QueryBuilderFactoryContract
    {
        return new SqlQueryBuilderFactory();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function beginTransaction(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function inTransaction(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function ensureTransaction(): void
    {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function commit(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function rollback(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function lastInsertId(string $table, string $idField): string
    {
        return '1';
    }
}

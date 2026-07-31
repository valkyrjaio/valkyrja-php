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

use Override;
use Valkyrja\Orm\Data\Value;
use Valkyrja\Orm\Statement\NullStatement;

/**
 * A recording stand-in for one prepared statement.
 *
 * It keeps the query it was prepared with and every value bound to it, so a
 * test can assert what the adapter asked the database to do.
 */
final class DatabaseStatementFixture extends NullStatement
{
    /** @var array<string, mixed> The values bound to this statement, by name */
    public array $bound = [];

    public bool $executed = false;

    /**
     * @param array<string, scalar|null> $row The row a fetch returns
     */
    public function __construct(
        public string $query = '',
        public array $row = [],
        public int $rowCount = 1,
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function bindValue(Value $value): bool
    {
        $this->bound[$value->name] = $value->value;

        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function execute(): bool
    {
        $this->executed = true;

        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function fetchAll(): array
    {
        return $this->row === []
            ? []
            : [$this->row];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}

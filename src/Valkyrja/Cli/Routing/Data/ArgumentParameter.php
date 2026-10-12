<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Cli\Routing\Data;

use Override;
use Valkyrja\Cli\Interaction\Argument\Contract\ArgumentContract;
use Valkyrja\Cli\Routing\Data\Abstract\Parameter;
use Valkyrja\Cli\Routing\Data\Contract\ArgumentParameterContract;
use Valkyrja\Cli\Routing\Enum\ArgumentMode;
use Valkyrja\Cli\Routing\Enum\ArgumentValueMode;
use Valkyrja\Cli\Routing\Throwable\Exception\CliRoutingArgumentValuesValidationException;
use Valkyrja\Type\Data\Cast;

use function count;

class ArgumentParameter extends Parameter implements ArgumentParameterContract
{
    /**
     * @param non-empty-string   $name        The name
     * @param non-empty-string   $description The description
     * @param ArgumentContract[] $arguments   The arguments
     */
    public function __construct(
        string $name,
        string $description,
        Cast|null $cast = null,
        protected ArgumentMode $mode = ArgumentMode::OPTIONAL,
        protected ArgumentValueMode $valueMode = ArgumentValueMode::DEFAULT,
        protected array $arguments = [],
    ) {
        parent::__construct(
            name: $name,
            description: $description,
            cast: $cast
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMode(): ArgumentMode
    {
        return $this->mode;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withMode(ArgumentMode $mode): static
    {
        $new = clone $this;

        $new->mode = $mode;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getValueMode(): ArgumentValueMode
    {
        return $this->valueMode;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withValueMode(ArgumentValueMode $valueMode): static
    {
        $new = clone $this;

        $new->valueMode = $valueMode;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withArguments(ArgumentContract ...$arguments): static
    {
        $new = clone $this;

        $new->arguments = $arguments;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAddedArguments(ArgumentContract ...$arguments): static
    {
        $new = clone $this;

        foreach ($arguments as $argument) {
            $new->arguments[] = $argument;
        }

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getValues(): array
    {
        return $this->getValuesOfParameters($this->arguments);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isProvided(): bool
    {
        return isset($this->arguments[0]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getFirstValue(): string
    {
        $firstItem = $this->arguments[0] ?? null;

        if ($firstItem === null) {
            return '';
        }

        return $firstItem->getValue();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function areValuesValid(): bool
    {
        $valid = true;

        if ($this->mode === ArgumentMode::REQUIRED) {
            $valid = $this->arguments !== [];
        }

        if ($this->valueMode === ArgumentValueMode::DEFAULT) {
            $valid = $valid && count($this->arguments) <= 1;
        }

        return $valid;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function validateValues(): static
    {
        if (! $this->areValuesValid()) {
            throw new CliRoutingArgumentValuesValidationException("$this->name is invalid");
        }

        return $this;
    }
}

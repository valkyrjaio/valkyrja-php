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
use Valkyrja\Cli\Interaction\Option\Contract\OptionContract;
use Valkyrja\Cli\Routing\Data\Abstract\Parameter;
use Valkyrja\Cli\Routing\Data\Contract\OptionParameterContract;
use Valkyrja\Cli\Routing\Enum\OptionMode;
use Valkyrja\Cli\Routing\Enum\OptionValueMode;
use Valkyrja\Cli\Routing\Throwable\Exception\CliRoutingInvalidOptionWithValueException;
use Valkyrja\Cli\Routing\Throwable\Exception\CliRoutingOptionValuesValidationException;
use Valkyrja\Type\Data\Cast;

use function count;
use function in_array;

class OptionParameter extends Parameter implements OptionParameterContract
{
    /**
     * @param non-empty-string   $name        The names
     * @param non-empty-string   $description The description
     * @param non-empty-string[] $shortNames  The short names
     * @param non-empty-string[] $validValues The valid values
     * @param OptionContract[]   $options     The options
     */
    public function __construct(
        string $name,
        string $description,
        protected string $valueDisplayName = '',
        Cast|null $cast = null,
        protected string $defaultValue = '',
        protected array $shortNames = [],
        protected array $validValues = [],
        protected array $options = [],
        protected OptionMode $mode = OptionMode::OPTIONAL,
        protected OptionValueMode $valueMode = OptionValueMode::DEFAULT,
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
    public function getShortNames(): array
    {
        return $this->shortNames;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withShortNames(string ...$shortNames): static
    {
        $new = clone $this;

        $new->shortNames = $shortNames;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAddedShortNames(string ...$shortNames): static
    {
        $new = clone $this;

        foreach ($shortNames as $shortName) {
            if (! in_array($shortName, $new->shortNames, true)) {
                $new->shortNames[] = $shortName;
            }
        }

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMode(): OptionMode
    {
        return $this->mode;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withMode(OptionMode $mode): static
    {
        $new = clone $this;

        $new->mode = $mode;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getValueMode(): OptionValueMode
    {
        return $this->valueMode;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withValueMode(OptionValueMode $valueMode): static
    {
        $new = clone $this;

        $new->valueMode = $valueMode;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasValueDisplayName(): bool
    {
        return $this->valueDisplayName !== '';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getValueDisplayName(): string
    {
        return $this->valueDisplayName;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withValueDisplayName(string $valueName): static
    {
        $new = clone $this;

        $new->valueDisplayName = $valueName;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getValidValues(): array
    {
        return $this->validValues;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withValidValues(string ...$validValues): static
    {
        $new = clone $this;

        $new->validValues = $validValues;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAddedValidValues(string ...$validValues): static
    {
        $new = clone $this;

        foreach ($validValues as $validValue) {
            if (! in_array($validValue, $new->validValues, true)) {
                $new->validValues[] = $validValue;
            }
        }

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasDefaultValue(): bool
    {
        return $this->defaultValue !== '';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getDefaultValue(): string
    {
        return $this->defaultValue;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withDefaultValue(string $defaultValue): static
    {
        $new = clone $this;

        $new->defaultValue = $defaultValue;

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withOptions(OptionContract ...$options): static
    {
        $new = clone $this;

        $new->options = [];

        foreach ($options as $option) {
            if ($this->valueMode === OptionValueMode::NONE && $option->hasValue()) {
                throw new CliRoutingInvalidOptionWithValueException("$this->name should have no value");
            }

            $new->options[] = $option;
        }

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withAddedOptions(OptionContract ...$options): static
    {
        $new = clone $this;

        foreach ($options as $option) {
            if ($this->valueMode === OptionValueMode::NONE && $option->hasValue()) {
                throw new CliRoutingInvalidOptionWithValueException("$this->name should have no value");
            }

            $new->options[] = $option;
        }

        return $new;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getValues(): array
    {
        return $this->getValuesOfParameters($this->options);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isProvided(): bool
    {
        return isset($this->options[0]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getFirstValue(): string
    {
        $firstItem = $this->options[0] ?? null;

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

        if ($this->mode === OptionMode::REQUIRED) {
            $valid = $this->options !== [];
        }

        if ($this->valueMode === OptionValueMode::DEFAULT) {
            $valid = $valid && count($this->options) <= 1;
        }

        if ($this->validValues !== []) {
            foreach ($this->options as $option) {
                if (! in_array($option->getValue(), $this->validValues, true)) {
                    return false;
                }
            }
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
            throw new CliRoutingOptionValuesValidationException("$this->name is invalid");
        }

        return $this;
    }
}

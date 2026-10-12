<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Http\Routing\Matcher;

use Override;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Http\Message\Enum\RequestMethod;
use Valkyrja\Http\Routing\Collection\Contract\RouteCollectionContract;
use Valkyrja\Http\Routing\Data\Contract\DynamicRouteContract;
use Valkyrja\Http\Routing\Data\Contract\ParameterContract;
use Valkyrja\Http\Routing\Data\Contract\RouteContract;
use Valkyrja\Http\Routing\Matcher\Contract\MatcherContract;
use Valkyrja\Http\Routing\Throwable\Exception\HttpRoutingInvalidRouteParameterException;
use Valkyrja\Http\Routing\Throwable\Exception\HttpRoutingInvalidRoutePathException;
use Valkyrja\Type\Constant\CastArgument;
use Valkyrja\Type\Data\Cast;

use function array_shift;
use function preg_match;
use function trim;

class Matcher implements MatcherContract
{
    public function __construct(
        protected RouteCollectionContract $collection,
        protected ContainerContract $container
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws HttpRoutingInvalidRoutePathException
     * @throws HttpRoutingInvalidRouteParameterException
     */
    #[Override]
    public function match(string $path, RequestMethod $requestMethod): RouteContract|null
    {
        $path  = '/' . trim($path, '/');
        $route = $this->matchStatic($path, $requestMethod);

        return $route
            ?? $this->matchDynamic($path, $requestMethod);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function matchStatic(string $path, RequestMethod $requestMethod): RouteContract|null
    {
        if ($this->collection->hasPath($path, $requestMethod)) {
            return clone $this->collection->getByPath($path, $requestMethod);
        }

        return null;
    }

    /**
     * @inheritDoc
     *
     * @throws HttpRoutingInvalidRoutePathException
     * @throws HttpRoutingInvalidRouteParameterException
     */
    #[Override]
    public function matchDynamic(string $path, RequestMethod $requestMethod): RouteContract|null
    {
        $regexes = $this->collection->getRegexes($requestMethod);

        // Attempt to find a match using dynamic routes that are set
        foreach ($regexes as $regex => $name) {
            // If the preg match is successful, we've found our route!
            if ($regex !== '' && preg_match($regex, $path, $matches)) {
                /** @var array<int|non-empty-string, string> $matches */
                return $this->processArguments(
                    $this->collection->getByRegex($regex, $requestMethod),
                    $matches
                );
            }
        }

        return null;
    }

    /**
     * Process matches for a dynamic route.
     *
     * @param array<int|non-empty-string, string> $matches The regex matches
     *
     * @throws HttpRoutingInvalidRoutePathException
     */
    protected function processArguments(DynamicRouteContract $route, array $matches): DynamicRouteContract
    {
        // The first match is the path itself, the rest could be empty.
        array_shift($matches);

        // Get the parameters
        $parameters = $route->getParameters();

        if ($parameters === []) {
            throw new HttpRoutingInvalidRoutePathException('Route parameters must not be empty');
        }

        $parametersWithValues = [];

        // Iterate through the matches
        foreach ($parameters as $parameter) {
            $name  = $parameter->getName();
            $match = $matches[$name]
                ??= $parameter->getDefault();

            if ($match === null) {
                $parametersWithValues[] = $parameter;

                continue;
            }

            $value = $this->checkAndCastMatchValue(
                parameter: $parameter,
                match: $match
            );

            $parametersWithValues[] = $parameter->withValue($value);
        }

        return $route->withParameters(...$parametersWithValues);
    }

    /**
     * @param ParameterContract                  $parameter The parameter
     * @param array<scalar|object>|scalar|object $match     The match
     *
     * @return array<scalar|object>|scalar|object|null
     */
    protected function checkAndCastMatchValue(
        ParameterContract $parameter,
        array|string|int|bool|float|object $match
    ): array|string|int|bool|float|object|null {
        if ($parameter->hasCast()) {
            return $this->castMatchValue(
                cast: $parameter->getCast(),
                match: $match
            );
        }

        return $match;
    }

    /**
     * Get a match value for the given cast type.
     *
     * @param Cast                               $cast  The cast
     * @param array<scalar|object>|scalar|object $match The match value
     *
     * @return array<scalar|object>|scalar|object|null
     */
    protected function castMatchValue(
        Cast $cast,
        array|string|int|bool|float|object $match
    ): array|string|int|bool|float|object|null {
        $type = $this->container->getService($cast->type, [CastArgument::VALUE => $match]);

        if ($cast->convert) {
            /** @var array<scalar|object>|scalar|object|null $type */
            $type = $type->asValue();
        }

        return $type;
    }
}

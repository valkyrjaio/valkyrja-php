<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Valkyrja\Tests\Unit\Http\Routing\Matcher;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Valkyrja\Container\Manager\Container;
use Valkyrja\Container\Throwable\Exception\ContainerInvalidReferenceException;
use Valkyrja\Http\Message\Enum\RequestMethod;
use Valkyrja\Http\Routing\Collection\RouteCollection;
use Valkyrja\Http\Routing\Constant\Regex;
use Valkyrja\Http\Routing\Data\Contract\DynamicRouteContract;
use Valkyrja\Http\Routing\Data\Contract\RouteContract;
use Valkyrja\Http\Routing\Data\DynamicRoute;
use Valkyrja\Http\Routing\Data\Parameter;
use Valkyrja\Http\Routing\Data\Route;
use Valkyrja\Http\Routing\Matcher\Matcher;
use Valkyrja\Http\Routing\Processor\Processor;
use Valkyrja\Http\Routing\Throwable\Exception\HttpRoutingInvalidRoutePathException;
use Valkyrja\Tests\Fixtures\Http\Routing\Provider\RouteProviderFixture;
use Valkyrja\Tests\Unit\Abstract\TestCase;
use Valkyrja\Type\Data\Cast;
use Valkyrja\Type\Enum\CastType;
use Valkyrja\Type\Int\IntT;
use Valkyrja\Type\Provider\TypeServiceProvider;

/**
 * Test the Matcher service.
 */
final class MatcherTest extends TestCase
{
    protected const string STATIC_PATH = '/';
    protected const string STATIC_NAME = 'static';

    protected const string DYNAMIC_PATH       = '/dynamic';
    protected const string DYNAMIC_ROUTE_NAME = 'dynamic';
    protected const string DYNAMIC_REGEX      = '/^\/(?<dynamic>[a-zA-Z]+)$/';

    protected const string OPTIONAL_DYNAMIC_PATH       = '/optional';
    protected const string OPTIONAL_DYNAMIC_ROUTE_NAME = 'optional-dynamic';
    protected const string OPTIONAL_DYNAMIC_REGEX      = '/^\/optional(?:\/)?(?<dynamic>[a-zA-Z]+)?$/';

    protected const string OPTIONAL_NULL_DYNAMIC_PATH       = '/optional-null';
    protected const string OPTIONAL_NULL_DYNAMIC_ROUTE_NAME = 'optional-null-dynamic';
    protected const string OPTIONAL_NULL_DYNAMIC_REGEX      = '/^\/optional-null(?:\/)?(?<dynamic>[a-zA-Z]+)?$/';

    protected const string CAST_DYNAMIC_PATH       = '/cast/2/235';
    protected const string CAST_DYNAMIC_ROUTE_NAME = 'cast-dynamic';
    protected const string CAST_DYNAMIC_REGEX      = '/^\/cast\/(?<dynamic1>\d+)\/(?<dynamic2>\d+)$/';

    protected const string INVALID_DYNAMIC_PATH       = '/invalid/dynamic';
    protected const string INVALID_DYNAMIC_ROUTE_NAME = 'invalid-dynamic';
    protected const string INVALID_DYNAMIC_REGEX      = '/^\/invalid\/(?<invalid>[a-zA-Z]+)$/';

    protected Matcher $matcher;

    protected RouteCollection $collection;

    /**
     * @return array<non-empty-string, array{non-empty-string, non-empty-string, non-empty-string|null}>
     */
    public static function matchingTypeProvider(): array
    {
        return [
            'num'                  => [Regex::NUM, '123', 'abc'],
            'alpha'                => [Regex::ALPHA, 'abc', 'abc1'],
            'alpha lowercase'      => [Regex::ALPHA_LOWERCASE, 'abc', 'Abc'],
            'alpha uppercase'      => [Regex::ALPHA_UPPERCASE, 'ABC', 'abc'],
            'alpha num'            => [Regex::ALPHA_NUM, 'abc123', 'abc-1'],
            'alpha num underscore' => [Regex::ALPHA_NUM_UNDERSCORE, 'abc_123', 'abc-1'],
            'slug'                 => [Regex::SLUG, 'My-slug-1', 'has_underscore'],
            'any'                  => [Regex::ANY, 'anything-1.x', null],
            'uuid'                 => [Regex::UUID, '66a39476-b630-4b95-8bfb-355f3d4843c5', 'not-a-uuid'],
            'uuid v4'              => [Regex::UUID_V4, '78cbd961-d07b-4ef9-89a7-b4ec9d1a70f0', '11111111-1111-1111-1111-111111111111'],
            'ulid'                 => [Regex::ULID, '01KYGBV64MKWPK63CC1QH0VGF7', 'notaulid'],
            'vlid v4'              => [Regex::VLID_V4, '04YHJMN6F5XHM497ZW', 'notavlid'],
        ];
    }

    /**
     * Get a container that holds every type that a cast type names.
     */
    protected static function containerWithTypes(): Container
    {
        $container = new Container();

        foreach (new TypeServiceProvider()->publishers() as $publisher) {
            $publisher($container);
        }

        return $container;
    }

    #[Override]
    protected function setUp(): void
    {
        $route = new Route(
            path: self::STATIC_PATH,
            name: self::STATIC_NAME,
            handler: [RouteProviderFixture::class, 'handler']
        );

        $dynamicRoute = new DynamicRoute(
            path: '/{dynamic}',
            name: self::DYNAMIC_ROUTE_NAME,
            regex: self::DYNAMIC_REGEX,
            parameters: [
                new Parameter(
                    name: self::DYNAMIC_ROUTE_NAME,
                    regex: Regex::ALPHA
                ),
            ],
            handler: [RouteProviderFixture::class, 'handler']
        );

        $optionalDynamicRoute = new DynamicRoute(
            path: '/{optional-dynamic?}',
            name: self::OPTIONAL_DYNAMIC_ROUTE_NAME,
            regex: self::OPTIONAL_DYNAMIC_REGEX,
            parameters: [
                new Parameter(
                    name: self::DYNAMIC_ROUTE_NAME,
                    regex: Regex::ALPHA,
                    isOptional: true,
                    default: 'default'
                ),
            ],
            handler: [RouteProviderFixture::class, 'handler']
        );

        $optionalDynamicRouteNullDefault = new DynamicRoute(
            path: '/{optional-null-dynamic?}',
            name: self::OPTIONAL_NULL_DYNAMIC_ROUTE_NAME,
            regex: self::OPTIONAL_NULL_DYNAMIC_REGEX,
            parameters: [
                new Parameter(
                    name: self::DYNAMIC_ROUTE_NAME,
                    regex: Regex::ALPHA,
                    isOptional: true
                ),
            ],
            handler: [RouteProviderFixture::class, 'handler']
        );

        $castDynamicRoute = new DynamicRoute(
            path: '/{dynamic1}/{dynamic2}',
            name: self::CAST_DYNAMIC_ROUTE_NAME,
            regex: self::CAST_DYNAMIC_REGEX,
            parameters: [
                new Parameter(
                    name: self::DYNAMIC_ROUTE_NAME . '1',
                    regex: Regex::NUM,
                    cast: new Cast(
                        type: CastType::int,
                        convert: true,
                    ),
                ),
                new Parameter(
                    name: self::DYNAMIC_ROUTE_NAME . '2',
                    regex: Regex::NUM,
                    cast: new Cast(
                        type: CastType::int,
                        convert: false,
                    ),
                ),
            ],
            handler: [RouteProviderFixture::class, 'handler']
        );

        $invalidDynamicRoute = new DynamicRoute(
            path: '/invalid/{invalid}',
            name: self::INVALID_DYNAMIC_ROUTE_NAME,
            regex: self::INVALID_DYNAMIC_REGEX,
            parameters: [],
            handler: [RouteProviderFixture::class, 'handler']
        );

        $collection = new RouteCollection();
        $collection->add($route);
        $collection->add($castDynamicRoute);
        $collection->add($optionalDynamicRoute);
        $collection->add($optionalDynamicRouteNullDefault);
        $collection->add($invalidDynamicRoute);
        $collection->add($dynamicRoute);

        $this->collection = $collection;
        $this->matcher    = new Matcher(
            collection: $collection,
            container: self::containerWithTypes()
        );
    }

    public function testNoMatch(): void
    {
        $path        = self::STATIC_PATH;
        $dynamicPath = self::DYNAMIC_PATH;

        $matcher = new Matcher(collection: new RouteCollection(), container: new Container());

        self::assertNull($matcher->match($path, RequestMethod::ANY));
        self::assertNull($matcher->match($dynamicPath, RequestMethod::ANY));
        self::assertNull($matcher->matchStatic($path, RequestMethod::ANY));
        self::assertNull($matcher->matchStatic($dynamicPath, RequestMethod::ANY));
        self::assertNull($matcher->matchDynamic($path, RequestMethod::ANY));
        self::assertNull($matcher->matchDynamic($dynamicPath, RequestMethod::ANY));
    }

    public function testStaticMatch(): void
    {
        $path        = self::STATIC_PATH;
        $dynamicPath = self::DYNAMIC_PATH;

        $matcher = $this->matcher;

        $route = $matcher->match($path, RequestMethod::ANY);

        self::assertNotNull($route);
        self::assertNotNull($matcher->matchStatic($path, RequestMethod::ANY));
        self::assertNull($matcher->matchStatic($dynamicPath, RequestMethod::ANY));

        self::assertNotInstanceOf(DynamicRouteContract::class, $route);
    }

    public function testDynamicMatch(): void
    {
        $path        = self::STATIC_PATH;
        $dynamicPath = self::DYNAMIC_PATH;

        $matcher = $this->matcher;

        $route = $matcher->match($dynamicPath, RequestMethod::ANY);

        self::assertNotNull($route);
        self::assertNull($matcher->matchDynamic($path, RequestMethod::ANY));
        self::assertNotNull($matcher->matchDynamic($dynamicPath, RequestMethod::ANY));

        self::assertInstanceOf(DynamicRouteContract::class, $route);
        self::assertTrue($route->hasParameter('dynamic'));
        self::assertIsString($route->getParameter('dynamic')->getValue());
        self::assertSame('dynamic', $route->getParameter('dynamic')->getValue());
    }

    public function testOptionalDynamicMatch(): void
    {
        $dynamicPath = self::OPTIONAL_DYNAMIC_PATH;

        $matcher = $this->matcher;

        $route = $matcher->match($dynamicPath, RequestMethod::ANY);

        self::assertNotNull($route);
        self::assertNotNull($matcher->matchDynamic($dynamicPath, RequestMethod::ANY));

        self::assertInstanceOf(DynamicRouteContract::class, $route);
        self::assertTrue($route->hasParameter('dynamic'));
        self::assertIsString($route->getParameter('dynamic')->getValue());
        self::assertSame('default', $route->getParameter('dynamic')->getValue());

        $route2 = $matcher->match($dynamicPath . '/optionalvalue', RequestMethod::ANY);

        self::assertNotNull($route2);
        self::assertNotNull($matcher->matchDynamic($dynamicPath . '/optionalvalue', RequestMethod::ANY));

        self::assertInstanceOf(DynamicRouteContract::class, $route2);
        self::assertTrue($route2->hasParameter('dynamic'));
        self::assertIsString($route2->getParameter('dynamic')->getValue());
        self::assertSame('optionalvalue', $route2->getParameter('dynamic')->getValue());
    }

    public function testOptionalNullDefaultDynamicMatch(): void
    {
        $dynamicPath = self::OPTIONAL_NULL_DYNAMIC_PATH;

        $matcher = $this->matcher;

        $route = $matcher->match($dynamicPath, RequestMethod::ANY);

        self::assertNotNull($route);
        self::assertNotNull($matcher->matchDynamic($dynamicPath, RequestMethod::ANY));

        self::assertInstanceOf(DynamicRouteContract::class, $route);
        self::assertTrue($route->hasParameter('dynamic'));
        self::assertNull($route->getParameter('dynamic')->getValue());

        $route2 = $matcher->match($dynamicPath . '/optionalvalue', RequestMethod::ANY);

        self::assertNotNull($route2);
        self::assertNotNull($matcher->matchDynamic($dynamicPath . '/optionalvalue', RequestMethod::ANY));

        self::assertInstanceOf(DynamicRouteContract::class, $route2);
        self::assertTrue($route2->hasParameter('dynamic'));
        self::assertIsString($route2->getParameter('dynamic')->getValue());
        self::assertSame('optionalvalue', $route2->getParameter('dynamic')->getValue());
    }

    public function testCastDynamicMatch(): void
    {
        $dynamicPath = self::CAST_DYNAMIC_PATH;

        $matcher = $this->matcher;

        $route = $matcher->match($dynamicPath, RequestMethod::ANY);

        self::assertNotNull($route);
        self::assertNotNull($matcher->matchDynamic($dynamicPath, RequestMethod::ANY));

        self::assertInstanceOf(DynamicRouteContract::class, $route);
        self::assertTrue($route->hasParameter('dynamic1'));
        self::assertTrue($route->hasParameter('dynamic2'));
        self::assertSame(2, $route->getParameter('dynamic1')->getValue());
        self::assertInstanceOf(IntT::class, $route->getParameter('dynamic2')->getValue());
        self::assertSame(235, $route->getParameter('dynamic2')->getValue()->asValue());
    }

    public function testCastTypeThatNoServiceHoldsRaises(): void
    {
        $matcher = new Matcher(collection: $this->collection, container: new Container());

        $this->expectException(ContainerInvalidReferenceException::class);

        $matcher->match(self::CAST_DYNAMIC_PATH, RequestMethod::ANY);
    }

    public function testInvalidDynamicMatch(): void
    {
        $this->expectException(HttpRoutingInvalidRoutePathException::class);

        $dynamicPath = self::INVALID_DYNAMIC_PATH;

        $matcher = $this->matcher;

        $matcher->match($dynamicPath, RequestMethod::ANY);
    }

    /**
     * Each parameter regex type matches a valid value (and binds it) and rejects an invalid one.
     *
     * @param non-empty-string      $typeRegex
     * @param non-empty-string      $validValue
     * @param non-empty-string|null $invalidValue null when the type matches anything (ANY)
     */
    #[DataProvider('matchingTypeProvider')]
    public function testDynamicRouteTypeMatchesValidAndRejectsInvalid(string $typeRegex, string $validValue, string|null $invalidValue): void
    {
        $matcher = $this->matcherFor(
            $this->processedDynamicRoute('/{value}', 'typed', [new Parameter(name: 'value', regex: $typeRegex)])
        );

        $matched = $matcher->match("/$validValue", RequestMethod::ANY);

        self::assertInstanceOf(DynamicRouteContract::class, $matched);
        self::assertSame($validValue, $matched->getParameter('value')->getValue());

        if ($invalidValue !== null) {
            self::assertNull($matcher->match("/$invalidValue", RequestMethod::ANY));
        }
    }

    /**
     * A dynamic route restricted to GET is matched under GET and ANY, but not POST.
     */
    public function testRequestMethodFilteringForDynamicRoute(): void
    {
        $matcher = $this->matcherFor(
            $this->processedDynamicRoute(
                '/{name}',
                'get-only-dynamic',
                [new Parameter(name: 'name', regex: Regex::ALPHA)],
                [RequestMethod::GET]
            )
        );

        self::assertInstanceOf(DynamicRouteContract::class, $matcher->match('/foo', RequestMethod::GET));
        self::assertInstanceOf(DynamicRouteContract::class, $matcher->match('/foo', RequestMethod::ANY));
        self::assertNull($matcher->match('/foo', RequestMethod::POST));
    }

    /**
     * A static route restricted to GET is matched under GET but not POST.
     */
    public function testRequestMethodFilteringForStaticRoute(): void
    {
        $matcher = $this->matcherFor(
            new Route(
                path: '/only-get',
                name: 'get-only-static',
                handler: [RouteProviderFixture::class, 'handler'],
                requestMethods: [RequestMethod::GET]
            )
        );

        $matched = $matcher->match('/only-get', RequestMethod::GET);

        self::assertNotNull($matched);
        self::assertNotInstanceOf(DynamicRouteContract::class, $matched);
        self::assertNull($matcher->match('/only-get', RequestMethod::POST));
    }

    /**
     * A trailing slash on the request path is normalized before matching.
     */
    public function testTrailingSlashIsNormalizedForMatching(): void
    {
        $matcher = $this->matcherFor(
            new Route(
                path: '/foo',
                name: 'foo-static',
                handler: [RouteProviderFixture::class, 'handler'],
                requestMethods: [RequestMethod::ANY]
            ),
            $this->processedDynamicRoute('/bar/{x}', 'bar-dynamic', [new Parameter(name: 'x', regex: Regex::ALPHA)])
        );

        self::assertNotNull($matcher->match('/foo/', RequestMethod::ANY));
        self::assertInstanceOf(DynamicRouteContract::class, $matcher->match('/bar/abc/', RequestMethod::ANY));
    }

    /**
     * A static route wins over a dynamic route that would also match the same path.
     */
    public function testStaticRouteTakesPrecedenceOverDynamic(): void
    {
        $matcher = $this->matcherFor(
            new Route(
                path: '/users',
                name: 'static-users',
                handler: [RouteProviderFixture::class, 'handler'],
                requestMethods: [RequestMethod::ANY]
            ),
            $this->processedDynamicRoute('/{name}', 'any-name', [new Parameter(name: 'name', regex: Regex::ALPHA)])
        );

        $static = $matcher->match('/users', RequestMethod::ANY);

        self::assertNotNull($static);
        self::assertNotInstanceOf(DynamicRouteContract::class, $static);

        // A path with no static route still falls through to the dynamic route.
        self::assertInstanceOf(DynamicRouteContract::class, $matcher->match('/other', RequestMethod::ANY));
    }

    /**
     * Multiple parameters are each extracted and bound to their own values.
     */
    public function testMultipleParametersAreExtracted(): void
    {
        $matcher = $this->matcherFor(
            $this->processedDynamicRoute(
                '/a/{x}/b/{y}',
                'multi',
                [
                    new Parameter(name: 'x', regex: Regex::NUM),
                    new Parameter(name: 'y', regex: Regex::ALPHA),
                ]
            )
        );

        $matched = $matcher->match('/a/12/b/two', RequestMethod::ANY);

        self::assertInstanceOf(DynamicRouteContract::class, $matched);
        self::assertSame('12', $matched->getParameter('x')->getValue());
        self::assertSame('two', $matched->getParameter('y')->getValue());
    }

    /**
     * A non-capturing parameter still matches but is not bound to a value.
     */
    public function testNonCaptureParameterIsNotBound(): void
    {
        $matcher = $this->matcherFor(
            $this->processedDynamicRoute(
                '/{nc}',
                'non-capture',
                [new Parameter(name: 'nc', regex: Regex::ALPHA, shouldCapture: false)]
            )
        );

        $matched = $matcher->match('/abc', RequestMethod::ANY);

        self::assertInstanceOf(DynamicRouteContract::class, $matched);
        self::assertNull($matched->getParameter('nc')->getValue());
    }

    /**
     * Build a dynamic route with its regex produced by the Processor (the real pipeline).
     *
     * @param non-empty-string $path
     * @param non-empty-string $name
     * @param array<Parameter> $parameters
     * @param RequestMethod[]  $requestMethods
     *
     * @throws HttpRoutingInvalidRoutePathException
     */
    private function processedDynamicRoute(
        string $path,
        string $name,
        array $parameters,
        array $requestMethods = [RequestMethod::ANY]
    ): DynamicRouteContract {
        $route = new DynamicRoute(
            path: $path,
            name: $name,
            regex: '',
            parameters: $parameters,
            handler: [RouteProviderFixture::class, 'handler'],
            requestMethods: $requestMethods
        );

        $processed = new Processor()->route($route);

        self::assertInstanceOf(DynamicRouteContract::class, $processed);

        return $processed;
    }

    /**
     * Build a matcher backed by a collection of the given routes.
     */
    private function matcherFor(RouteContract ...$routes): Matcher
    {
        $collection = new RouteCollection();

        foreach ($routes as $route) {
            $collection->add($route);
        }

        return new Matcher(collection: $collection, container: new Container());
    }
}

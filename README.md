<p align="center"><a href="https://valkyrja.io" target="_blank">
    <img src="https://raw.githubusercontent.com/valkyrjaio/art/refs/heads/26.x/long-banner/orange/php.png" width="100%">
</a></p>

# Valkyrja

[Valkyrja][Valkyrja url] is a PHP framework for web and console applications.

Valkyrja (pronounced "Valk-ear-ya") is the Old Norse spelling for Valkyrie, a
mythical creature that would guide warriors to Valhalla (the afterlife and a
better place) after death. In a similar sense, the Valkyrja framework guides
your application to be in a better state. Fast, light, and robust, Valkyrja
does the heavy lifting so you can focus on your application.

<p>
    <a href="https://packagist.org/packages/valkyrja/valkyrja"><img src="https://poser.pugx.org/valkyrja/valkyrja/v" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/valkyrja/valkyrja"><img src="https://poser.pugx.org/valkyrja/valkyrja/require/php" alt="PHP Version Require"></a>
    <a href="https://packagist.org/packages/valkyrja/valkyrja"><img src="https://poser.pugx.org/valkyrja/valkyrja/license" alt="License"></a>
    <a href="https://github.com/valkyrjaio/valkyrja-php/actions/workflows/ci.yml?query=branch%3A26.x"><img src="https://github.com/valkyrjaio/valkyrja-php/actions/workflows/ci.yml/badge.svg?branch=26.x" alt="CI Status"></a>
    <a href="https://scrutinizer-ci.com/g/valkyrjaio/valkyrja-php/?branch=26.x"><img src="https://scrutinizer-ci.com/g/valkyrjaio/valkyrja-php/badges/quality-score.png?b=26.x" alt="Scrutinizer"></a>
    <a href="https://coveralls.io/github/valkyrjaio/valkyrja-php?branch=26.x"><img src="https://coveralls.io/repos/github/valkyrjaio/valkyrja-php/badge.svg?branch=26.x" alt="Coverage Status" /></a>
    <a href="https://shepherd.dev/github/valkyrjaio/valkyrja-php"><img src="https://shepherd.dev/github/valkyrjaio/valkyrja-php/coverage.svg" alt="Psalm Shepherd" /></a>
    <a href="https://sonarcloud.io/summary/new_code?id=valkyrjaio_valkyrja"><img src="https://sonarcloud.io/api/project_badges/measure?project=valkyrjaio_valkyrja&metric=sqale_rating" alt="Maintainability Rating" /></a>
</p>

What's Included
---------------

- **HTTP and CLI kernels** — unified application architecture serving both
  web requests and command-line invocations
- **Dependency injection container** — deferred bindings, contextual
  resolution, and compiled configuration for fast resolution at runtime
- **Routing** — expressive route definitions with middleware, constraints,
  and reverse resolution
- **Event dispatcher** — decoupled event handling with typed listeners
- **ORM and data layer** — database access with repository patterns and
  migrations
- **Persistent worker support** — first-class integration with OpenSwoole,
  FrankenPHP, and RoadRunner for production-grade performance

Installation
------------

### Start a New Application

The fastest way to start a new Valkyrja application is with the starter
template or the Sindri build tool:

- Use the [`valkyrja-starter-app-php`][starter url] GitHub template ("Use
  this template" button on the repository page)
- Or run `composer create-project valkyrja/application your-project`
- Or use [Sindri][sindri url]:
  `composer create-project valkyrja/sindri your-project`

### Add to an Existing Project

To require the framework as a dependency:

```
composer require valkyrja/valkyrja
```

Documentation
-------------

Full [documentation][docs url] is baked into the repository so you can
browse it offline. Major areas include:

- [HTTP][http docs url] — routing, controllers, middleware, requests, responses
- [CLI][cli docs url] — commands, input, output, and dispatch
- [Container][container docs url] — dependency injection bindings and resolution
- [Events][events docs url] — event dispatch and listeners
- [ORM][orm docs url] — database access, repositories, and migrations

Ecosystem
---------

Valkyrja is the core framework. Surrounding it is an ecosystem of related
projects in the Valkyrjaio organization:

- [**Sindri**][sindri url] — build tool and application creator
- [**Starter (App)**][starter url] — starter template for new applications
- **Worker runtimes** — OpenSwoole, FrankenPHP, and RoadRunner worker entry
  points are built in, under `Valkyrja\Application\Entry`
- [**Docker**][docker url] — ready-made Docker configurations

See the [Valkyrjaio organization page][org url] for the complete listing.

Versioning and Release Process
------------------------------

Valkyrja follows [semantic versioning][semantic versioning url] with a major
release every year, and support for each major version for 2 years from the
date of release.

For more information see our
[Versioning and Release Process documentation][Versioning and Release Process url].

### Supported Versions

Bug fixes are provided until 3 months after the next major release. Security
fixes are provided for 2 years after the initial release.

| Version | PHP              | Release             | Bug Fixes Until | Security Fixes Until |
| :------ | :--------------- | :------------------ | :-------------- | :------------------- |
| 25 (*)  | 8.4 – 8.6        | December 11th, 2025 | March 31, 2026  | March 31, 2026       |
| 26      | 8.4.27 – 8.6 (†) | March 31, 2026      | Q2 2027         | Q1 2028              |
| 27      | 8.5 – 8.6        | Q1 2027             | Q2 2028         | Q1 2029              |
| 28      | 8.6+             | Q1 2028             | Q2 2029         | Q1 2030              |

(*) Version 25 was a pre-release line. It is no longer supported now that
version 26 has shipped.

(†) PHP 8.4.27 fixes CVE-2026-109618 and CVE-2026-109619. A Valkyrja 26 PHP
package does not install on an earlier 8.4 release.

Contributing
------------

Valkyrja is an open-source, community-driven project. Thank you for your
interest in helping develop, maintain, and release it.

See [`CONTRIBUTING.md`][contributing url] for the submission process and
[`VOCABULARY.md`][vocabulary url] for the terminology used across Valkyrja.

Security Issues
---------------

If you discover a security vulnerability within Valkyrja, please follow our
[disclosure procedure][security vulnerabilities url].

License
-------

Valkyrja is open-source software licensed under the
[MIT license][MIT license url]. See [`LICENSE.md`](./LICENSE.md).

[Valkyrja url]: https://valkyrja.io
[org url]: https://github.com/valkyrjaio
[sindri url]: https://github.com/valkyrjaio/sindri-php
[starter url]: https://github.com/valkyrjaio/valkyrja-starter-app-php
[docker url]: https://github.com/valkyrjaio/valkyrja-docker-php
[docs url]: ./src/Valkyrja/README.md
[http docs url]: ./src/Valkyrja/Http/README.md
[cli docs url]: ./src/Valkyrja/Cli/README.md
[container docs url]: ./src/Valkyrja/Container/README.md
[events docs url]: ./src/Valkyrja/Event/README.md
[orm docs url]: ./src/Valkyrja/Orm/README.md
[Versioning and Release Process url]: ./src/Valkyrja/VERSIONING_AND_RELEASE_PROCESS.md
[contributing url]: https://github.com/valkyrjaio/.github/blob/26.x/CONTRIBUTING.md
[vocabulary url]: https://github.com/valkyrjaio/.github/blob/26.x/VOCABULARY.md
[security vulnerabilities url]: https://github.com/valkyrjaio/.github/blob/26.x/SECURITY.md
[semantic versioning url]: https://semver.org/
[MIT license url]: https://opensource.org/licenses/MIT
[license url]: ./LICENSE.md

# Versioning and Release Process

## Introduction

Valkyrja versions are `YY.FEATURE.PATCH`, where `YY` is the two-digit year — so
`26.4.1` is the fifth feature release of the 2026 line. A new major line opens
once per year, and each is supported for two years from its release date.

This cadence gives you a predictable upgrade path: you know when a new version
is coming, how long your current version will be maintained, and what the
support window looks like before you need to plan a migration.

## Versioning

Each part of the version signals what kind of changes the release contains.

- **`YY`** — The year. Opens once a year, and is the only component bumped by
  hand.
- **`FEATURE`** — New features, deprecations, and breaking changes.
- **`PATCH`** — Everything else: fixes, documentation, and small atomic changes
  that don't affect existing behavior.

Because the year is the major component, it is the only one left to carry
breaking changes — so **`FEATURE` covers both new features and breaking
changes**. That is deliberate. Under strict semantic versioning an urgent fix
that happens to break a public contract cannot ship until someone cuts a major
release, which means either the fix waits or the break gets hidden in a patch.

Two things keep that manageable for you:

- **Planned removals wait for the year boundary.** An API is deprecated first and
  removed in the next `YY`, so you get a full line's notice.
- **Unplanned breaks are always marked.** Any change that breaks a public
  contract is flagged in the release notes, because the version number alone
  cannot tell you a `FEATURE` release broke something.

If you pin with a caret (`^26.1`), you will receive feature releases
automatically — read the release notes before upgrading.

## Release Schedule

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

## Support Policy

**Bug fixes** are provided until three months after the next major version is
released. This window exists to give applications time to migrate before the
previous version goes fully unsupported.

**Security fixes** are provided for two years from the initial release date of
each major version. After this period, the version receives no further patches
of any kind.

Applications running an end-of-life version should upgrade. Running unsupported
software in production means known security vulnerabilities will not be patched.

## Development Branches

Each major version in active development has a corresponding branch in the
repository.

| Branch   | Purpose                                                                         |
| :------- | :------------------------------------------------------------------------------ |
| `26.x`   | The current release line. Where fixes, features, and deprecations land.         |
| `master` | Preparation for the next year's major. Open for removals and large API changes. |

When a new major version ships, its `YY.x` branch becomes the current line and
the previous one moves into security-only mode.

Contributions should target the current `YY.x` branch. Only changes that must
wait for the next year — chiefly removing something already deprecated — target
`master`.

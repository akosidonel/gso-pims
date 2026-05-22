# Automatic Versioning And Changelog

The project now uses release tags as the source of truth for versions.

## Version Format

- Released build: `v1.20.1`
- Work in progress after a release: `v1.20.2-dev.3`
- Local uncommitted changes: `v1.20.2-dev.3.dirty`

This keeps the public version simple while still showing when the local copy is ahead of the last release.

## How The Next Version Is Chosen

`php tools/release.php` looks at commit messages since the latest `vX.Y.Z` tag.

- `major`: commit message contains `breaking change`, `breaking:`, or starts with `major:`
- `minor`: commit message starts with `feat`, `feature`, `add`, `added`, `new`, `create`, or `created`
- `patch`: everything else

If there is no existing release tag yet, the first release starts at `v1.0.0`.

## Changelog Rules

`CHANGELOG.md` is generated from commit history during release.

Entries are grouped into:

- `Added`
- `Changed`
- `Fixed`
- `Removed`

Because the changelog is generated from commit messages, short and clear commit messages will give the best result.

## Release Commands

Preview the next release without changing anything:

```bash
php tools/release.php --dry-run
```

Create the next release automatically:

```bash
php tools/release.php
```

Force a specific bump:

```bash
php tools/release.php patch
php tools/release.php minor
php tools/release.php major
```

Composer shortcuts are also available:

```bash
composer release:dry-run
composer release
```

## What The Release Script Does

When you run `php tools/release.php`:

1. It checks that the git working tree is clean.
2. It collects commits since the latest release tag.
3. It chooses the next version.
4. It prepends a new release section to `CHANGELOG.md`.
5. It creates a commit like `release: v1.20.1`.
6. It creates the matching git tag `v1.20.1`.

## Deployment Note

If a deployment does not include `.git`, set `APP_VERSION` so the app can still show the correct release number.

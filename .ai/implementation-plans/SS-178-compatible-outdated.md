# SS-178: Split the outdated report by compatibility

## Goal

The report shows which outdated direct dependencies can be updated **without** changing the project's
PHP version or the major version of its core packages (Silverstripe by default). Everything else is
collapsed into a `<details>` block with the reason it is blocked.

## Decisions

| # | Decision |
| - | -------- |
| 1 | Lives in this action, released as `v3`. `v2` users see no change. The update workflow moves to `@v3`. |
| 2 | Generic check. `compatibility-packages` input, default `php silverstripe/framework silverstripe/cms silverstripe/admin`. |
| 3 | `php-version` input, falling back to `config.platform.php` in `composer.json`. A `major.minor` value means the newest patch of that line. |
| 4 | Report logic in PHP (`composer/semver`). `entrypoint.sh` keeps the `composer outdated` call and the `GITHUB_OUTPUT` plumbing. |
| 5 | Per-version `require` data from the Packagist `p2` API. Anything it cannot resolve is "compatibility unknown". |
| 6 | Packages with no compatible newer version are shown, collapsed. |
| 7 | Update workflow intro: "These packages have newer versions that are compatible with this project. Update their constraints in composer.json to pick them up." |
| 8 | PHPUnit tests with fixtures and a fake version source; no network. Run in a GitHub workflow. |

> **Review [2026-09-24]:**
> **Status:** Decisions 2 and 3 deviated (approved)
> **What changed:** `config.platform.php` takes precedence and `php-version` is the fallback (`src/Platform.php`). The `compatibility-packages` default is `silverstripe/framework silverstripe/cms silverstripe/admin`; a `php` entry is ignored and PHP is checked whenever a version is known. The input reaches the container as `TARGET_PHP_VERSION`.
> **Root cause:** The update workflow always passes its `php_version` input, which defaults to `8.3`, so input-first precedence checked every project as 8.3 and marked PHP 8.3-only releases compatible for 8.1 projects. Composer itself treats `config.platform.php` as the platform. Keeping PHP in the list meant overriding the list silently turned PHP checks off. The `composer` base image sets `ENV PHP_VERSION` to its own PHP, so that env name could leak the container's version in.
> **Decision:** Approved (defaults accepted).

## Compatibility rule

For an outdated package `P`, a candidate version `V` (newer than installed, no less stable than
installed, stable when installed is stable) is **compatible** when, for every compatibility package `C`
other than `P`:

- **php:** `V`'s `require.php`, if any, is satisfied by the target PHP version.
- **Forward:** `V`'s `require[C]`, if any, intersects `^<locked C>`, i.e. some release in the locked
  major of `C` (at or above the locked version) satisfies it. Bumping `C` within its major is fine.
- **Reverse:** if locked `C` requires `P`, then some release of `C` inside `^<locked C>` either has
  no requirement on `P` or allows `V`.
- **Self:** if `P` is itself a compatibility package, `V` stays in `P`'s installed major.

> **Review [2026-09-24]:**
> **Status:** Deviated (approved)
> **What changed:** The release of `C` that allows `V` must also be no less stable than locked `C` and must itself pass the PHP and forward checks (`CompatibilityChecker::lineAllows`). Installs from a named branch (`dev-main`) are "compatibility unknown", and compatibility packages on one are not treated as locked.
> **Root cause:** `^5.2.22.0` matches `5.3.0.0-beta1`, and the first allowing release was accepted without checking its own `php` requirement, so a beta or a PHP 8.3-only framework/admin release could make an update look compatible on a stable 8.1 project. `dev-main` normalizes to a non-numeric version that every tag compares as newer than.
> **Decision:** Approved (defaults accepted).

The newest compatible candidate is reported. When there is none, the blockers of the latest release
are listed instead.

## Output

- Open table: packages with a compatible update, plus abandoned packages. Columns:
  Package, Current, Compatible, Latest, Compare (current...compatible), Details.
- `<details>` with a count in the `<summary>`: the rest. Columns: Package, Current, Latest,
  Compare (current...latest), Blocked by.

> **Review [2026-09-24]:**
> **Status:** Deviated (denied, fixed in 8fc75f5)
> **What changed:** An abandoned package with no compatible release shows Compatible `-`, compares against latest, and now carries `; blocked by ...` or `; compatibility unknown` in Details.
> **Root cause:** Abandoned packages stay in the open table whatever their compatibility, so without this their blockers appeared nowhere in the report.
> **Decision:** Denied, reworked.

## Plan

<!-- REVIEW: tasks 1-9 completed; tasks 4 and 8 extended during review -->
1. `composer.json` with `composer/semver`, `composer/metadata-minifier`, PHPUnit dev; PSR-4 `src/`.
2. `src/VersionSource.php`, `src/PackagistVersionSource.php`: fetch and expand p2 metadata, cached per package.
3. `src/Platform.php`: target PHP, locked versions and locked requires of the compatibility packages.
4. `src/CompatibilityChecker.php`: the rule above, returning compatible version or blockers.
5. `src/MarkdownReport.php`: the two sections.
6. `bin/report.php`: wires it together from the workspace `composer.json`, `composer.lock` and the outdated JSON.
7. `entrypoint.sh`, `Dockerfile`, `action.yml` (`php-version`, `compatibility-packages` inputs), README.
8. `tests/` and `.github/workflows/tests.yml`.
9. In `composer-update-action`: `@v3`, pass `php-version`, new intro copy.

> **Review [2026-09-24]:**
> **Status:** Unplanned additions (approved)
> **What was added:** `src/Versions.php` (semver helpers that treat unparseable constraints such as `self.version` as no opinion), `src/CompatibilityResult.php`, `phpunit.xml.dist`, `.gitignore`, `tests/ArrayVersionSource.php`, and `tests/ReportScriptTest.php` with `tests/fixtures/project/`. `bin/report.php` reads `PACKAGIST_URL` so its tests run offline.
> **Root cause:** Supporting work for tasks 4 and 8. The script test was added after review found `bin/report.php` and the unreadable-input path untested (denied, fixed in 8fc75f5).

---

## Review Record

**Reviewed:** 2026-09-24
**Reviewer:** Claude (Opus subagent, fresh context)
**Branch:** feature/SS-178-compatible-outdated (both repos)
**Commit:** 9444434 (reviewed), 8fc75f5 (rework)

### Verification Results
- **Tests:** 31 passed at review; 35 tests, 55 assertions after rework
- **Lint:** `php -l` clean

### Triage Summary
| # | Finding | Type | Decision |
|---|---------|------|----------|
| 1 | PHP version precedence reversed | Deviation | Approved |
| 2 | `php` dropped from the compatibility-packages default | Deviation | Approved |
| 3 | Reverse rule stricter than planned | Deviation | Approved |
| 4 | Abandoned packages lose their blockers | Deviation | Denied, fixed in 8fc75f5 |
| 5 | Plan text out of date | Deviation | Approved, annotated here |
| 6 | Supporting files not named in the plan | Unplanned | Approved |
| 7 | `bin/report.php` and the failure path untested | Test issue | Denied, fixed in 8fc75f5 |

### Technical Context & Learnings
- Composer's own `config.platform.php` is the authoritative platform; any action input for PHP should only fill in when it is absent. Reusable workflows pass input defaults whether or not the caller set them.
- The official `composer` image inherits `ENV PHP_VERSION` from the php image; action env vars must not reuse that name.
- Packagist p2 metadata is minified (`composer/2.0`); fields carry over from the previous version until changed and must be expanded with `composer/metadata-minifier`.
- A caret constraint on a stable version still matches later pre-releases (`^5.2.22.0` matches `5.3.0.0-beta1`), so stability has to be filtered explicitly.
- `v3` of this action must be tagged before the composer-update-action change that points at `@v3` merges.

### Items Requiring Rework
None

### Deferred/Skipped Items
- `-d <dir>` in `extra-arguments`: the report still reads the root `composer.json`/`composer.lock`. Rare; no cheap fix.
- `composer_outdated_abandoned` is declared in `action.yml` but never written (pre-existing).

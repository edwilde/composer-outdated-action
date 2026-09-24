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

The newest compatible candidate is reported. When there is none, the blockers of the latest release
are listed instead.

## Output

- Open table: packages with a compatible update, plus abandoned packages. Columns:
  Package, Current, Compatible, Latest, Compare (current...compatible), Details.
- `<details>` with a count in the `<summary>`: the rest. Columns: Package, Current, Latest,
  Compare (current...latest), Blocked by.

## Plan

1. `composer.json` with `composer/semver`, `composer/metadata-minifier`, PHPUnit dev; PSR-4 `src/`.
2. `src/VersionSource.php`, `src/PackagistVersionSource.php`: fetch and expand p2 metadata, cached per package.
3. `src/Platform.php`: target PHP, locked versions and locked requires of the compatibility packages.
4. `src/CompatibilityChecker.php`: the rule above, returning compatible version or blockers.
5. `src/MarkdownReport.php`: the two sections.
6. `bin/report.php`: wires it together from the workspace `composer.json`, `composer.lock` and the outdated JSON.
7. `entrypoint.sh`, `Dockerfile`, `action.yml` (`php-version`, `compatibility-packages` inputs), README.
8. `tests/` and `.github/workflows/tests.yml`.
9. In `composer-update-action`: `@v3`, pass `php-version`, new intro copy.

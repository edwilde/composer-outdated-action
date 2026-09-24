# Composer Outdated action

This action runs [composer outdated](https://getcomposer.org/doc/03-cli.md#outdated) on your project and generates a markdown table with the results. This is handy for adding to a pull request output.

## Usage

In your workflow, define a step which refers to the action:

```yml
  steps:
    # ...
    - name: Composer Outdated
      id: composer_outdated
      uses: edwilde/composer-outdated-action@v3
      with:
        extra-arguments: '--direct'
        php-version: '8.3'
```

## Preview

Packages with an update that works on the project's current PHP version and core package versions are listed in the table. The rest are collapsed below it, with what blocks their latest release.

| Package | Current | Compatible | Latest | Compare | Details |
| ------- | ------- | ---------- | ------ | ------- | ------- |
| [dnadesign/silverstripe-elemental](https://github.com/silverstripe/silverstripe-elemental) | 5.0.4 | 5.4.10 | 6.2.3 | [Compare](https://github.com/silverstripe/silverstripe-elemental/compare/5.0.4...5.4.10) | Elemental pagetype and co… |
| [guzzlehttp/guzzle](https://github.com/guzzle/guzzle) | 7.5.3 | 7.15.5 | 8.2.0 | [Compare](https://github.com/guzzle/guzzle/compare/7.5.3...7.15.5) | Guzzle is a PHP HTTP clie… |
| :warning: acme/private-thing | 1.0.0 | - | 2.0.0 | - | **Abandoned**, use `acme/new-thing` |

<details>
<summary>1 outdated package is not compatible with the current platform</summary>

| Package | Current | Latest | Compare | Blocked by |
| ------- | ------- | ------ | ------- | ---------- |
| [silverstripe/userforms](https://github.com/silverstripe/silverstripe-userforms) | 6.4.9 | 7.1.3 | [Compare](https://github.com/silverstripe/silverstripe-userforms/compare/6.4.9...7.1.3) | `php ^8.3`, `silverstripe/framework ^6.1`, `silverstripe/cms ^6` |

</details>

## How compatibility is decided

For each outdated package, the action reads every release from [Packagist](https://packagist.org) and picks the newest one that:

- is newer than the installed version, and no less stable (a stable install only considers stable releases),
- has a `php` requirement satisfied by `php-version`,
- only requires versions of the `compatibility-packages` within their installed major, e.g. `^5.3` is fine on Silverstripe 5.2 but `^6` is not,
- is allowed by some release of each compatibility package within its installed major,
- stays within its own installed major, when it is itself a compatibility package.

"Blocked by" lists the reasons the latest release fails: a requirement it has (`silverstripe/framework ^6`), or an installed release line that does not allow it (`silverstripe/cms 5.x`).

Packages not on Packagist (private repositories, VCS forks) are listed as "compatibility unknown". Abandoned packages always stay in the table.

## Inputs

### `extra-arguments`

Extra arguments passed to the `composer outdated` command, separated with a space, in the same way as you would add them on the command-line. See [the available options](https://getcomposer.org/doc/03-cli.md#outdated).

### `php-version`

The PHP version updates must work with, e.g. `8.3`. A `major.minor` version stands for the newest patch release of that line. Defaults to `config.platform.php` in `composer.json`; with neither, PHP requirements are not checked.

### `compatibility-packages`

Space-separated packages whose installed major version updates must stay on. Defaults to `php silverstripe/framework silverstripe/cms silverstripe/admin`. Packages that are not installed are ignored.

## Outputs

### Outdated packages report (`composer_outdated`)

The markdown table of compatible updates, followed by a collapsed table of the other outdated packages.

This can be output in later steps using:

```
${{ steps.composer_outdated.outputs.composer_outdated }}
```

### Exit code (`composer_outdated_exit_code`)

The raw exit code from the `composer outdated` command, useful for debugging.

This can be output in later steps using:

```
${{ steps.composer_outdated.outputs.composer_outdated_exit_code }}
```

## Development

```sh
composer install
vendor/bin/phpunit
```

## Gotchas

### Missing extension

```
Cannot use silverstripe/framework 4.13.39 as it requires ext-intl * which is missing from your platform.
```

Make sure the PHP extension is defined in `composer.json`

```json
"config": {
    "platform": {
        "php": "8.1.2",
        "ext-intl": "1.1.0"
    },
}
```

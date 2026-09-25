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
        compatibility-packages: 'acme/framework acme/cms'
```

## Preview

Packages with an update that works on the project's PHP version and the installed major of its `compatibility-packages` are listed in the table. The rest are collapsed below it, with what blocks their latest release. This example is for a project on PHP 8.1 with `acme/framework` 5.2 installed and `compatibility-packages: 'acme/framework acme/cms'`.

| Package | Current | Compatible | Latest | Compare | Details |
| ------- | ------- | ---------- | ------ | ------- | ------- |
| [acme/blog](https://github.com/acme/blog) | 5.0.4 | 5.4.10 | 6.2.3 | [Compare](https://github.com/acme/blog/compare/5.0.4...5.4.10) | Blog pages for acme/frame… |
| [guzzlehttp/guzzle](https://github.com/guzzle/guzzle) | 7.5.3 | 7.15.5 | 8.2.0 | [Compare](https://github.com/guzzle/guzzle/compare/7.5.3...7.15.5) | Guzzle is a PHP HTTP clie… |
| :warning: acme/private-thing | 1.0.0 | - | 2.0.0 | - | **Abandoned**, use `acme/new-thing`; compatibility unknown |

<details>
<summary>1 outdated package is not compatible with the current platform</summary>

| Package | Current | Latest | Compare | Blocked by |
| ------- | ------- | ------ | ------- | ---------- |
| [acme/forms](https://github.com/acme/forms) | 6.4.9 | 7.1.3 | [Compare](https://github.com/acme/forms/compare/6.4.9...7.1.3) | `php ^8.3`, `acme/framework ^6.1`, `acme/cms ^6` |

</details>

## How compatibility is decided

For each outdated package, the action reads every release from [Packagist](https://packagist.org) and picks the newest one that:

- is newer than the installed version, and no less stable (a stable install only considers stable releases),
- has a `php` requirement satisfied by the project's PHP version (`config.platform.php`, else `php-version`),
- only requires versions of the `compatibility-packages` within their installed major, e.g. `acme/framework ^5.3` is fine with 5.2 installed but `^6` is not,
- is allowed by some release of each compatibility package within its installed major, where that release is no less stable than the installed one and itself meets the two rules above,
- stays within its own installed major, when it is itself a compatibility package.
- does not need a new major of an installed package that the project's `composer.json` or another installed package holds back, e.g. `acme/assets ^3` when `acme/cms` requires `acme/assets ^2`.

"Blocked by" lists the reasons the latest release fails: a requirement it has (`acme/framework ^6`), or an installed release line that does not allow it (`acme/cms 5.x`).

With no `compatibility-packages`, only PHP requirements and installed packages that are held back are checked.

Packages not on Packagist (private repositories, VCS forks) and packages installed from a named branch such as `dev-main` are listed as "compatibility unknown". Abandoned packages always stay in the table.

If the `composer outdated` output cannot be read, the report says so and points to the workflow log.

## Inputs

### `extra-arguments`

Extra arguments passed to the `composer outdated` command, separated with a space, in the same way as you would add them on the command-line. See [the available options](https://getcomposer.org/doc/03-cli.md#outdated).

### `php-version`

The PHP version the project runs on, e.g. `8.3`, used when `composer.json` has no `config.platform.php` (which composer itself treats as the platform, so it takes precedence). A `major.minor` version, optionally followed by `.x` or `.*`, stands for the newest patch release of that line. With neither, PHP requirements are not checked and the log says so.

### `compatibility-packages`

Space-separated packages whose installed major version updates must stay on, typically the framework the project is built on. Empty by default. Packages that are not installed, or are installed from a named branch, are ignored. PHP is always checked and does not need listing.

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
Cannot use vendor/package 4.13.39 as it requires ext-intl * which is missing from your platform.
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

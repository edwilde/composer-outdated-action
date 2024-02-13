# Composer Outdated action

This action runs [composer outdated](https://getcomposer.org/doc/03-cli.md#outdated) on your project and generates a markdown table with the results. This is handy for adding to a pull request output.

## Usage

In your workflow, define a step which refers to the action:

```yml
  steps:
    # ...
    - name: Composer Outdated
      id: composer_outdated
      uses: edwilde/composer-outdated-action@2
      with:
        extra-arguments: '--direct'
```

## Preview

This is an example of the output for the outdated table, including the abandoned packages.

| Package | Current | New | Compare | Details |
| ------- | ------- | --- | ------- | ------- |
| :warning: [betterbrief/silverstripe-googlemapfield](https://github.com/BetterBrief/silverstripe-googlemapfield) | v2.2.1 | v2.2.1 | [Compare](https://github.com/BetterBrief/silverstripe-googlemapfield/compare/v2.2.1...v2.2.1) | Save locations using lati… |
| [bramus/monolog-colored-line-formatter](https://github.com/bramus/monolog-colored-line-formatter) | 2.0.3 | 3.1.2 | [Compare](https://github.com/bramus/monolog-colored-line-formatter/compare/2.0.3...3.1.2) | Colored Line Formatter fo… |
| [doctrine/lexer](https://github.com/doctrine/lexer) | 2.1.0 | 3.0.1 | [Compare](https://github.com/doctrine/lexer/compare/2.1.0...3.0.1) | PHP Doctrine Lexer parser… |
| [silverstripe/framework](https://github.com/silverstripe/silverstripe-framework) | 4.13.39 | 5.1.15 | [Compare](https://github.com/silverstripe/silverstripe-framework/compare/4.13.39...5.1.15) | The SilverStripe framewor… |

## Inputs

Extra arguments can be passed to the `composer outdated` command using the `extra-arguments` parameter. These should be separated with a space, in the same way as you would add them on the command-line.

See [the available options](https://getcomposer.org/doc/03-cli.md#outdated).

## Outputs

### Outdated packages table (`composer_outdated`)

This is the markdown table displaying all outdated packages, their current version and the latest version.

In this output, any packages which are *not* outdated (i.e. use the symbol `=` in the raw output) are removed.

This can be output in later steps using:

```
${{ steps.composer_outdated.outputs.composer_outdated }}
```

### Abandoned packages list (`composer_outdated_abandoned`)

If any abandoned packages are found, they are output in this variable as a list.

This can be output in later steps using:

```
${{ steps.composer_outdated.outputs.composer_outdated_abandoned }}
```

### Exit code (`composer_outdated_exit_code`)

The raw exit code from the `composer outdated` command, useful for debugging.

This can be output in later steps using:

```
${{ steps.composer_outdated.outputs.composer_outdated_exit_code }}
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
        "php": "8.1.0",
        "ext-intl": "1.1.0"
    },
}
```

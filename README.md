# Composer Outdated action

This action runs [composer outdated](https://getcomposer.org/doc/03-cli.md#outdated) on your project and generates a markdown table with the results. This is handy for adding to a pull request output.

## Usage

In your workflow, define a step which refers to the action:

```yml
  steps:
    # ...
    - name: Composer Outdated
      id: composer_outdated
      uses: edwilde/composer-outdated-action
      with:
        extra-arguments: '--direct'
```

## Preview

This is an example of the output for the outdated table, then the abandoned packages.

| Package | Current | Release | New | Details |
| ------- | ------- | ------- | --- | ------- |
| andrewandante/silverstripe-pdf-parser | v2.0.0 | major | v3.0.0 | Adds PDFParser p... |
| dnadesign/silverstripe-elemental | 4.11.7 | major | 5.1.3 | Elemental pagety... |
| dnadesign/silverstripe-elemental-userforms | 3.3.4 | minor/patch | 3.3.5 | Adds a new eleme... |
| heyday/silverstripe-menumanager | 3.3.0 | minor/patch | 3.4.0 | Allows complex m... |

Package betterbrief/silverstripe-googlemapfield is abandoned, you should avoid using it. No replacement was suggested.

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

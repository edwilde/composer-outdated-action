#!/bin/bash

git config --global --add safe.directory "$GITHUB_WORKSPACE"

ARGS=$*
OUTDATED_FILE="$(mktemp)"
composer outdated --ignore-platform-reqs --format=json $ARGS > "$OUTDATED_FILE"
EXIT_CODE=$?

cat "$OUTDATED_FILE"

# Split the outdated packages by compatibility with PHP_VERSION and COMPATIBILITY_PACKAGES
REPORT=$(php /action/bin/report.php "$OUTDATED_FILE")

echo "$REPORT"

# Set a delimiter to allow for multi-line output
delimiter="$(openssl rand -hex 8)"

{
	echo "composer_outdated<<${delimiter}"
	echo "${REPORT}"
	echo ""
	echo "${delimiter}"
} >> "${GITHUB_OUTPUT}"

# Output the exit code for 'composer outdated'
echo "composer_outdated_exit_code=$EXIT_CODE" >> "$GITHUB_OUTPUT"

# Exit with the exit code from 'composer outdated'
echo "Exit $EXIT_CODE"

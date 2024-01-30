#!/bin/bash

git config --global --add safe.directory "$GITHUB_WORKSPACE"

ARGS=$*
OUTDATED=$(composer outdated $ARGS)
EXIT_CODE=$?

set -e

# Remove any abandoned packages from the list
ABANDONED=$(echo "$OUTDATED" | grep 'abandoned')
OUTDATED=$(echo "$OUTDATED" | grep -v 'abandoned')

# Construct the markdown table
table_legend="| Package | Current | Release | New | Details |"
table_divider="| ------- | ------- | ------- | --- | ------- |"
table=$(echo "$OUTDATED" | awk -v OFS="|" 'BEGIN { FS = " " } ; { if ($3 != "=") { gsub("~", "major", $3); gsub("!", "minor/patch", $3); printf "| %s | %s | %s | %s |", $1, $2, $3, $4; for(i=5; i<=NF; i++) printf " %s", $i; print " |" } }')

# Set a delimiter to allow for multi-line output
delimiter="$(openssl rand -hex 8)"

# Output the exit code for 'composer outdated'
echo "composer_outdated_exit_code=$EXIT_CODE" >> $GITHUB_OUTPUT

# Output the table
echo "composer_outdated<<${delimiter}" >> "${GITHUB_OUTPUT}"
echo "${table_legend}" >> "${GITHUB_OUTPUT}"
echo "${table_divider}" >> "${GITHUB_OUTPUT}"
echo "${table}" >> "${GITHUB_OUTPUT}"
echo "" >> "${GITHUB_OUTPUT}"
echo "${delimiter}" >> "${GITHUB_OUTPUT}"

# Output the abandoned packages
echo "composer_outdated_abandoned<<${delimiter}" >> "${GITHUB_OUTPUT}"
echo "${ABANDONED}" >> "${GITHUB_OUTPUT}"
echo "${delimiter}" >> "${GITHUB_OUTPUT}"

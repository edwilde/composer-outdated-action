#!/bin/bash

git config --global --add safe.directory "$GITHUB_WORKSPACE"

ARGS=$*
OUTDATED=$(composer outdated --ignore-platform-reqs --format=json $ARGS)
EXIT_CODE=$?

echo "$OUTDATED"

table_legend="| Package | Current | Release | New | Details |"
table_divider="| ------- | ------- | ------- | --- | ------- |"
echo $table_legend
echo $table_divider

# Parse the json response for each package
echo $OUTDATED | jq -c '.installed[]' | while IFS= read -r item

do
	# echo $item;
    name=$(echo $item | jq -r ".name")
    direct_dependency=$(echo $item | jq -r '.["direct-dependency"]')
    homepage=$(echo $item | jq -r '.["homepage"]')
    source=$(echo $item | jq -r '.["source"]')
    version=$(echo $item | jq -r '.["version"]')
    latest=$(echo $item | jq -r '.["latest"]')
    latest_status=$(echo $item | jq -r '.["latest-status"]')
    description=$(echo $item | jq -r '.["description"]')
    abandoned=$(echo $item | jq -r '.["abandoned"]')

	# if the package is up-to-date, skip it
	if [ "$latest_status" == "up-to-date" ]; then
		continue
	fi

	# work out the github address from the source, removing everything after 'tree'
	if [ -n "$source" ]; then
		github=$(echo $source | sed 's/\/tree.*//')
	fi

	# make the package name a link to the package's github url
	if [ -n "$github" ]; then
		name="[$name]($github)"
	fi

	# replace the release with an simpler text
	if [ "$latest_status" == "semver-safe-update" ]; then
		latest_status="minor/patch"
	elif [ "$latest_status" == "update-possible" ]; then
		latest_status="major"
	fi

	# if the package is abandoned, add a warning icon to the latest status
	if [ -n "$abandoned" ]; then
		latest_status="abandoned"
	fi

	if [ -n "$github" ]; then
		# use the source url to compare the current and latest versions using github ... syntax
		latest="[$latest]($github/compare/$version...$latest)"
	fi

	# construct the table row
    table="| $name | $version | $latest_status | $latest | $description |"
	echo $table
done

# Remove any abandoned packages from the list
# ABANDONED=$(echo "$OUTDATED" | grep 'abandoned')
# OUTDATED=$(echo "$OUTDATED" | grep -v 'abandoned')

# # Construct the markdown table

# table=$(echo "$OUTDATED" | awk -v OFS="|" 'BEGIN { FS = " " } ; { if ($3 != "=") { gsub("~", "major", $3); gsub("!", "minor/patch", $3); printf "| %s | %s | %s | %s |", $1, $2, $3, $4; for(i=5; i<=NF; i++) printf " %s", $i; print " |" } }')

# # Set a delimiter to allow for multi-line output
# delimiter="$(openssl rand -hex 8)"

# # Output the exit code for 'composer outdated'
# echo "composer_outdated_exit_code=$EXIT_CODE" >> $GITHUB_OUTPUT

# # Output the table


# echo "composer_outdated<<${delimiter}" >> "${GITHUB_OUTPUT}"
# echo "${table_legend}" >> "${GITHUB_OUTPUT}"
# echo "${table_divider}" >> "${GITHUB_OUTPUT}"
# echo "${table}" >> "${GITHUB_OUTPUT}"
# echo "" >> "${GITHUB_OUTPUT}"
# echo "${delimiter}" >> "${GITHUB_OUTPUT}"

# # Output the abandoned packages
# echo "composer_outdated_abandoned<<${delimiter}" >> "${GITHUB_OUTPUT}"
# echo "${ABANDONED}" >> "${GITHUB_OUTPUT}"
# echo "${delimiter}" >> "${GITHUB_OUTPUT}"

# echo "Exit $EXIT_CODE"

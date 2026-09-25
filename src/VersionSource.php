<?php

namespace ComposerOutdated;

/**
 * Where package releases and their requirements are read from.
 */
interface VersionSource
{
	/**
	 * Every published version of a package, each with at least `version`, `version_normalized`
	 * and `require`, or null when the package cannot be resolved.
	 *
	 * @param string $package package name, e.g. `vendor/package`
	 * @return array<int, array<string, mixed>>|null
	 */
	public function versions(string $package): ?array;
}

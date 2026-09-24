<?php

namespace ComposerOutdated;

interface VersionSource
{
	/**
	 * Every published version of a package, each with at least `version`, `version_normalized`
	 * and `require`, or null when the package cannot be resolved.
	 *
	 * @return array<int, array<string, mixed>>|null
	 */
	public function versions(string $package): ?array;
}

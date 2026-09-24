<?php

namespace ComposerOutdated\Tests;

use Composer\Semver\VersionParser;
use ComposerOutdated\VersionSource;

class ArrayVersionSource implements VersionSource
{
	/**
	 * @param array<string, array<string, array<string, string>>> $packages package => version => require
	 */
	public function __construct(private array $packages)
	{
	}

	public function versions(string $package): ?array
	{
		if (!isset($this->packages[$package])) {
			return null;
		}

		$parser = new VersionParser();
		$versions = [];
		foreach ($this->packages[$package] as $version => $require) {
			$versions[] = [
				'version' => $version,
				'version_normalized' => $parser->normalize($version),
				'require' => $require,
			];
		}
		return $versions;
	}
}

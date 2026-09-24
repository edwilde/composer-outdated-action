<?php

namespace ComposerOutdated;

use Composer\MetadataMinifier\MetadataMinifier;

class PackagistVersionSource implements VersionSource
{
	/** @var array<string, array<int, array<string, mixed>>|null> */
	private array $cache = [];

	public function __construct(
		private string $baseUrl = 'https://repo.packagist.org/p2',
		private int $timeout = 15,
	) {
	}

	public function versions(string $package): ?array
	{
		if (!array_key_exists($package, $this->cache)) {
			$this->cache[$package] = $this->fetch($package);
		}

		return $this->cache[$package];
	}

	private function fetch(string $package): ?array
	{
		if (!preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#i', $package)) {
			return null;
		}

		$context = stream_context_create([
			'http' => [
				'timeout' => $this->timeout,
				'user_agent' => 'edwilde/composer-outdated-action',
			],
		]);

		$body = @file_get_contents(sprintf('%s/%s.json', rtrim($this->baseUrl, '/'), strtolower($package)), false, $context);
		if ($body === false) {
			return null;
		}

		$data = json_decode($body, true);
		$versions = $data['packages'][strtolower($package)] ?? null;
		if (!is_array($versions) || !$versions) {
			return null;
		}

		if (($data['minified'] ?? null) === 'composer/2.0') {
			$versions = MetadataMinifier::expand($versions);
		}

		return $versions;
	}
}

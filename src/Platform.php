<?php

namespace ComposerOutdated;

use Composer\Semver\VersionParser;
use UnexpectedValueException;

/**
 * The versions an update has to stay compatible with: the target PHP version and the locked
 * versions of the compatibility packages.
 */
class Platform
{
	/**
	 * @param string|null $php target PHP version, normalized, or null to skip PHP checks
	 * @param array<string, array{version: string, normalized: string, require: array<string, string>}> $locked
	 *     locked compatibility packages keyed by lowercase name
	 * @param string[] $compatibilityPackages lowercase package names, `php` included
	 */
	public function __construct(
		public readonly ?string $php,
		public readonly array $locked,
		public readonly array $compatibilityPackages,
	) {
	}

	/**
	 * Builds the platform from a project's composer files.
	 *
	 * @param array<string, mixed> $composerJson decoded composer.json
	 * @param array<string, mixed> $composerLock decoded composer.lock
	 * @param string|null $phpVersion target PHP version; falls back to `config.platform.php`
	 * @param string[] $compatibilityPackages lowercase package names, `php` included
	 * @return self
	 */
	public static function fromProject(
		array $composerJson,
		array $composerLock,
		?string $phpVersion,
		array $compatibilityPackages,
	): self {
		$phpVersion = $phpVersion ?: ($composerJson['config']['platform']['php'] ?? null);

		$locked = [];
		foreach (array_merge($composerLock['packages'] ?? [], $composerLock['packages-dev'] ?? []) as $package) {
			$name = strtolower($package['name'] ?? '');
			if (!in_array($name, $compatibilityPackages, true)) {
				continue;
			}
			$normalized = Versions::normalize($package['version'] ?? '');
			if ($normalized === null) {
				continue;
			}
			$locked[$name] = [
				'version' => $package['version'],
				'normalized' => $normalized,
				'require' => array_change_key_case($package['require'] ?? [], CASE_LOWER),
			];
		}

		return new self(self::normalizePhp($phpVersion), $locked, $compatibilityPackages);
	}

	/**
	 * Normalizes the target PHP version. A `major.minor` version stands for the newest patch
	 * release of that line.
	 *
	 * @param string|null $version PHP version, e.g. `8.3` or `8.1.2`
	 * @return string|null normalized version, or null when empty or unparseable
	 */
	public static function normalizePhp(?string $version): ?string
	{
		if ($version === null || trim($version) === '') {
			return null;
		}
		$version = trim($version);
		if (preg_match('/^\d+\.\d+$/', $version)) {
			$version .= '.99999';
		}
		try {
			return (new VersionParser())->normalize($version);
		} catch (UnexpectedValueException) {
			return null;
		}
	}
}

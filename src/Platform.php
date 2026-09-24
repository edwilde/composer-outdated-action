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
	 * @param string[] $compatibilityPackages lowercase package names
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
	 * @param string|null $phpVersion PHP version the project runs on, used when composer.json
	 *     has no `config.platform.php`
	 * @param string[] $compatibilityPackages lowercase package names; a `php` entry is ignored,
	 *     PHP is always checked when a version is known
	 * @return self
	 */
	public static function fromProject(
		array $composerJson,
		array $composerLock,
		?string $phpVersion,
		array $compatibilityPackages,
	): self {
		$phpVersion = ($composerJson['config']['platform']['php'] ?? null) ?: $phpVersion;
		$compatibilityPackages = array_values(array_diff($compatibilityPackages, ['php']));

		$locked = [];
		foreach (array_merge($composerLock['packages'] ?? [], $composerLock['packages-dev'] ?? []) as $package) {
			$name = strtolower($package['name'] ?? '');
			if (!in_array($name, $compatibilityPackages, true)) {
				continue;
			}
			$normalized = Versions::normalize($package['version'] ?? '');
			// lazy: a compatibility package locked to a named branch is not checked; resolve the
			// branch alias from the lock's `extra.branch-alias` if that becomes common.
			if ($normalized === null || Versions::isNamedBranch($normalized)) {
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
	 * Normalizes the target PHP version. A `major.minor` version, with or without a trailing
	 * `.x` or `.*`, stands for the newest patch release of that line.
	 *
	 * @param string|null $version PHP version, e.g. `8.3`, `8.3.x` or `8.1.2`
	 * @return string|null normalized version, or null when empty or not a `major.minor[.patch]` version
	 */
	public static function normalizePhp(?string $version): ?string
	{
		if ($version === null || trim($version) === '') {
			return null;
		}
		$version = trim($version);
		if (preg_match('/^(\d+\.\d+)(\.[x*])?$/i', $version, $matches)) {
			$version = $matches[1] . '.99999';
		} elseif (!preg_match('/^\d+\.\d+\.\d+/', $version)) {
			return null;
		}
		try {
			return (new VersionParser())->normalize($version);
		} catch (UnexpectedValueException) {
			return null;
		}
	}
}

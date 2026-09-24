<?php

namespace ComposerOutdated;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;

/**
 * Finds the newest release of a package that installs alongside the target PHP version and
 * the locked release lines of the compatibility packages.
 */
class CompatibilityChecker
{
	public function __construct(
		private Platform $platform,
		private VersionSource $source,
	) {
	}

	public function check(string $package, string $installedVersion): CompatibilityResult
	{
		$package = strtolower($package);
		$installed = Versions::normalize($installedVersion);
		$versions = $this->source->versions($package);
		if ($installed === null || $versions === null) {
			return CompatibilityResult::unknown();
		}

		$candidates = $this->candidates($versions, $installedVersion, $installed);
		if (!$candidates) {
			return CompatibilityResult::unknown();
		}

		foreach ($candidates as $candidate) {
			if (!$this->blockers($package, $installed, $candidate)) {
				return CompatibilityResult::compatible($candidate['version'], $candidates[0]['version']);
			}
		}

		return CompatibilityResult::blocked($candidates[0]['version'], $this->blockers($package, $installed, $candidates[0]));
	}

	/**
	 * Releases newer than the installed one, newest first. Pre-releases only count when the
	 * installed release is a pre-release of at least that stability; a dev branch counts as stable.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function candidates(array $versions, string $installedVersion, string $installed): array
	{
		$maxRank = Versions::stability($installedVersion) === 'dev' ? 0 : Versions::stabilityRank($installedVersion);

		$byNormalized = [];
		foreach ($versions as $version) {
			$normalized = $version['version_normalized'] ?? null;
			if (!is_string($normalized) || !isset($version['version'])) {
				continue;
			}
			if (Versions::stabilityRank($version['version']) > $maxRank) {
				continue;
			}
			if (!Comparator::greaterThan($normalized, $installed)) {
				continue;
			}
			$byNormalized[$normalized] = $version;
		}

		$order = Semver::rsort(array_keys($byNormalized));
		return array_map(fn ($normalized) => $byNormalized[$normalized], $order);
	}

	/**
	 * Why a candidate cannot be installed on the current platform, empty when it can.
	 *
	 * @return string[]
	 */
	private function blockers(string $package, string $installed, array $candidate): array
	{
		$blockers = [];
		$require = is_array($candidate['require'] ?? null) ? array_change_key_case($candidate['require'], CASE_LOWER) : [];

		foreach ($this->platform->compatibilityPackages as $compatibility) {
			if ($compatibility === 'php') {
				if ($this->platform->php !== null && isset($require['php'])
					&& !Versions::satisfies($this->platform->php, $require['php'])) {
					$blockers[] = 'php ' . $require['php'];
				}
				continue;
			}

			if ($compatibility === $package) {
				if (!Versions::satisfies($candidate['version_normalized'], Versions::lineConstraint($installed))) {
					$blockers[] = $package . ' ' . Versions::lineLabel($installed);
				}
				continue;
			}

			$locked = $this->platform->locked[$compatibility] ?? null;
			if ($locked === null) {
				continue;
			}

			if (isset($require[$compatibility])
				&& !Versions::intersects($require[$compatibility], Versions::lineConstraint($locked['normalized']))) {
				$blockers[] = $compatibility . ' ' . $require[$compatibility];
			}

			if (isset($locked['require'][$package])
				&& !$this->lineAllows($compatibility, $locked, $package, $candidate['version_normalized'])) {
				$blockers[] = $compatibility . ' ' . Versions::lineLabel($locked['normalized']);
			}
		}

		return $blockers;
	}

	/**
	 * Whether any release on the locked line of a compatibility package accepts the candidate.
	 * Falls back to the locked release's own requirement when the package cannot be resolved.
	 */
	private function lineAllows(string $compatibility, array $locked, string $package, string $candidate): bool
	{
		$line = Versions::lineConstraint($locked['normalized']);
		$releases = $this->source->versions($compatibility) ?? [['version_normalized' => $locked['normalized'], 'require' => $locked['require']]];

		foreach ($releases as $release) {
			$normalized = $release['version_normalized'] ?? null;
			if (!is_string($normalized) || !Versions::satisfies($normalized, $line)) {
				continue;
			}
			$require = is_array($release['require'] ?? null) ? array_change_key_case($release['require'], CASE_LOWER) : [];
			if (!isset($require[$package]) || Versions::satisfies($candidate, $require[$package])) {
				return true;
			}
		}

		return false;
	}
}

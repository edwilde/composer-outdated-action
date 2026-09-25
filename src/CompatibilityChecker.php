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
	/**
	 * Checker for one platform and version source.
	 *
	 * @param Platform $platform target PHP version and locked compatibility packages
	 * @param VersionSource $source where package releases are read from
	 */
	public function __construct(
		private Platform $platform,
		private VersionSource $source,
	) {
	}

	/**
	 * Checks an outdated package against the platform.
	 *
	 * @param string $package package name
	 * @param string $installedVersion installed version as composer reports it
	 * @return CompatibilityResult the newest compatible release, the blockers of the latest
	 *     release when none is compatible, or unknown when the releases cannot be read or the
	 *     installed version is a named branch such as `dev-main`
	 */
	public function check(string $package, string $installedVersion): CompatibilityResult
	{
		$package = strtolower($package);
		$installed = Versions::normalize($installedVersion);
		if ($installed === null || Versions::isNamedBranch($installed)) {
			return CompatibilityResult::unknown();
		}

		$versions = $this->source->versions($package);
		if ($versions === null) {
			return CompatibilityResult::unknown();
		}

		$candidates = $this->candidates($versions, $installedVersion, $installed);
		if (!$candidates) {
			return CompatibilityResult::unknown();
		}

		$latestBlockers = $this->blockers($package, $installed, $candidates[0]);
		if (!$latestBlockers) {
			return CompatibilityResult::compatible($candidates[0]['version'], $candidates[0]['version']);
		}

		foreach (array_slice($candidates, 1) as $candidate) {
			if (!$this->blockers($package, $installed, $candidate)) {
				return CompatibilityResult::compatible($candidate['version'], $candidates[0]['version']);
			}
		}

		return CompatibilityResult::blocked($candidates[0]['version'], $latestBlockers);
	}

	/**
	 * Releases newer than the installed one, newest first. Pre-releases only count when the
	 * installed release is a pre-release of at least that stability; a dev branch counts as stable.
	 *
	 * @param array<int, array<string, mixed>> $versions every release of the package
	 * @param string $installedVersion installed version as composer reports it
	 * @param string $installed installed version, normalized
	 * @return array<int, array<string, mixed>> candidate releases, newest first
	 */
	private function candidates(array $versions, string $installedVersion, string $installed): array
	{
		$maxRank = Versions::maxCandidateRank($installedVersion);

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
	 * @param string $package package name, lowercase
	 * @param string $installed installed version, normalized
	 * @param array<string, mixed> $candidate candidate release
	 * @return string[] blockers, e.g. `php ^8.3`, `acme/framework ^6`, `acme/cms 5.x`
	 */
	private function blockers(string $package, string $installed, array $candidate): array
	{
		$require = $this->requirements($candidate);
		$blockers = $this->forwardBlockers($require, $package);

		if (in_array($package, $this->platform->compatibilityPackages, true)
			&& !Versions::satisfies($candidate['version_normalized'], Versions::lineConstraint($installed))) {
			$blockers[] = $package . ' ' . Versions::lineLabel($installed);
		}

		foreach ($this->platform->locked as $compatibility => $locked) {
			if ($compatibility === $package || !isset($locked['require'][$package])) {
				continue;
			}
			if (!$this->lineAllows($compatibility, $locked, $package, $candidate['version_normalized'])) {
				$blockers[] = $compatibility . ' ' . Versions::lineLabel($locked['normalized']);
			}
		}

		return $blockers;
	}

	/**
	 * Requirements of a release that the platform cannot meet: a PHP version other than the
	 * target, or a compatibility package outside its locked major.
	 *
	 * @param array<string, string> $require the release's requirements, lowercase keys
	 * @param string $self package the release belongs to, whose own line is not checked here
	 * @return string[] blockers, e.g. `php ^8.3`, `acme/framework ^6`
	 */
	private function forwardBlockers(array $require, string $self): array
	{
		$blockers = [];

		if ($this->platform->php !== null && isset($require['php'])
			&& !Versions::satisfies($this->platform->php, $require['php'])) {
			$blockers[] = 'php ' . $require['php'];
		}

		foreach ($this->platform->locked as $compatibility => $locked) {
			if ($compatibility === $self || !isset($require[$compatibility])) {
				continue;
			}
			if (!Versions::intersects($require[$compatibility], Versions::lineConstraint($locked['normalized']))) {
				$blockers[] = $compatibility . ' ' . $require[$compatibility];
			}
		}

		return $blockers;
	}

	/**
	 * Whether a release on the locked line of a compatibility package accepts the candidate and
	 * can itself be installed on the platform. Falls back to the locked release's own
	 * requirement when the package cannot be resolved.
	 *
	 * @param string $compatibility compatibility package name
	 * @param array{version: string, normalized: string, require: array<string, string>} $locked locked release of it
	 * @param string $package package being checked
	 * @param string $candidate candidate version of that package, normalized
	 * @return bool true when some release on the locked line allows the candidate
	 */
	private function lineAllows(string $compatibility, array $locked, string $package, string $candidate): bool
	{
		$line = Versions::lineConstraint($locked['normalized']);
		$maxRank = Versions::maxCandidateRank($locked['version']);
		$releases = $this->source->versions($compatibility)
			?? [['version' => $locked['version'], 'version_normalized' => $locked['normalized'], 'require' => $locked['require']]];

		foreach ($releases as $release) {
			$normalized = $release['version_normalized'] ?? null;
			if (!is_string($normalized) || !Versions::satisfies($normalized, $line)) {
				continue;
			}
			if (Versions::stabilityRank((string) ($release['version'] ?? $normalized)) > $maxRank) {
				continue;
			}
			$require = $this->requirements($release);
			if (isset($require[$package]) && !Versions::satisfies($candidate, $require[$package])) {
				continue;
			}
			if (!$this->forwardBlockers($require, $compatibility)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Requirements of a release with lowercase package names.
	 *
	 * @param array<string, mixed> $release a release from the version source
	 * @return array<string, string> its requirements, lowercase keys
	 */
	private function requirements(array $release): array
	{
		return is_array($release['require'] ?? null) ? array_change_key_case($release['require'], CASE_LOWER) : [];
	}
}

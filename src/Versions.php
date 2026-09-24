<?php

namespace ComposerOutdated;

use Composer\Semver\Intervals;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use UnexpectedValueException;

/**
 * Version helpers that treat unparseable input (`self.version`, commit refs) as "no opinion".
 */
class Versions
{
	private const STABILITY_RANK = ['stable' => 0, 'RC' => 5, 'beta' => 10, 'alpha' => 15, 'dev' => 20];

	/**
	 * Normalizes a version as composer reports it, which may carry a trailing commit ref
	 * (`5.0.x-dev 61491f2`).
	 *
	 * @param string $version pretty version, e.g. `v5.2.1` or `5.0.x-dev 61491f2`
	 * @return string|null normalized version, e.g. `5.2.1.0`, or null when it cannot be parsed
	 */
	public static function normalize(string $version): ?string
	{
		$version = strtok(trim($version), ' ');
		if ($version === false || $version === '') {
			return null;
		}
		try {
			return (new VersionParser())->normalize($version);
		} catch (UnexpectedValueException) {
			return null;
		}
	}

	/**
	 * Whether a version satisfies a constraint.
	 *
	 * @param string $normalized normalized version to test
	 * @param string $constraint composer constraint, e.g. `^5.2`
	 * @return bool true when satisfied, or when the constraint cannot be parsed
	 */
	public static function satisfies(string $normalized, string $constraint): bool
	{
		try {
			return Semver::satisfies($normalized, $constraint);
		} catch (UnexpectedValueException) {
			return true;
		}
	}

	/**
	 * Whether some version satisfies both constraints.
	 *
	 * @param string $constraintA composer constraint
	 * @param string $constraintB composer constraint
	 * @return bool true when the constraints overlap, or when either cannot be parsed
	 */
	public static function intersects(string $constraintA, string $constraintB): bool
	{
		try {
			$parser = new VersionParser();
			return Intervals::haveIntersections($parser->parseConstraints($constraintA), $parser->parseConstraints($constraintB));
		} catch (UnexpectedValueException) {
			return true;
		}
	}

	/**
	 * Caret constraint covering the release line a version sits on: `5.2.22.0` gives `^5.2.22.0`,
	 * the dev branch `5.0.9999999.9999999-dev` gives `^5.0.0.0`.
	 *
	 * @param string $normalized normalized version
	 * @return string caret constraint from that version up to the next major
	 */
	public static function lineConstraint(string $normalized): string
	{
		return '^' . implode('.', self::numericParts($normalized));
	}

	/**
	 * Human label for a release line: `5.x`, or `0.3.x` below 1.0.
	 *
	 * @param string $normalized normalized version
	 * @return string release line label
	 */
	public static function lineLabel(string $normalized): string
	{
		$parts = self::numericParts($normalized);
		return $parts[0] === '0' ? sprintf('0.%s.x', $parts[1] ?? '0') : $parts[0] . '.x';
	}

	/**
	 * Ranks stability from `stable` (0) to `dev` (20); a higher rank is less stable.
	 *
	 * @param string $version pretty version, optionally with a trailing commit ref
	 * @return int stability rank
	 */
	public static function stabilityRank(string $version): int
	{
		return self::STABILITY_RANK[self::stability($version)] ?? self::STABILITY_RANK['dev'];
	}

	/**
	 * Least stable rank a newer release may have to be offered in place of this version:
	 * the version's own stability, or stable when it is a dev branch.
	 *
	 * @param string $version pretty version, optionally with a trailing commit ref
	 * @return int stability rank
	 */
	public static function maxCandidateRank(string $version): int
	{
		return self::stability($version) === 'dev' ? self::STABILITY_RANK['stable'] : self::stabilityRank($version);
	}

	/**
	 * Whether a normalized version is a named branch such as `dev-main`, which has no place in
	 * the numeric release order.
	 *
	 * @param string $normalized normalized version
	 * @return bool
	 */
	public static function isNamedBranch(string $normalized): bool
	{
		return str_starts_with($normalized, 'dev-');
	}

	/**
	 * Stability of a version.
	 *
	 * @param string $version pretty version, optionally with a trailing commit ref
	 * @return string one of `stable`, `RC`, `beta`, `alpha`, `dev`
	 */
	public static function stability(string $version): string
	{
		return VersionParser::parseStability((string) strtok(trim($version), ' '));
	}

	/**
	 * Numeric parts of a normalized version, with the stability suffix dropped and the
	 * dev-branch placeholder `9999999` read as 0.
	 *
	 * @param string $normalized normalized version
	 * @return string[] version parts, major first
	 */
	private static function numericParts(string $normalized): array
	{
		$base = preg_replace('/-.*$/', '', $normalized);
		return array_map(fn ($part) => $part === '9999999' ? '0' : $part, explode('.', $base));
	}
}

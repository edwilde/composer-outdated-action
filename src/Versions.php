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

	public static function satisfies(string $normalized, string $constraint): bool
	{
		try {
			return Semver::satisfies($normalized, $constraint);
		} catch (UnexpectedValueException) {
			return true;
		}
	}

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
	 */
	public static function lineConstraint(string $normalized): string
	{
		return '^' . implode('.', self::numericParts($normalized));
	}

	/**
	 * Human label for a release line: `5.x`, or `0.3.x` below 1.0.
	 */
	public static function lineLabel(string $normalized): string
	{
		$parts = self::numericParts($normalized);
		return $parts[0] === '0' ? sprintf('0.%s.x', $parts[1] ?? '0') : $parts[0] . '.x';
	}

	public static function stabilityRank(string $version): int
	{
		return self::STABILITY_RANK[self::stability($version)] ?? self::STABILITY_RANK['dev'];
	}

	public static function stability(string $version): string
	{
		return VersionParser::parseStability((string) strtok(trim($version), ' '));
	}

	/**
	 * @return string[]
	 */
	private static function numericParts(string $normalized): array
	{
		$base = preg_replace('/-.*$/', '', $normalized);
		return array_map(fn ($part) => $part === '9999999' ? '0' : $part, explode('.', $base));
	}
}

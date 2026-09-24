<?php

namespace ComposerOutdated;

/**
 * Outcome of checking one outdated package: compatible, blocked or unknown.
 */
class CompatibilityResult
{
	public const COMPATIBLE = 'compatible';
	public const BLOCKED = 'blocked';
	public const UNKNOWN = 'unknown';

	/**
	 * @param string $status one of the status constants
	 * @param string|null $compatible newest compatible release, when there is one
	 * @param string|null $latest newest release considered
	 * @param string[] $blockers why the latest release is not compatible
	 */
	private function __construct(
		public readonly string $status,
		public readonly ?string $compatible = null,
		public readonly ?string $latest = null,
		public readonly array $blockers = [],
	) {
	}

	/**
	 * @param string $compatible newest compatible release
	 * @param string $latest newest release considered
	 * @return self
	 */
	public static function compatible(string $compatible, string $latest): self
	{
		return new self(self::COMPATIBLE, $compatible, $latest);
	}

	/**
	 * @param string $latest newest release considered
	 * @param string[] $blockers why it is not compatible
	 * @return self
	 */
	public static function blocked(string $latest, array $blockers): self
	{
		return new self(self::BLOCKED, null, $latest, $blockers);
	}

	/**
	 * The package's releases could not be read, or none is newer than the installed one.
	 *
	 * @return self
	 */
	public static function unknown(): self
	{
		return new self(self::UNKNOWN);
	}
}

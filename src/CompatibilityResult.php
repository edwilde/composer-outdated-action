<?php

namespace ComposerOutdated;

class CompatibilityResult
{
	public const COMPATIBLE = 'compatible';
	public const BLOCKED = 'blocked';
	public const UNKNOWN = 'unknown';

	/**
	 * @param string[] $blockers
	 */
	private function __construct(
		public readonly string $status,
		public readonly ?string $compatible = null,
		public readonly ?string $latest = null,
		public readonly array $blockers = [],
	) {
	}

	public static function compatible(string $compatible, string $latest): self
	{
		return new self(self::COMPATIBLE, $compatible, $latest);
	}

	/**
	 * @param string[] $blockers
	 */
	public static function blocked(string $latest, array $blockers): self
	{
		return new self(self::BLOCKED, null, $latest, $blockers);
	}

	public static function unknown(): self
	{
		return new self(self::UNKNOWN);
	}
}

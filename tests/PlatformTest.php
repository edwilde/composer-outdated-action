<?php

namespace ComposerOutdated\Tests;

use ComposerOutdated\Platform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlatformTest extends TestCase
{
	/**
	 * @return array<string, array{0: string|null, 1: string|null}>
	 */
	public static function phpVersions(): array
	{
		return [
			'major.minor' => ['8.3', '8.3.99999.0'],
			'major.minor.x' => ['8.3.x', '8.3.99999.0'],
			'major.minor.*' => ['8.3.*', '8.3.99999.0'],
			'full version' => ['8.1.2', '8.1.2.0'],
			'bare major' => ['8', null],
			'major.x' => ['8.x', null],
			'empty' => ['', null],
			'null' => [null, null],
		];
	}

	#[DataProvider('phpVersions')]
	public function testNormalizesPhpVersions(?string $input, ?string $expected): void
	{
		$this->assertSame($expected, Platform::normalizePhp($input));
	}

	public function testConfigPlatformPhpWinsOverTheRuntimeVersion(): void
	{
		$platform = Platform::fromProject(['config' => ['platform' => ['php' => '8.1.2']]], [], '8.3', []);

		$this->assertSame('8.1.2.0', $platform->php);
	}

	public function testRuntimeVersionIsUsedWithoutConfigPlatformPhp(): void
	{
		$platform = Platform::fromProject([], [], '8.3', []);

		$this->assertSame('8.3.99999.0', $platform->php);
	}

	public function testOnlyCompatibilityPackagesOnNumberedReleasesAreLocked(): void
	{
		$platform = Platform::fromProject([], [
			'packages' => [
				['name' => 'silverstripe/framework', 'version' => '5.2.22'],
				['name' => 'silverstripe/cms', 'version' => 'dev-main'],
				['name' => 'acme/other', 'version' => '1.0.0'],
			],
			'packages-dev' => [
				['name' => 'silverstripe/admin', 'version' => '2.2.14'],
			],
		], null, ['php', 'silverstripe/framework', 'silverstripe/cms', 'silverstripe/admin']);

		$this->assertSame(['silverstripe/framework', 'silverstripe/admin'], array_keys($platform->locked));
		$this->assertSame(['silverstripe/framework', 'silverstripe/cms', 'silverstripe/admin'], $platform->compatibilityPackages);
	}
}

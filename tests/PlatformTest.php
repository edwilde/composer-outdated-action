<?php

namespace ComposerOutdated\Tests;

use ComposerOutdated\Platform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlatformTest extends TestCase
{
	/**
	 * PHP version inputs and their normalized form.
	 *
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

	/**
	 * Check PHP versions are normalized
	 */
	#[DataProvider('phpVersions')]
	public function testNormalizesPhpVersions(?string $input, ?string $expected): void
	{
		$this->assertSame($expected, Platform::normalizePhp($input));
	}

	/**
	 * Check config.platform.php wins over the runtime version
	 */
	public function testConfigPlatformPhpWinsOverTheRuntimeVersion(): void
	{
		$platform = Platform::fromProject(['config' => ['platform' => ['php' => '8.1.2']]], [], '8.3', []);

		$this->assertSame('8.1.2.0', $platform->php);
	}

	/**
	 * Check the runtime version is used without config.platform.php
	 */
	public function testRuntimeVersionIsUsedWithoutConfigPlatformPhp(): void
	{
		$platform = Platform::fromProject([], [], '8.3', []);

		$this->assertSame('8.3.99999.0', $platform->php);
	}

	/**
	 * Check only compatibility packages on numbered releases are locked
	 */
	public function testOnlyCompatibilityPackagesOnNumberedReleasesAreLocked(): void
	{
		$platform = Platform::fromProject([], [
			'packages' => [
				['name' => 'acme/framework', 'version' => '5.2.22'],
				['name' => 'acme/cms', 'version' => 'dev-main'],
				['name' => 'acme/other', 'version' => '1.0.0'],
			],
			'packages-dev' => [
				['name' => 'acme/admin', 'version' => '2.2.14'],
			],
		], null, ['php', 'acme/framework', 'acme/cms', 'acme/admin']);

		$this->assertSame(['acme/framework', 'acme/admin'], array_keys($platform->locked));
		$this->assertSame(['acme/framework', 'acme/cms', 'acme/admin'], $platform->compatibilityPackages);
	}

	/**
	 * Check installed packages record what the project and other packages require of them
	 */
	public function testInstalledPackagesRecordTheirRequirers(): void
	{
		$platform = Platform::fromProject(['require-dev' => ['Acme/Tool' => '^1']], [
			'packages' => [
				['name' => 'acme/lib', 'version' => '2.1.0'],
				['name' => 'acme/cms', 'version' => '5.2.5', 'require' => ['Acme/Lib' => '^2']],
				['name' => 'acme/branch', 'version' => 'dev-main'],
			],
			'packages-dev' => [
				['name' => 'acme/tool', 'version' => '1.0.0', 'require' => ['acme/lib' => '^2.1']],
			],
		], null, []);

		$this->assertSame(['acme/lib', 'acme/cms', 'acme/tool'], array_keys($platform->installed));
		$this->assertSame('2.1.0.0', $platform->installed['acme/lib']['normalized']);
		$this->assertSame(['acme/cms' => '^2', 'acme/tool' => '^2.1'], $platform->installed['acme/lib']['requiredBy']);
		$this->assertSame(['composer.json' => '^1'], $platform->installed['acme/tool']['requiredBy']);
	}
}

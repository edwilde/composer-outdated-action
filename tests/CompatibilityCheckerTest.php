<?php

namespace ComposerOutdated\Tests;

use ComposerOutdated\CompatibilityChecker;
use ComposerOutdated\CompatibilityResult;
use ComposerOutdated\Platform;
use PHPUnit\Framework\TestCase;

class CompatibilityCheckerTest extends TestCase
{
	private const COMPATIBILITY = ['php', 'silverstripe/framework', 'silverstripe/cms', 'silverstripe/admin'];

	/**
	 * @param array<string, array<string, array<string, string>>> $packages package => version => require
	 * @param string $php target PHP version
	 * @param array<string, mixed> $lock composer.lock contents; defaults to framework 5.2.22 and cms 5.2.5
	 * @return CompatibilityChecker
	 */
	private function checker(array $packages, string $php = '8.1', array $lock = []): CompatibilityChecker
	{
		$lock = $lock ?: [
			'packages' => [
				['name' => 'silverstripe/framework', 'version' => '5.2.22', 'require' => ['php' => '^8.1']],
				['name' => 'silverstripe/cms', 'version' => '5.2.5', 'require' => ['silverstripe/framework' => '~5.2.0']],
			],
		];

		return new CompatibilityChecker(
			Platform::fromProject([], $lock, $php, self::COMPATIBILITY),
			new ArrayVersionSource($packages),
		);
	}

	public function testPicksNewestReleaseOnTheInstalledSilverstripeLine(): void
	{
		$result = $this->checker([
			'acme/module' => [
				'1.0.0' => ['silverstripe/framework' => '^5'],
				'1.3.0' => ['silverstripe/framework' => '^5.3'],
				'2.0.0' => ['silverstripe/framework' => '^6'],
			],
		])->check('acme/module', '1.0.0');

		$this->assertSame(CompatibilityResult::COMPATIBLE, $result->status);
		$this->assertSame('1.3.0', $result->compatible);
		$this->assertSame('2.0.0', $result->latest);
	}

	public function testBlocksReleasesNeedingANewerPhp(): void
	{
		$result = $this->checker([
			'acme/lib' => [
				'1.0.0' => ['php' => '^8.1'],
				'1.1.0' => ['php' => '^8.1'],
				'1.2.0' => ['php' => '>=8.3'],
			],
		])->check('acme/lib', '1.0.0');

		$this->assertSame('1.1.0', $result->compatible);
		$this->assertSame('1.2.0', $result->latest);
	}

	public function testMajorMinorPhpVersionMeansNewestPatch(): void
	{
		$result = $this->checker([
			'acme/lib' => [
				'1.0.0' => [],
				'1.1.0' => ['php' => '>=8.1.20'],
			],
		], '8.1')->check('acme/lib', '1.0.0');

		$this->assertSame('1.1.0', $result->compatible);
	}

	public function testListsBlockersOfTheLatestReleaseWhenNothingIsCompatible(): void
	{
		$result = $this->checker([
			'acme/module' => [
				'1.4.0' => ['silverstripe/framework' => '^5'],
				'2.0.0' => ['php' => '^8.3', 'silverstripe/framework' => '^6', 'silverstripe/cms' => '^6'],
			],
		])->check('acme/module', '1.4.0');

		$this->assertSame(CompatibilityResult::BLOCKED, $result->status);
		$this->assertSame('2.0.0', $result->latest);
		$this->assertSame(['php ^8.3', 'silverstripe/framework ^6', 'silverstripe/cms ^6'], $result->blockers);
	}

	public function testCompatibilityPackageStaysOnItsMajorAndRespectsReverseRequirements(): void
	{
		$checker = $this->checker([
			'silverstripe/framework' => [
				'5.2.22' => ['php' => '^8.1'],
				'5.4.30' => ['php' => '^8.1'],
				'6.0.0' => ['php' => '^8.3'],
			],
			'silverstripe/cms' => [
				'5.2.5' => ['silverstripe/framework' => '~5.2.0'],
				'5.4.0' => ['silverstripe/framework' => '~5.4.0'],
				'6.0.0' => ['silverstripe/framework' => '^6'],
			],
		], '8.3');

		$result = $checker->check('silverstripe/framework', '5.2.22');

		$this->assertSame('5.4.30', $result->compatible);
		$this->assertSame('6.0.0', $result->latest);
	}

	public function testReverseRequirementBlocksAMajorUpgradeOfAnotherCorePackage(): void
	{
		$checker = $this->checker([
			'silverstripe/framework' => [
				'5.2.22' => [],
				'6.0.0' => [],
			],
			'silverstripe/cms' => [
				'5.2.5' => ['silverstripe/framework' => '~5.2.0'],
			],
		], '8.3');

		$result = $checker->check('silverstripe/framework', '5.2.22');

		$this->assertSame(CompatibilityResult::BLOCKED, $result->status);
		$this->assertSame(['silverstripe/framework 5.x', 'silverstripe/cms 5.x'], $result->blockers);
	}

	public function testIgnoresPreReleasesWhenInstalledIsStable(): void
	{
		$result = $this->checker([
			'acme/lib' => [
				'1.0.0' => [],
				'1.1.0' => [],
				'1.2.0-beta1' => [],
			],
		])->check('acme/lib', '1.0.0');

		$this->assertSame('1.1.0', $result->compatible);
		$this->assertSame('1.1.0', $result->latest);
	}

	public function testDevBranchInstallComparesAgainstTaggedReleases(): void
	{
		$result = $this->checker([
			'acme/lib' => [
				'5.0.3' => [],
				'5.1.0' => [],
			],
		])->check('acme/lib', '5.0.x-dev 61491f2');

		$this->assertSame('5.1.0', $result->compatible);
	}

	public function testUnresolvablePackageIsUnknown(): void
	{
		$result = $this->checker([])->check('acme/private', '1.0.0');

		$this->assertSame(CompatibilityResult::UNKNOWN, $result->status);
	}

	public function testUnparseableConstraintsDoNotBlock(): void
	{
		$result = $this->checker([
			'acme/recipe' => [
				'1.0.0' => [],
				'1.1.0' => ['silverstripe/framework' => 'self.version'],
			],
		])->check('acme/recipe', '1.0.0');

		$this->assertSame('1.1.0', $result->compatible);
	}
}

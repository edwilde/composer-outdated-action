<?php

namespace ComposerOutdated\Tests;

use ComposerOutdated\CompatibilityChecker;
use ComposerOutdated\CompatibilityResult;
use ComposerOutdated\Platform;
use PHPUnit\Framework\TestCase;

class CompatibilityCheckerTest extends TestCase
{
	private const COMPATIBILITY = ['php', 'acme/framework', 'acme/cms', 'acme/admin'];

	/**
	 * Checker over the given releases, PHP version and lock.
	 *
	 * @param array<string, array<string, array<string, string>>> $packages package => version => require
	 * @param string $php target PHP version
	 * @param array<string, mixed> $lock composer.lock contents; defaults to framework 5.2.22 and cms 5.2.5
	 * @return CompatibilityChecker
	 */
	private function checker(array $packages, string $php = '8.1', array $lock = []): CompatibilityChecker
	{
		$lock = $lock ?: [
			'packages' => [
				['name' => 'acme/framework', 'version' => '5.2.22', 'require' => ['php' => '^8.1']],
				['name' => 'acme/cms', 'version' => '5.2.5', 'require' => ['acme/framework' => '~5.2.0']],
			],
		];

		return new CompatibilityChecker(
			Platform::fromProject([], $lock, $php, self::COMPATIBILITY),
			new ArrayVersionSource($packages),
		);
	}

	/**
	 * Check the newest release on the installed framework line is picked
	 */
	public function testPicksNewestReleaseOnTheInstalledFrameworkLine(): void
	{
		$result = $this->checker([
			'acme/module' => [
				'1.0.0' => ['acme/framework' => '^5'],
				'1.3.0' => ['acme/framework' => '^5.3'],
				'2.0.0' => ['acme/framework' => '^6'],
			],
		])->check('acme/module', '1.0.0');

		$this->assertSame(CompatibilityResult::COMPATIBLE, $result->status);
		$this->assertSame('1.3.0', $result->compatible);
		$this->assertSame('2.0.0', $result->latest);
	}

	/**
	 * Check releases needing a newer PHP are blocked
	 */
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

	/**
	 * Check a major.minor PHP version stands for its newest patch
	 */
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

	/**
	 * Check the latest release's blockers are listed when nothing is compatible
	 */
	public function testListsBlockersOfTheLatestReleaseWhenNothingIsCompatible(): void
	{
		$result = $this->checker([
			'acme/module' => [
				'1.4.0' => ['acme/framework' => '^5'],
				'2.0.0' => ['php' => '^8.3', 'acme/framework' => '^6', 'acme/cms' => '^6'],
			],
		])->check('acme/module', '1.4.0');

		$this->assertSame(CompatibilityResult::BLOCKED, $result->status);
		$this->assertSame('2.0.0', $result->latest);
		$this->assertSame(['php ^8.3', 'acme/framework ^6', 'acme/cms ^6'], $result->blockers);
	}

	/**
	 * Check a compatibility package stays on its major and respects reverse requirements
	 */
	public function testCompatibilityPackageStaysOnItsMajorAndRespectsReverseRequirements(): void
	{
		$checker = $this->checker([
			'acme/framework' => [
				'5.2.22' => ['php' => '^8.1'],
				'5.4.30' => ['php' => '^8.1'],
				'6.0.0' => ['php' => '^8.3'],
			],
			'acme/cms' => [
				'5.2.5' => ['acme/framework' => '~5.2.0'],
				'5.4.0' => ['acme/framework' => '~5.4.0'],
				'6.0.0' => ['acme/framework' => '^6'],
			],
		], '8.3');

		$result = $checker->check('acme/framework', '5.2.22');

		$this->assertSame('5.4.30', $result->compatible);
		$this->assertSame('6.0.0', $result->latest);
	}

	/**
	 * Check a reverse requirement blocks a major upgrade of another compatibility package
	 */
	public function testReverseRequirementBlocksAMajorUpgradeOfAnotherCorePackage(): void
	{
		$checker = $this->checker([
			'acme/framework' => [
				'5.2.22' => [],
				'6.0.0' => [],
			],
			'acme/cms' => [
				'5.2.5' => ['acme/framework' => '~5.2.0'],
			],
		], '8.3');

		$result = $checker->check('acme/framework', '5.2.22');

		$this->assertSame(CompatibilityResult::BLOCKED, $result->status);
		$this->assertSame(['acme/framework 5.x', 'acme/cms 5.x'], $result->blockers);
	}

	/**
	 * Check pre-releases are ignored when the installed release is stable
	 */
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

	/**
	 * Check a dev-branch install is compared against tagged releases
	 */
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

	/**
	 * Check an unresolvable package is unknown
	 */
	public function testUnresolvablePackageIsUnknown(): void
	{
		$result = $this->checker([])->check('acme/private', '1.0.0');

		$this->assertSame(CompatibilityResult::UNKNOWN, $result->status);
	}

	/**
	 * Check unparseable constraints do not block
	 */
	public function testUnparseableConstraintsDoNotBlock(): void
	{
		$result = $this->checker([
			'acme/recipe' => [
				'1.0.0' => [],
				'1.1.0' => ['acme/framework' => 'self.version'],
			],
		])->check('acme/recipe', '1.0.0');

		$this->assertSame('1.1.0', $result->compatible);
	}

	/**
	 * Check a pre-release of a compatibility package does not unlock an update
	 */
	public function testPreReleaseOfACompatibilityPackageDoesNotUnlockAnUpdate(): void
	{
		$result = $this->checker([
			'acme/module' => [
				'1.0.0' => [],
				'2.0.0' => [],
			],
			'acme/framework' => [
				'5.2.22' => ['acme/module' => '^1'],
				'5.3.0-beta1' => ['acme/module' => '^1 || ^2'],
			],
		], '8.3', ['packages' => [
			['name' => 'acme/framework', 'version' => '5.2.22', 'require' => ['acme/module' => '^1']],
		]])->check('acme/module', '1.0.0');

		$this->assertSame(CompatibilityResult::BLOCKED, $result->status);
		$this->assertSame(['acme/framework 5.x'], $result->blockers);
	}

	/**
	 * Check a compatibility release that needs a newer PHP does not unlock an update
	 */
	public function testCompatibilityReleaseThatNeedsANewerPhpDoesNotUnlockAnUpdate(): void
	{
		$result = $this->checker([
			'acme/module' => [
				'1.0.0' => [],
				'2.0.0' => [],
			],
			'acme/admin' => [
				'2.2.0' => ['acme/module' => '^1'],
				'2.4.0' => ['php' => '^8.3', 'acme/module' => '^1 || ^2'],
			],
		], '8.1', ['packages' => [
			['name' => 'acme/admin', 'version' => '2.2.0', 'require' => ['acme/module' => '^1']],
		]])->check('acme/module', '1.0.0');

		$this->assertSame(CompatibilityResult::BLOCKED, $result->status);
		$this->assertSame(['acme/admin 2.x'], $result->blockers);
	}

	/**
	 * Check a named-branch install is unknown
	 */
	public function testNamedBranchInstallIsUnknown(): void
	{
		$result = $this->checker([
			'acme/lib' => ['1.5.0' => []],
		])->check('acme/lib', 'dev-main abc1234');

		$this->assertSame(CompatibilityResult::UNKNOWN, $result->status);
	}

	/**
	 * Check PHP is checked without being listed as a compatibility package
	 */
	public function testPhpIsCheckedWithoutBeingListedAsACompatibilityPackage(): void
	{
		$checker = new CompatibilityChecker(
			Platform::fromProject([], [], '8.1', ['acme/framework']),
			new ArrayVersionSource(['acme/lib' => ['1.0.0' => [], '1.1.0' => ['php' => '^8.3']]]),
		);

		$result = $checker->check('acme/lib', '1.0.0');

		$this->assertSame(CompatibilityResult::BLOCKED, $result->status);
		$this->assertSame(['php ^8.3'], $result->blockers);
	}

	/**
	 * Check a new major of an installed package that another package holds back is blocked
	 */
	public function testBlocksANewMajorOfAnInstalledPackageThatAnotherPackageHoldsBack(): void
	{
		$result = $this->checker([
			'acme/module' => [
				'1.0.0' => ['acme/assets' => '^2'],
				'1.1.0' => ['acme/assets' => '^2.4'],
				'2.0.0' => ['acme/assets' => '^3'],
			],
		], '8.1', ['packages' => [
			['name' => 'acme/framework', 'version' => '5.2.22'],
			['name' => 'acme/cms', 'version' => '5.2.5', 'require' => ['acme/assets' => '^2.1']],
			['name' => 'acme/assets', 'version' => '2.1.0'],
			['name' => 'acme/module', 'version' => '1.0.0', 'require' => ['acme/assets' => '^2']],
		]])->check('acme/module', '1.0.0');

		$this->assertSame('1.1.0', $result->compatible);
		$this->assertSame('2.0.0', $result->latest);
		$this->assertSame(CompatibilityResult::BLOCKED, $this->checker([
			'acme/module' => ['1.0.0' => [], '2.0.0' => ['acme/assets' => '^3']],
		], '8.1', ['packages' => [
			['name' => 'acme/cms', 'version' => '5.2.5', 'require' => ['acme/assets' => '^2.1']],
			['name' => 'acme/assets', 'version' => '2.1.0'],
		]])->check('acme/module', '1.0.0')->status);
	}

	/**
	 * Check a new major of a package only the updated package requires does not block
	 */
	public function testNewMajorOfAPackageOnlyTheUpdatedPackageRequiresDoesNotBlock(): void
	{
		$result = $this->checker([
			'acme/tool' => [
				'1.0.0' => ['acme/helper' => '^1'],
				'2.0.0' => ['acme/helper' => '^2'],
			],
		], '8.1', ['packages' => [
			['name' => 'acme/tool', 'version' => '1.0.0', 'require' => ['acme/helper' => '^1']],
			['name' => 'acme/helper', 'version' => '1.4.0'],
		]])->check('acme/tool', '1.0.0');

		$this->assertSame('2.0.0', $result->compatible);
	}

	/**
	 * Check the project's own composer.json requirement holds a package back
	 */
	public function testProjectRequirementHoldsAPackageBack(): void
	{
		$checker = new CompatibilityChecker(
			Platform::fromProject(
				['require' => ['acme/assets' => '^2']],
				['packages' => [['name' => 'acme/assets', 'version' => '2.1.0']]],
				'8.1',
				[],
			),
			new ArrayVersionSource(['acme/module' => ['1.0.0' => [], '2.0.0' => ['acme/assets' => '^3']]]),
		);

		$result = $checker->check('acme/module', '1.0.0');

		$this->assertSame(CompatibilityResult::BLOCKED, $result->status);
		$this->assertSame(['acme/assets ^3'], $result->blockers);
	}
}

<?php

namespace ComposerOutdated\Tests;

use ComposerOutdated\CompatibilityChecker;
use ComposerOutdated\MarkdownReport;
use ComposerOutdated\Platform;
use PHPUnit\Framework\TestCase;

class MarkdownReportTest extends TestCase
{
	/**
	 * Report over framework 5.2.22 on PHP 8.1, with one compatible, one blocked and one old package.
	 *
	 * @return MarkdownReport
	 */
	private function report(): MarkdownReport
	{
		$lock = ['packages' => [['name' => 'acme/framework', 'version' => '5.2.22']]];

		return new MarkdownReport(new CompatibilityChecker(
			Platform::fromProject([], $lock, '8.1', ['php', 'acme/framework']),
			new ArrayVersionSource([
				'acme/compatible' => [
					'1.0.0' => [],
					'1.2.0' => ['acme/framework' => '^5'],
					'2.0.0' => ['acme/framework' => '^6'],
				],
				'acme/blocked' => [
					'3.0.0' => [],
					'4.0.0' => ['php' => '^8.3', 'acme/framework' => '^6'],
				],
				'acme/old' => [
					'1.0.0' => [],
					'2.0.0' => ['php' => '^8.3'],
				],
			]),
		));
	}

	/**
	 * An entry as `composer outdated --format=json` reports it.
	 *
	 * @param string $name package name
	 * @param string $version installed version
	 * @param string $latest latest version
	 * @param array<string, mixed> $extra fields to override
	 * @return array<string, mixed>
	 */
	private function package(string $name, string $version, string $latest, array $extra = []): array
	{
		return $extra + [
			'name' => $name,
			'version' => $version,
			'latest' => $latest,
			'latest-status' => 'update-possible',
			'source' => sprintf('https://github.com/%s/tree/%s', $name, $version),
			'description' => 'A package | with a long description',
			'abandoned' => false,
		];
	}

	/**
	 * Check compatible updates are split from the rest
	 */
	public function testSplitsCompatibleUpdatesFromTheRest(): void
	{
		$markdown = $this->report()->render([
			$this->package('acme/compatible', '1.0.0', '2.0.0'),
			$this->package('acme/blocked', '3.0.0', '4.0.0'),
			$this->package('acme/private', '1.0.0', '1.1.0', ['source' => null]),
			$this->package('acme/old', '1.0.0', '2.0.0', ['abandoned' => 'acme/new']),
		]);

		$expected = <<<'MD'
| Package | Current | Compatible | Latest | Compare | Details |
| ------- | ------- | ---------- | ------ | ------- | ------- |
| [acme/compatible](https://github.com/acme/compatible) | 1.0.0 | 1.2.0 | 2.0.0 | [Compare](https://github.com/acme/compatible/compare/1.0.0...1.2.0) | A package \| with a long d… |
| :warning: [acme/old](https://github.com/acme/old) | 1.0.0 | - | 2.0.0 | [Compare](https://github.com/acme/old/compare/1.0.0...2.0.0) | **Abandoned**, use `acme/new`; blocked by `php ^8.3` |

<details>
<summary>2 outdated packages are not compatible with the current platform</summary>

| Package | Current | Latest | Compare | Blocked by |
| ------- | ------- | ------ | ------- | ---------- |
| [acme/blocked](https://github.com/acme/blocked) | 3.0.0 | 4.0.0 | [Compare](https://github.com/acme/blocked/compare/3.0.0...4.0.0) | `php ^8.3`, `acme/framework ^6` |
| acme/private | 1.0.0 | 1.1.0 | - | compatibility unknown |

</details>

MD;

		$this->assertSame($expected, $markdown);
	}

	/**
	 * Check the report for no outdated packages
	 */
	public function testNoOutdatedPackages(): void
	{
		$this->assertSame("_No compatible updates available._\n", $this->report()->render([]));
	}

	/**
	 * Check up-to-date packages are skipped
	 */
	public function testSkipsUpToDatePackages(): void
	{
		$markdown = $this->report()->render([
			$this->package('acme/compatible', '2.0.0', '2.0.0', ['latest-status' => 'up-to-date']),
		]);

		$this->assertSame("_No compatible updates available._\n", $markdown);
	}

	/**
	 * Check a dev-branch install compares from its commit
	 */
	public function testDevBranchInstallComparesFromItsCommit(): void
	{
		$markdown = $this->report()->render([
			$this->package('acme/compatible', '1.0.x-dev 61491f2', '2.0.0'),
		]);

		$this->assertStringContainsString('(https://github.com/acme/compatible/compare/61491f2...1.2.0)', $markdown);
	}
}

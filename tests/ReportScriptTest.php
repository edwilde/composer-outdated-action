<?php

namespace ComposerOutdated\Tests;

use PHPUnit\Framework\TestCase;

class ReportScriptTest extends TestCase
{
	private const PROJECT = __DIR__ . '/fixtures/project';

	/**
	 * Runs bin/report.php from the fixture project against the local p2 fixtures.
	 *
	 * @param string $outdatedFile path to the composer outdated JSON
	 * @return array{0: int, 1: string, 2: string} exit code, stdout, stderr
	 */
	private function runReport(string $outdatedFile): array
	{
		$process = proc_open(
			[PHP_BINARY, dirname(__DIR__) . '/bin/report.php', $outdatedFile],
			[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
			$pipes,
			self::PROJECT,
			['PACKAGIST_URL' => __DIR__ . '/fixtures/p2', 'TARGET_PHP_VERSION' => '8.3'],
		);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		return [proc_close($process), $stdout, $stderr];
	}

	public function testReportsFromTheInstalledList(): void
	{
		[$exit, $stdout] = $this->runReport(self::PROJECT . '/outdated.json');

		$this->assertSame(0, $exit);
		$this->assertStringContainsString('_No compatible updates available._', $stdout);
		$this->assertStringContainsString('| [acme/minified](https://github.com/acme/minified) | 1.4.0 | 2.1.0 |', $stdout);
		$this->assertStringContainsString('`silverstripe/framework ^6`', $stdout);
	}

	public function testReportsFromTheLockedList(): void
	{
		$locked = tempnam(sys_get_temp_dir(), 'outdated');
		$data = json_decode((string) file_get_contents(self::PROJECT . '/outdated.json'), true);
		file_put_contents($locked, json_encode(['locked' => $data['installed']]));

		[$exit, $stdout] = $this->runReport($locked);
		unlink($locked);

		$this->assertSame(0, $exit);
		$this->assertStringContainsString('acme/minified', $stdout);
	}

	public function testConfigPlatformPhpIsUsedOverTheTargetVersion(): void
	{
		[, $stdout] = $this->runReport(self::PROJECT . '/outdated.json');

		$this->assertStringContainsString('`php ^8.2`', $stdout);
	}

	public function testUnreadableOutdatedOutputExitsWithoutAReport(): void
	{
		[$exit, $stdout, $stderr] = $this->runReport(self::PROJECT . '/invalid.json');

		$this->assertSame(1, $exit);
		$this->assertSame('', $stdout);
		$this->assertStringContainsString('Could not read the composer outdated output.', $stderr);
	}
}

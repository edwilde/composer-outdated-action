<?php

namespace ComposerOutdated\Tests;

use ComposerOutdated\PackagistVersionSource;
use PHPUnit\Framework\TestCase;

class PackagistVersionSourceTest extends TestCase
{
	/**
	 * Source reading from the local p2 fixtures.
	 *
	 * @return PackagistVersionSource
	 */
	private function source(): PackagistVersionSource
	{
		return new PackagistVersionSource(__DIR__ . '/fixtures/p2');
	}

	/**
	 * Check minified metadata is expanded
	 */
	public function testExpandsMinifiedMetadata(): void
	{
		$versions = $this->source()->versions('acme/minified');

		$this->assertSame(['2.1.0', '2.0.0', '1.4.0'], array_column($versions, 'version'));
		$this->assertSame(['php' => '^8.2', 'acme/framework' => '^6'], $versions[1]['require']);
		$this->assertSame(['php' => '^8.1', 'acme/framework' => '^5'], $versions[2]['require']);
	}

	/**
	 * Check a missing package is null
	 */
	public function testMissingPackageIsNull(): void
	{
		$this->assertNull($this->source()->versions('acme/missing'));
	}

	/**
	 * Check names that are not package names are rejected
	 */
	public function testRejectsNamesThatAreNotPackageNames(): void
	{
		$this->assertNull($this->source()->versions('../acme/minified'));
	}
}

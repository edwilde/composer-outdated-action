<?php

namespace ComposerOutdated\Tests;

use ComposerOutdated\PackagistVersionSource;
use PHPUnit\Framework\TestCase;

class PackagistVersionSourceTest extends TestCase
{
	private function source(): PackagistVersionSource
	{
		return new PackagistVersionSource(__DIR__ . '/fixtures/p2');
	}

	public function testExpandsMinifiedMetadata(): void
	{
		$versions = $this->source()->versions('acme/minified');

		$this->assertSame(['2.1.0', '2.0.0', '1.4.0'], array_column($versions, 'version'));
		$this->assertSame(['php' => '^8.2', 'silverstripe/framework' => '^6'], $versions[1]['require']);
		$this->assertSame(['php' => '^8.1', 'silverstripe/framework' => '^5'], $versions[2]['require']);
	}

	public function testMissingPackageIsNull(): void
	{
		$this->assertNull($this->source()->versions('acme/missing'));
	}

	public function testRejectsNamesThatAreNotPackageNames(): void
	{
		$this->assertNull($this->source()->versions('../acme/minified'));
	}
}

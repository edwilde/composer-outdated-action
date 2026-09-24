<?php

/**
 * Usage: report.php <outdated.json>
 *
 * Reads composer.json and composer.lock from the working directory. Environment:
 *   TARGET_PHP_VERSION      PHP version the project runs on, used when composer.json has no config.platform.php
 *   COMPATIBILITY_PACKAGES  space-separated packages whose locked major version must be kept
 *   PACKAGIST_URL           p2 metadata endpoint, defaults to https://repo.packagist.org/p2
 *
 * Exits 1 without a report when the outdated JSON cannot be read.
 */

use ComposerOutdated\CompatibilityChecker;
use ComposerOutdated\MarkdownReport;
use ComposerOutdated\PackagistVersionSource;
use ComposerOutdated\Platform;

require dirname(__DIR__) . '/vendor/autoload.php';

const DEFAULT_COMPATIBILITY_PACKAGES = 'silverstripe/framework silverstripe/cms silverstripe/admin';

/**
 * Decodes a JSON file.
 *
 * @param string $path file path
 * @return array<mixed>|null decoded contents, or null when missing or invalid
 */
function readJson(string $path): ?array
{
	if (!is_file($path)) {
		return null;
	}
	$data = json_decode((string) file_get_contents($path), true);
	return is_array($data) ? $data : null;
}

$outdated = readJson($argv[1] ?? '');
if ($outdated === null) {
	fwrite(STDERR, "Could not read the composer outdated output.\n");
	exit(1);
}
$packages = $outdated['installed'] ?? $outdated['locked'] ?? [];

$compatibilityPackages = preg_split('/[\s,]+/', strtolower(trim(getenv('COMPATIBILITY_PACKAGES') ?: DEFAULT_COMPATIBILITY_PACKAGES)), -1, PREG_SPLIT_NO_EMPTY);
$phpVersion = getenv('TARGET_PHP_VERSION') ?: null;

$platform = Platform::fromProject(
	readJson('composer.json') ?? [],
	readJson('composer.lock') ?? [],
	$phpVersion,
	$compatibilityPackages,
);

if ($platform->php === null) {
	fwrite(STDERR, "No usable PHP version from config.platform.php or php-version; PHP requirements are not checked.\n");
}

$report = new MarkdownReport(new CompatibilityChecker($platform, new PackagistVersionSource(getenv('PACKAGIST_URL') ?: 'https://repo.packagist.org/p2')));
echo $report->render($packages);

<?php

/**
 * Usage: report.php <outdated.json>
 *
 * Reads composer.json and composer.lock from the working directory. Environment:
 *   PHP_VERSION             target PHP version, falls back to config.platform.php
 *   COMPATIBILITY_PACKAGES  space-separated packages whose locked release line must be kept
 */

use ComposerOutdated\CompatibilityChecker;
use ComposerOutdated\MarkdownReport;
use ComposerOutdated\PackagistVersionSource;
use ComposerOutdated\Platform;

require dirname(__DIR__) . '/vendor/autoload.php';

const DEFAULT_COMPATIBILITY_PACKAGES = 'php silverstripe/framework silverstripe/cms silverstripe/admin';

/**
 * Decodes a JSON file.
 *
 * @param string $path file path
 * @return array<mixed> decoded contents, or an empty array when missing or invalid
 */
function readJson(string $path): array
{
	if (!is_file($path)) {
		return [];
	}
	$data = json_decode((string) file_get_contents($path), true);
	return is_array($data) ? $data : [];
}

$outdated = readJson($argv[1] ?? '');
$packages = $outdated['installed'] ?? $outdated['locked'] ?? [];

$compatibilityPackages = preg_split('/[\s,]+/', strtolower(trim(getenv('COMPATIBILITY_PACKAGES') ?: DEFAULT_COMPATIBILITY_PACKAGES)), -1, PREG_SPLIT_NO_EMPTY);

$platform = Platform::fromProject(
	readJson('composer.json'),
	readJson('composer.lock'),
	getenv('PHP_VERSION') ?: null,
	$compatibilityPackages,
);

$report = new MarkdownReport(new CompatibilityChecker($platform, new PackagistVersionSource()));
echo $report->render($packages);

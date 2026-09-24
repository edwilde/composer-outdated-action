<?php

namespace ComposerOutdated;

/**
 * Renders `composer outdated` entries as a table of compatible updates, with everything else
 * collapsed into a `<details>` block.
 */
class MarkdownReport
{
	private const DESCRIPTION_LENGTH = 25;

	public function __construct(private CompatibilityChecker $checker)
	{
	}

	/**
	 * @param array<int, array<string, mixed>> $packages the `installed` (or `locked`) list from `composer outdated --format=json`
	 */
	public function render(array $packages): string
	{
		$open = [];
		$collapsed = [];

		foreach ($packages as $package) {
			if (($package['latest-status'] ?? null) === 'up-to-date' || !isset($package['name'], $package['version'])) {
				continue;
			}

			$result = $this->checker->check($package['name'], $package['version']);
			$abandoned = !empty($package['abandoned']);

			if ($result->status === CompatibilityResult::COMPATIBLE || $abandoned) {
				$open[] = $this->openRow($package, $result, $abandoned);
			} else {
				$collapsed[] = $this->collapsedRow($package, $result);
			}
		}

		$lines = [];
		if ($open) {
			$lines[] = '| Package | Current | Compatible | Latest | Compare | Details |';
			$lines[] = '| ------- | ------- | ---------- | ------ | ------- | ------- |';
			array_push($lines, ...$open);
		} else {
			$lines[] = '_No compatible updates available._';
		}

		if ($collapsed) {
			$count = count($collapsed);
			$lines[] = '';
			$lines[] = '<details>';
			$lines[] = sprintf(
				'<summary>%d outdated %s not compatible with the current platform</summary>',
				$count,
				$count === 1 ? 'package is' : 'packages are',
			);
			$lines[] = '';
			$lines[] = '| Package | Current | Latest | Compare | Blocked by |';
			$lines[] = '| ------- | ------- | ------ | ------- | ---------- |';
			array_push($lines, ...$collapsed);
			$lines[] = '';
			$lines[] = '</details>';
		}

		return implode("\n", $lines) . "\n";
	}

	private function openRow(array $package, CompatibilityResult $result, bool $abandoned): string
	{
		$current = $package['version'];
		$latest = $result->latest ?? ($package['latest'] ?? '');
		$compatible = $result->compatible;

		$details = $this->truncate((string) ($package['description'] ?? ''));
		if ($abandoned) {
			$replacement = is_string($package['abandoned']) ? sprintf(', use `%s`', $package['abandoned']) : '';
			$details = '**Abandoned**' . $replacement;
		}

		return $this->row([
			$this->name($package),
			$current,
			$compatible ?? '-',
			$latest,
			$this->compare($package, $current, $compatible ?? $latest),
			$details,
		]);
	}

	private function collapsedRow(array $package, CompatibilityResult $result): string
	{
		$current = $package['version'];
		$latest = $result->latest ?? ($package['latest'] ?? '');
		$blockedBy = $result->status === CompatibilityResult::UNKNOWN
			? 'compatibility unknown'
			: implode(', ', array_map(fn ($blocker) => '`' . $blocker . '`', $result->blockers));

		return $this->row([
			$this->name($package),
			$current,
			$latest,
			$this->compare($package, $current, $latest),
			$blockedBy,
		]);
	}

	private function name(array $package): string
	{
		$name = $package['name'];
		$github = $this->github($package);
		if ($github !== null) {
			$name = sprintf('[%s](%s)', $name, $github);
		}
		if (!empty($package['warning']) || !empty($package['abandoned'])) {
			$name = ':warning: ' . $name;
		}
		return $name;
	}

	private function compare(array $package, string $from, string $to): string
	{
		$github = $this->github($package);
		if ($github === null || $to === '') {
			return '-';
		}
		return sprintf('[Compare](%s/compare/%s...%s)', $github, $from, $to);
	}

	private function github(array $package): ?string
	{
		$source = $package['source'] ?? null;
		if (!is_string($source) || !str_contains($source, 'github.com')) {
			return null;
		}
		return preg_replace('#/tree.*$#', '', $source);
	}

	private function truncate(string $text): string
	{
		if (mb_strlen($text) <= self::DESCRIPTION_LENGTH) {
			return $text;
		}
		return mb_substr($text, 0, self::DESCRIPTION_LENGTH) . '…';
	}

	/**
	 * @param string[] $cells
	 */
	private function row(array $cells): string
	{
		$cells = array_map(fn ($cell) => str_replace(['|', "\r", "\n"], ['\|', ' ', ' '], (string) $cell), $cells);
		return '| ' . implode(' | ', $cells) . ' |';
	}
}

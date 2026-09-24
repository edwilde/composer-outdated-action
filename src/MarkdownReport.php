<?php

namespace ComposerOutdated;

/**
 * Renders `composer outdated` entries as a table of compatible updates, with everything else
 * collapsed into a `<details>` block.
 */
class MarkdownReport
{
	private const DESCRIPTION_LENGTH = 25;

	/**
	 * Report that sorts packages with the given checker.
	 *
	 * @param CompatibilityChecker $checker decides which section each package goes in
	 */
	public function __construct(private CompatibilityChecker $checker)
	{
	}

	/**
	 * Renders the report.
	 *
	 * @param array<int, array<string, mixed>> $packages the `installed` (or `locked`) list from `composer outdated --format=json`
	 * @return string markdown, ending in a newline
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

	/**
	 * Row for the compatible updates table.
	 *
	 * @param array<string, mixed> $package outdated package entry
	 * @param CompatibilityResult $result its compatibility
	 * @param bool $abandoned whether composer reports it as abandoned
	 * @return string markdown table row
	 */
	private function openRow(array $package, CompatibilityResult $result, bool $abandoned): string
	{
		$current = $package['version'];
		$latest = $result->latest ?? ($package['latest'] ?? '');
		$compatible = $result->compatible;

		$details = $this->truncate((string) ($package['description'] ?? ''));
		if ($abandoned) {
			$replacement = is_string($package['abandoned']) ? sprintf(', use `%s`', $package['abandoned']) : '';
			$details = '**Abandoned**' . $replacement . $this->abandonedCompatibility($result);
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

	/**
	 * Why an abandoned package has no compatible update, for its Details cell.
	 *
	 * @param CompatibilityResult $result its compatibility
	 * @return string `; blocked by ...`, `; compatibility unknown`, or empty when it has a compatible update
	 */
	private function abandonedCompatibility(CompatibilityResult $result): string
	{
		return match ($result->status) {
			CompatibilityResult::BLOCKED => '; blocked by ' . $this->blockedBy($result),
			CompatibilityResult::UNKNOWN => '; compatibility unknown',
			default => '',
		};
	}

	/**
	 * Blockers as a comma-separated list of code spans.
	 *
	 * @param CompatibilityResult $result a blocked result
	 * @return string markdown
	 */
	private function blockedBy(CompatibilityResult $result): string
	{
		return implode(', ', array_map(fn ($blocker) => '`' . $blocker . '`', $result->blockers));
	}

	/**
	 * Row for the collapsed table of packages without a compatible update.
	 *
	 * @param array<string, mixed> $package outdated package entry
	 * @param CompatibilityResult $result its compatibility
	 * @return string markdown table row
	 */
	private function collapsedRow(array $package, CompatibilityResult $result): string
	{
		$current = $package['version'];
		$latest = $result->latest ?? ($package['latest'] ?? '');
		$blockedBy = $result->status === CompatibilityResult::UNKNOWN
			? 'compatibility unknown'
			: $this->blockedBy($result);

		return $this->row([
			$this->name($package),
			$current,
			$latest,
			$this->compare($package, $current, $latest),
			$blockedBy,
		]);
	}

	/**
	 * Package name, linked to its GitHub repository and flagged when composer warns about it.
	 *
	 * @param array<string, mixed> $package outdated package entry
	 * @return string markdown
	 */
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

	/**
	 * GitHub compare link between two versions.
	 *
	 * @param array<string, mixed> $package outdated package entry
	 * @param string $from version to compare from
	 * @param string $to version to compare to
	 * @return string markdown link, or `-` when the source is not on GitHub
	 */
	private function compare(array $package, string $from, string $to): string
	{
		$github = $this->github($package);
		if ($github === null || $to === '') {
			return '-';
		}
		return sprintf('[Compare](%s/compare/%s...%s)', $github, $from, $to);
	}

	/**
	 * GitHub repository URL from the package's source URL.
	 *
	 * @param array<string, mixed> $package outdated package entry
	 * @return string|null repository URL, or null when the source is not on GitHub
	 */
	private function github(array $package): ?string
	{
		$source = $package['source'] ?? null;
		if (!is_string($source) || !str_contains($source, 'github.com')) {
			return null;
		}
		return preg_replace('#/tree.*$#', '', $source);
	}

	/**
	 * Shortens a description to fit the table.
	 *
	 * @param string $text description
	 * @return string at most DESCRIPTION_LENGTH characters plus an ellipsis
	 */
	private function truncate(string $text): string
	{
		if (mb_strlen($text) <= self::DESCRIPTION_LENGTH) {
			return $text;
		}
		return mb_substr($text, 0, self::DESCRIPTION_LENGTH) . '…';
	}

	/**
	 * Markdown table row, with pipes and line breaks in cells escaped.
	 *
	 * @param string[] $cells cell contents
	 * @return string markdown table row
	 */
	private function row(array $cells): string
	{
		$cells = array_map(fn ($cell) => str_replace(['|', "\r", "\n"], ['\|', ' ', ' '], (string) $cell), $cells);
		return '| ' . implode(' | ', $cells) . ' |';
	}
}

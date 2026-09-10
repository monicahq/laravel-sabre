<?php

namespace LaravelSabre\Tests\Unit;

use LaravelSabre\Tests\FeatureTestCase;

/**
 * Static-analysis suppressions are capped and must stay narrow (SC-009, constitution IV).
 *
 * The 1.x baseline was 22 inline annotations in src/ plus two config-level entries.
 */
class SuppressionBudgetTest extends FeatureTestCase
{
    private const BUDGET = 11;

    private const BASELINE = 22;

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return array<int, string>
     */
    private function inlineSuppressions(): array
    {
        $found = [];

        /** @var iterable<\SplFileInfo> $files */
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root().'/src'));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            preg_match_all('/@(psalm-suppress|phpstan-ignore[a-z-]*)([^\n]*)/', $contents, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $found[] = $file->getFilename().': @'.$match[1].$match[2];
            }
        }

        return $found;
    }

    public function test_the_inline_suppression_budget_is_respected()
    {
        $suppressions = $this->inlineSuppressions();

        $this->assertLessThanOrEqual(
            self::BUDGET,
            count($suppressions),
            'suppression budget exceeded: '.implode(', ', $suppressions)
        );
    }

    public function test_the_count_is_at_most_half_the_1x_baseline()
    {
        $this->assertLessThanOrEqual(
            (int) floor(self::BASELINE / 2),
            count($this->inlineSuppressions()),
            'SC-009 requires at least a 50% reduction from the 1.x baseline of '.self::BASELINE
        );
    }

    public function test_no_suppression_is_broader_than_a_single_symbol()
    {
        $broad = array_values(array_filter($this->inlineSuppressions(), function (string $suppression): bool {
            return str_contains($suppression, '*')
                || preg_match('/@(psalm-suppress|phpstan-ignore[a-z-]*)\s*$/', $suppression) === 1;
        }));

        $this->assertSame([], $broad, 'every suppression must name one issue on one symbol');
    }

    public function test_no_analyser_configuration_suppresses_a_whole_file_or_directory()
    {
        $phpstan = (string) file_get_contents($this->root().'/phpstan.neon');
        $psalm = (string) file_get_contents($this->root().'/psalm.xml');

        $this->assertStringNotContainsString('ignoreErrors', $phpstan);
        $this->assertStringNotContainsString('errorLevel type="suppress"', $psalm);
    }
}

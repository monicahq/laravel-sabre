<?php

namespace LaravelSabre\Tests\Unit;

use LaravelSabre\Tests\FeatureTestCase;

/**
 * The supported matrix must be declared identically in composer.json and in the tests workflow
 * (constitution II). A change to one without the other fails here rather than in CI.
 */
class SupportedMatrixTest extends FeatureTestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_composer_and_the_workflow_declare_the_same_matrix()
    {
        $composerMajors = $this->composerLaravelMajors();
        $workflowMajors = $this->workflowLaravelMajors();

        $this->assertNotEmpty($composerMajors, 'composer.json must constrain illuminate/support');
        $this->assertNotEmpty($workflowMajors, 'the workflow must list laravel-versions');

        if (count($composerMajors) === 1) {
            // The tests workflow narrows the constraint to the cell being built, with
            // `composer require "illuminate/support:13.*" --no-update`, so during a matrix run the
            // file names one version rather than the whole supported range. The guarantee that still
            // holds there, and the one worth asserting, is that the pinned version is inside the
            // declared matrix.
            $this->assertContains(
                $composerMajors[0],
                $workflowMajors,
                'the pinned Laravel version is not one the workflow declares'
            );

            return;
        }

        $this->assertSame(
            $workflowMajors,
            $composerMajors,
            'composer.json and tests.yml disagree about the supported Laravel versions'
        );
    }

    /**
     * Major versions named by the illuminate/support constraint, whether it lists a range such as
     * "^11.0 || ^12.0 || ^13.0" or a single pinned version such as "13.*".
     *
     * @return array<int, string>
     */
    private function composerLaravelMajors(): array
    {
        $composer = json_decode((string) file_get_contents($this->root().'/composer.json'), true);
        $constraint = (string) $composer['require']['illuminate/support'];

        preg_match_all('/(\d+)\.(?:\d+|\*)/', $constraint, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return array<int, string>
     */
    private function workflowLaravelMajors(): array
    {
        $workflow = (string) file_get_contents($this->root().'/.github/workflows/tests.yml');

        preg_match('/laravel-versions:\s*"\[(.*?)\]"/', $workflow, $versions);
        preg_match_all('/(\d+)\.\*/', $versions[1] ?? '', $matches);

        return array_values(array_unique($matches[1]));
    }

    public function test_the_php_versions_are_declared_in_the_workflow()
    {
        $workflow = (string) file_get_contents($this->root().'/.github/workflows/tests.yml');

        preg_match('/php-versions:\s*"\[(.*?)\]"/', $workflow, $versions);
        preg_match_all("/'([\d.]+)'/", $versions[1] ?? '', $found);

        $this->assertSame(['8.2', '8.3', '8.4'], $found[1]);
    }

    public function test_the_rebuild_introduced_no_new_runtime_dependency()
    {
        $composer = json_decode((string) file_get_contents($this->root().'/composer.json'), true);

        $required = array_keys($composer['require']);
        sort($required);

        $this->assertSame(
            ['illuminate/support', 'sabre/dav', 'thecodingmachine/safe'],
            $required,
            'a new runtime dependency is a supply-chain and matrix cost that must be justified'
        );
    }
}

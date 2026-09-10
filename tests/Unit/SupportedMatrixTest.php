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
        $composer = json_decode((string) file_get_contents($this->root().'/composer.json'), true);
        $workflow = (string) file_get_contents($this->root().'/.github/workflows/tests.yml');

        $constraint = $composer['require']['illuminate/support'];
        preg_match_all('/\^(\d+)\.0/', $constraint, $composerMajors);

        preg_match('/laravel-versions:\s*"\[(.*?)\]"/', $workflow, $workflowVersions);
        preg_match_all('/(\d+)\.\*/', $workflowVersions[1] ?? '', $workflowMajors);

        $this->assertNotEmpty($composerMajors[1], 'composer.json must constrain illuminate/support');
        $this->assertNotEmpty($workflowMajors[1], 'the workflow must list laravel-versions');
        $this->assertSame(
            $composerMajors[1],
            $workflowMajors[1],
            'composer.json and tests.yml disagree about the supported Laravel versions'
        );
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

        $this->assertSame(
            ['illuminate/support', 'sabre/dav', 'thecodingmachine/safe'],
            array_keys($composer['require']),
            'a new runtime dependency is a supply-chain and matrix cost that must be justified'
        );
    }
}

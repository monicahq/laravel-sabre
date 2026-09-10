<?php

namespace LaravelSabre\Tests\Unit;

use LaravelSabre\Tests\FeatureTestCase;

/**
 * The README must document installation, every configuration setting and every public call, in the
 * same change that introduces them (FR-028, constitution V), so a developer can reach a first
 * PROPFIND without reading the package source (User Story 5 scenario 3).
 */
class DocumentationTest extends FeatureTestCase
{
    private function readme(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/README.md');
    }

    public function test_the_readme_documents_the_whole_public_surface()
    {
        $readme = $this->readme();

        foreach ([
            'composer require monicahq/laravel-sabre',
            'vendor:publish',
            'laravelsabre-config',
            'LaravelSabre::nodes',
            'LaravelSabre::plugins',
            'LaravelSabre::plugin',
            'LaravelSabre::auth',
            'LaravelSabre::principal',
            'AuthBackend',
            'PROPFIND',
            "route('sabre.dav')",
            'MIGRATION.md',
        ] as $needle) {
            $this->assertStringContainsString($needle, $readme, 'the README must document '.$needle);
        }
    }

    public function test_the_readme_documents_every_configuration_key()
    {
        $readme = $this->readme();
        $defaults = require dirname(__DIR__, 2).'/config/laravelsabre.php';

        foreach (array_keys($defaults) as $key) {
            $this->assertStringContainsString(
                '`'.$key.'`',
                $readme,
                'the README must document the '.$key.' configuration option'
            );
        }
    }

    public function test_the_migration_guide_states_that_no_deployment_change_is_needed()
    {
        $guide = (string) file_get_contents(dirname(__DIR__, 2).'/MIGRATION.md');

        $this->assertStringContainsString('No change to your configuration files', $guide);
        $this->assertStringContainsString('LARAVELSABRE_ENABLED', $guide);
        $this->assertStringContainsString('sabre.dav', $guide);
    }
}

<?php

namespace LaravelSabre\Tests\Unit;

use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\FeatureTestCase;

/**
 * Elements the rebuild removed must fail visibly rather than keep working through a deprecated
 * pass-through, and each is listed in MIGRATION.md (FR-031).
 */
class RemovedSurfaceTest extends FeatureTestCase
{
    public function test_the_1x_accessors_are_gone()
    {
        $this->assertFalse(method_exists(LaravelSabre::class, 'getNodes'));
        $this->assertFalse(method_exists(LaravelSabre::class, 'getPlugins'));
    }

    public function test_calling_a_removed_accessor_fails_immediately()
    {
        $this->expectException(\Error::class);

        /** @phpstan-ignore-next-line calling a removed method on purpose */
        LaravelSabre::getNodes();
    }

    public function test_the_removed_exception_class_is_gone()
    {
        $this->assertFalse(class_exists('LaravelSabre\Exception\InvalidStateException'));
        $this->assertFileDoesNotExist(__DIR__.'/../../src/Exception/InvalidStateException.php');
    }

    public function test_every_removed_element_is_named_in_the_migration_guide()
    {
        $guide = (string) file_get_contents(__DIR__.'/../../MIGRATION.md');

        foreach (['getNodes', 'getPlugins', 'InvalidStateException'] as $removed) {
            $this->assertStringContainsString($removed, $guide, $removed.' must appear in MIGRATION.md');
        }
    }
}

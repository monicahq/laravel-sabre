<?php

namespace LaravelSabre\Tests\Unit;

use Illuminate\Container\Container;
use LaravelSabre\LaravelSabre;
use LaravelSabre\Registry;
use LaravelSabre\Tests\FeatureTestCase;
use Sabre\DAV\SimpleCollection;

/**
 * Registration must live with the application instance, not with the process, so that two
 * applications in one process keep separate registrations without an explicit reset
 * (FR-034, User Story 5 scenario 5).
 */
class RegistryIsolationTest extends FeatureTestCase
{
    protected function tearDown(): void
    {
        Container::setInstance($this->app);

        parent::tearDown();
    }

    public function test_two_applications_in_one_process_keep_separate_registrations()
    {
        $first = new Container();
        $first->singleton(Registry::class);
        Container::setInstance($first);

        LaravelSabre::nodes([new SimpleCollection('first')]);
        $this->assertCount(1, $first->make(Registry::class)->resolveNodes());

        $second = new Container();
        $second->singleton(Registry::class);
        Container::setInstance($second);

        $this->assertSame(
            [],
            $second->make(Registry::class)->resolveNodes(),
            'a second application must not see the first application registration'
        );

        LaravelSabre::nodes([new SimpleCollection('second-a'), new SimpleCollection('second-b')]);
        $this->assertCount(2, $second->make(Registry::class)->resolveNodes());

        Container::setInstance($first);
        $this->assertCount(
            1,
            $first->make(Registry::class)->resolveNodes(),
            'the first application registration must survive the second application'
        );
    }

    public function test_no_registration_state_is_held_statically()
    {
        $properties = (new \ReflectionClass(LaravelSabre::class))->getProperties(\ReflectionProperty::IS_STATIC);

        $this->assertSame([], $properties, 'the entry point must hold no static state');
    }
}

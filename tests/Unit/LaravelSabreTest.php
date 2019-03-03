<?php

namespace LaravelSabre\Tests\Unit;

use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\FeatureTestCase;

class LaravelSabreTest extends FeatureTestCase
{
    /**
     * @return void
     */
    protected function tearDown() : void
    {
        LaravelSabre::clear();

        parent::tearDown();
    }

    public function test_add_node_null()
    {
        LaravelSabre::nodes(null);

        $this->assertCount(0, LaravelSabre::getNodes());
    }

    public function test_add_node_collection()
    {
        LaravelSabre::nodes([
            'test',
        ]);

        $this->assertCount(1, LaravelSabre::getNodes());
    }

    public function test_add_node_callback()
    {
        LaravelSabre::nodes(function () {
            return ['test'];
        });

        $this->assertCount(1, LaravelSabre::getNodes());
    }

    public function test_add_plugins_null()
    {
        LaravelSabre::plugins(null);

        $this->assertCount(0, LaravelSabre::getPlugins());
    }

    public function test_add_plugin_null()
    {
        LaravelSabre::plugin(null);

        $this->assertCount(1, LaravelSabre::getPlugins());
    }

    public function test_add_plugins_collection()
    {
        LaravelSabre::plugins([
            'test',
        ]);

        $this->assertCount(1, LaravelSabre::getPlugins());
    }

    public function test_add_plugin_collection()
    {
        LaravelSabre::plugin('test');

        $this->assertCount(1, LaravelSabre::getPlugins());
    }

    public function test_add_plugins_callback()
    {
        LaravelSabre::plugins(function () {
            return ['test'];
        });

        $this->assertCount(1, LaravelSabre::getPlugins());
    }
}

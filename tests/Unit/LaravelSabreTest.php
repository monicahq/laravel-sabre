<?php

namespace LaravelSabre\Tests\Unit;

use LaravelSabre\Exception\InvalidStateException;
use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\FeatureTestCase;

class LaravelSabreTest extends FeatureTestCase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        LaravelSabre::clear();

        parent::tearDown();
    }

    public function test_add_node_null()
    {
        LaravelSabre::nodes(null);

        if (function_exists('PHPUnit\Framework\assertIsArray')) {
            $this->assertIsArray(LaravelSabre::getNodes());
        }
        $this->assertCount(0, LaravelSabre::getNodes());
        $this->assertEquals([], LaravelSabre::getNodes());
    }

    public function test_add_node_collection()
    {
        LaravelSabre::nodes([
            'test',
        ]);

        if (function_exists('PHPUnit\Framework\assertIsArray')) {
            $this->assertIsArray(LaravelSabre::getNodes());
        }
        $this->assertCount(1, LaravelSabre::getNodes());
        $this->assertEquals(['test'], LaravelSabre::getNodes());
    }

    public function test_add_node_callback()
    {
        LaravelSabre::nodes(function () {
            return ['test'];
        });

        if (function_exists('PHPUnit\Framework\assertIsArray')) {
            $this->assertIsArray(LaravelSabre::getNodes());
        }
        $this->assertCount(1, LaravelSabre::getNodes());
        $this->assertEquals(['test'], LaravelSabre::getNodes());
    }

    public function test_add_plugins_null()
    {
        LaravelSabre::plugins(null);

        if (function_exists('PHPUnit\Framework\assertIsArray')) {
            $this->assertIsArray(LaravelSabre::getNodes());
        }
        $this->assertCount(0, LaravelSabre::getPlugins());
        $this->assertEquals([], LaravelSabre::getPlugins());
    }

    public function test_add_plugin_null()
    {
        LaravelSabre::plugin(null);

        if (function_exists('PHPUnit\Framework\assertIsArray')) {
            $this->assertIsArray(LaravelSabre::getNodes());
        }
        $this->assertCount(1, LaravelSabre::getPlugins());
        $this->assertEquals([null], LaravelSabre::getPlugins());
    }

    public function test_add_plugins_collection()
    {
        LaravelSabre::plugins([
            'test',
        ]);

        if (function_exists('PHPUnit\Framework\assertIsArray')) {
            $this->assertIsArray(LaravelSabre::getNodes());
        }
        $this->assertCount(1, LaravelSabre::getPlugins());
        $this->assertEquals(['test'], LaravelSabre::getPlugins());
    }

    public function test_add_plugin_collection()
    {
        LaravelSabre::plugin('test');

        if (function_exists('PHPUnit\Framework\assertIsArray')) {
            $this->assertIsArray(LaravelSabre::getNodes());
        }
        $this->assertCount(1, LaravelSabre::getPlugins());
        $this->assertEquals(['test'], LaravelSabre::getPlugins());
    }

    public function test_add_plugin_collection2()
    {
        LaravelSabre::plugin('test');
        LaravelSabre::plugin('yeah');

        if (function_exists('PHPUnit\Framework\assertIsArray')) {
            $this->assertIsArray(LaravelSabre::getNodes());
        }
        $this->assertCount(2, LaravelSabre::getPlugins());
        $this->assertEquals(['test', 'yeah'], LaravelSabre::getPlugins());
    }

    public function test_add_plugins_callback()
    {
        LaravelSabre::plugins(function () {
            return ['test'];
        });

        if (function_exists('PHPUnit\Framework\assertIsArray')) {
            $this->assertIsArray(LaravelSabre::getNodes());
        }
        $this->assertCount(1, LaravelSabre::getPlugins());
        $this->assertEquals(['test'], LaravelSabre::getPlugins());
    }

    public function test_add_plugin_exception()
    {
        LaravelSabre::plugins(function () {
            return ['test'];
        });

        $this->expectException(InvalidStateException::class);

        LaravelSabre::plugin('test');
    }

    public function test_clear()
    {
        LaravelSabre::plugins(['test']);

        if (function_exists('PHPUnit\Framework\assertIsArray')) {
            $this->assertIsArray(LaravelSabre::getNodes());
        }
        $this->assertCount(1, LaravelSabre::getPlugins());
        $this->assertEquals(['test'], LaravelSabre::getPlugins());

        LaravelSabre::clear();

        if (function_exists('PHPUnit\Framework\assertIsArray')) {
            $this->assertIsArray(LaravelSabre::getNodes());
        }
        $this->assertCount(0, LaravelSabre::getPlugins());
        $this->assertEquals([], LaravelSabre::getPlugins());
    }

    public function test_add_auth_callback()
    {
        $this->assertTrue(LaravelSabre::check(new \Illuminate\Http\Request()));

        LaravelSabre::auth(function () {
            return false;
        });

        $this->assertIsBool(LaravelSabre::check(new \Illuminate\Http\Request()));
        $this->assertFalse(LaravelSabre::check(new \Illuminate\Http\Request()));
    }
}

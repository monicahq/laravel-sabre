<?php

namespace LaravelSabre\Tests\Unit;

use LaravelSabre\Exception\LaravelSabreException;
use LaravelSabre\Registry;
use LaravelSabre\Tests\FeatureTestCase;
use Sabre\CalDAV\Plugin as CalDAVPlugin;
use Sabre\CardDAV\Plugin as CardDAVPlugin;
use Sabre\DAV\Browser\Plugin as BrowserPlugin;
use Sabre\DAV\SimpleCollection;
use Sabre\DAV\Tree;

class RegistryTest extends FeatureTestCase
{
    private function registry(): Registry
    {
        return $this->app->make(Registry::class);
    }

    public function test_nodes_defaults_to_an_empty_collection()
    {
        $this->assertSame([], $this->registry()->resolveNodes());
    }

    public function test_nodes_accepts_an_array()
    {
        $node = new SimpleCollection('one');

        $this->registry()->nodes([$node]);

        $this->assertSame([$node], $this->registry()->resolveNodes());
    }

    public function test_nodes_accepts_a_single_node()
    {
        $node = new SimpleCollection('one');

        $this->registry()->nodes($node);

        $this->assertSame($node, $this->registry()->resolveNodes());
    }

    public function test_nodes_accepts_a_prebuilt_tree()
    {
        $tree = new Tree(new SimpleCollection('root'));

        $this->registry()->nodes($tree);

        $this->assertSame($tree, $this->registry()->resolveNodes());
    }

    public function test_nodes_accepts_a_provider_closure()
    {
        $node = new SimpleCollection('one');

        $this->registry()->nodes(function () use ($node) {
            return [$node];
        });

        $this->assertSame([$node], $this->registry()->resolveNodes());
    }

    public function test_nodes_accepts_null_as_an_empty_collection()
    {
        $this->registry()->nodes(null);

        $this->assertSame([], $this->registry()->resolveNodes());
    }

    public function test_nodes_registration_replaces_the_previous_one()
    {
        $first = new SimpleCollection('first');
        $second = new SimpleCollection('second');

        $this->registry()->nodes([$first]);
        $this->registry()->nodes([$second]);

        $this->assertSame([$second], $this->registry()->resolveNodes());
    }

    public function test_plugin_registered_alone()
    {
        $plugin = new CardDAVPlugin();

        $this->registry()->plugin($plugin);

        $this->assertSame([$plugin], $this->registry()->resolvePlugins());
    }

    public function test_plugins_registered_in_bulk()
    {
        $card = new CardDAVPlugin();
        $cal = new CalDAVPlugin();

        $this->registry()->plugins([$card, $cal]);

        $this->assertSame([$card, $cal], $this->registry()->resolvePlugins());
    }

    public function test_plugins_registered_as_a_provider_closure()
    {
        $card = new CardDAVPlugin();

        $this->registry()->plugins(function () use ($card) {
            return [$card];
        });

        $this->assertSame([$card], $this->registry()->resolvePlugins());
    }

    public function test_single_plugin_after_a_bulk_closure_registration()
    {
        $card = new CardDAVPlugin();
        $cal = new CalDAVPlugin();

        $this->registry()->plugins(function () use ($card) {
            return [$card];
        });
        $this->registry()->plugin($cal);

        $this->assertSame([$card, $cal], $this->registry()->resolvePlugins());
    }

    public function test_single_plugin_before_a_bulk_closure_registration()
    {
        $card = new CardDAVPlugin();
        $cal = new CalDAVPlugin();

        $this->registry()->plugin($cal);
        $this->registry()->plugins(function () use ($card) {
            return [$card];
        });

        $this->assertSame([$cal, $card], $this->registry()->resolvePlugins());
    }

    public function test_single_plugin_between_two_bulk_registrations()
    {
        $card = new CardDAVPlugin();
        $cal = new CalDAVPlugin();
        $browser = new BrowserPlugin();

        $this->registry()->plugins([$card]);
        $this->registry()->plugin($browser);
        $this->registry()->plugins(function () use ($cal) {
            return [$cal];
        });

        $this->assertSame([$card, $browser, $cal], $this->registry()->resolvePlugins());
    }

    public function test_plugin_provider_may_yield_a_generator()
    {
        $card = new CardDAVPlugin();
        $cal = new CalDAVPlugin();

        $this->registry()->plugins(function () use ($card, $cal) {
            yield $card;
            yield $cal;
        });

        $this->assertSame([$card, $cal], $this->registry()->resolvePlugins());
    }

    public function test_plugin_providers_are_resolved_on_each_resolution_and_not_at_registration()
    {
        $calls = 0;

        $this->registry()->plugins(function () use (&$calls) {
            $calls++;

            return [new CardDAVPlugin()];
        });

        $this->assertSame(0, $calls, 'a provider must not run at registration time');

        $this->registry()->resolvePlugins();
        $this->assertSame(1, $calls);

        $this->registry()->resolvePlugins();
        $this->assertSame(2, $calls, 'a provider runs once per resolution, so once per request');
    }

    public function test_plugins_accepts_null_without_registering_anything()
    {
        $this->registry()->plugins(null);

        $this->assertSame([], $this->registry()->resolvePlugins());
    }

    public function test_an_invalid_plugin_entry_is_rejected_at_registration_time()
    {
        $this->expectException(LaravelSabreException::class);
        $this->expectExceptionMessage('string');

        $this->registry()->plugin('not-a-plugin');
    }

    public function test_an_invalid_plugin_from_a_provider_is_rejected_at_resolution_time()
    {
        $this->registry()->plugins(function () {
            return ['not-a-plugin'];
        });

        $this->expectException(LaravelSabreException::class);

        $this->registry()->resolvePlugins();
    }

    public function test_access_rule_admits_when_none_is_registered()
    {
        $this->assertTrue($this->registry()->check($this->app->make('request')));
    }

    public function test_access_rule_is_single_valued()
    {
        $this->registry()->auth(function (): bool {
            return false;
        });
        $this->registry()->auth(function (): bool {
            return true;
        });

        $this->assertTrue($this->registry()->check($this->app->make('request')));
    }

    public function test_access_rule_receives_the_request()
    {
        $seen = null;
        $request = $this->app->make('request');

        $this->registry()->auth(function ($given) use (&$seen): bool {
            $seen = $given;

            return true;
        });

        $this->registry()->check($request);

        $this->assertSame($request, $seen);
    }

    public function test_principal_mapper_is_single_valued_and_absent_by_default()
    {
        $this->assertNull($this->registry()->principalMapper());

        $this->registry()->principal(function (): string {
            return 'first';
        });
        $this->registry()->principal(function (): string {
            return 'second';
        });

        $mapper = $this->registry()->principalMapper();
        $this->assertNotNull($mapper);
        $this->assertSame('second', $mapper(null));
    }

    public function test_clear_resets_every_registration()
    {
        $this->registry()->nodes([new SimpleCollection('one')]);
        $this->registry()->plugin(new CardDAVPlugin());
        $this->registry()->auth(function (): bool {
            return false;
        });
        $this->registry()->principal(function (): string {
            return 'someone';
        });

        $this->registry()->clear();

        $this->assertSame([], $this->registry()->resolveNodes());
        $this->assertSame([], $this->registry()->resolvePlugins());
        $this->assertTrue($this->registry()->check($this->app->make('request')));
        $this->assertNull($this->registry()->principalMapper());
    }

    public function test_nodes_rejects_a_value_that_cannot_be_a_tree()
    {
        $this->expectException(LaravelSabreException::class);
        $this->expectExceptionMessage('string');

        $this->registry()->nodes('not-a-tree');
    }

    public function test_nodes_accepts_a_provider_returning_a_prebuilt_tree()
    {
        $tree = new Tree(new SimpleCollection('root'));

        $this->registry()->nodes(function () use ($tree) {
            return $tree;
        });

        $this->assertSame($tree, $this->registry()->resolveNodes());
    }

    public function test_plugins_accepts_a_single_plugin_instance()
    {
        $plugin = new CardDAVPlugin();

        $this->registry()->plugins($plugin);

        $this->assertSame([$plugin], $this->registry()->resolvePlugins());
    }

    public function test_a_provider_may_return_a_single_plugin()
    {
        $plugin = new CardDAVPlugin();

        $this->registry()->plugins(function () use ($plugin) {
            return $plugin;
        });

        $this->assertSame([$plugin], $this->registry()->resolvePlugins());
    }

    public function test_a_provider_may_return_nothing()
    {
        $this->registry()->plugins(function () {
            return null;
        });

        $this->assertSame([], $this->registry()->resolvePlugins());
    }

    public function test_nodes_accepts_a_traversable()
    {
        $node = new SimpleCollection('one');

        $this->registry()->nodes(new \ArrayIterator([$node]));

        $this->assertSame([$node], $this->registry()->resolveNodes());
    }

    public function test_registration_calls_are_chainable()
    {
        $registry = $this->registry()
            ->nodes([new SimpleCollection('one')])
            ->plugin(new CardDAVPlugin())
            ->auth(function (): bool {
                return true;
            })
            ->principal(function (): string {
                return 'someone';
            });

        $this->assertInstanceOf(Registry::class, $registry);
    }
}

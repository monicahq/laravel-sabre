<?php

namespace LaravelSabre\Tests\Unit;

use Illuminate\Http\Request;
use LaravelSabre\LaravelSabre;
use LaravelSabre\Registry;
use LaravelSabre\Tests\Authenticated;
use LaravelSabre\Tests\FeatureTestCase;
use Sabre\CardDAV\Plugin as CardDAVPlugin;
use Sabre\DAV\SimpleCollection;

class LaravelSabreTest extends FeatureTestCase
{
    protected function tearDown(): void
    {
        LaravelSabre::clear();

        parent::tearDown();
    }

    public function test_the_entry_point_writes_to_the_application_registry()
    {
        $node = new SimpleCollection('one');
        $plugin = new CardDAVPlugin();

        LaravelSabre::nodes([$node]);
        LaravelSabre::plugin($plugin);

        $registry = $this->app->make(Registry::class);

        $this->assertSame([$node], $registry->resolveNodes());
        $this->assertSame([$plugin], $registry->resolvePlugins());
    }

    public function test_every_registration_call_returns_the_registry_for_chaining()
    {
        $this->assertInstanceOf(Registry::class, LaravelSabre::nodes([]));
        $this->assertInstanceOf(Registry::class, LaravelSabre::plugins([]));
        $this->assertInstanceOf(Registry::class, LaravelSabre::plugin(new CardDAVPlugin()));
        $this->assertInstanceOf(Registry::class, LaravelSabre::auth(function (): bool {
            return true;
        }));
        $this->assertInstanceOf(Registry::class, LaravelSabre::principal(function (): string {
            return 'someone';
        }));
    }

    public function test_check_admits_when_no_rule_is_registered()
    {
        $this->assertTrue(LaravelSabre::check(new Request()));
    }

    public function test_check_reflects_the_registered_rule()
    {
        LaravelSabre::auth(function (): bool {
            return false;
        });

        $this->assertFalse(LaravelSabre::check(new Request()));
    }

    public function test_principal_mapper_is_used_for_the_signed_in_user()
    {
        LaravelSabre::principal(function ($user): string {
            return 'uid/'.$user->getAuthIdentifier();
        });

        $mapper = $this->app->make(Registry::class)->principalMapper();
        $user = new Authenticated();

        $this->assertNotNull($mapper);
        $this->assertSame('uid/auth-identifier', $mapper($user));
    }

    public function test_clear_resets_the_registration()
    {
        LaravelSabre::plugin(new CardDAVPlugin());

        LaravelSabre::clear();

        $this->assertSame([], $this->app->make(Registry::class)->resolvePlugins());
    }

    public function test_the_removed_1x_accessors_are_gone()
    {
        $this->assertFalse(method_exists(LaravelSabre::class, 'getNodes'));
        $this->assertFalse(method_exists(LaravelSabre::class, 'getPlugins'));
    }
}

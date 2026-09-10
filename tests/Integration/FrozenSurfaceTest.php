<?php

namespace LaravelSabre\Tests\Integration;

use Illuminate\Support\Facades\Route;
use LaravelSabre\Http\Auth\AuthBackend;
use LaravelSabre\Http\Middleware\Authorize;
use LaravelSabre\LaravelSabre;
use LaravelSabre\LaravelSabreServiceProvider;
use LaravelSabre\Tests\Authenticated;
use LaravelSabre\Tests\Sabre\DAVACL\PrincipalBackend\Mock as PrincipalBackend;
use LaravelSabre\Tests\Support\CapturePrincipalPlugin;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAVACL\PrincipalCollection;

/**
 * The deployment-facing surface is frozen, so upgrading needs no change to configuration,
 * environment variables or client-facing URLs (FR-030, SC-011).
 */
class FrozenSurfaceTest extends IntegrationTestCase
{
    public function test_the_config_file_name_and_the_1x_keys_are_unchanged()
    {
        $this->assertFileExists(__DIR__.'/../../config/laravelsabre.php');

        $defaults = require __DIR__.'/../../config/laravelsabre.php';

        foreach (['domain', 'path', 'enabled', 'middleware'] as $key) {
            $this->assertArrayHasKey($key, $defaults, $key.' is part of the frozen surface');
        }
    }

    public function test_the_environment_variable_is_unchanged()
    {
        $contents = (string) file_get_contents(__DIR__.'/../../config/laravelsabre.php');

        $this->assertStringContainsString("env('LARAVELSABRE_ENABLED', true)", $contents);
    }

    public function test_the_route_name_and_default_path_are_unchanged()
    {
        $this->assertNotNull(Route::getRoutes()->getByName('sabre.dav'));
        $this->assertSame('dav', config('laravelsabre.path'));
        $this->assertSame('http://localhost/dav', route('sabre.dav'));
    }

    public function test_the_middleware_group_name_is_unchanged()
    {
        $this->assertArrayHasKey('laravelsabre', Route::getMiddlewareGroups());
        $this->assertContains('laravelsabre', Route::getRoutes()->getByName('sabre.dav')->middleware());
    }

    public function test_the_public_class_names_are_unchanged()
    {
        $this->assertTrue(class_exists(LaravelSabre::class));
        $this->assertTrue(class_exists(LaravelSabreServiceProvider::class));
        $this->assertTrue(class_exists(Authorize::class));
        $this->assertTrue(class_exists(AuthBackend::class));
    }

    public function test_the_documented_registration_calls_are_unchanged()
    {
        foreach (['nodes', 'plugins', 'plugin', 'auth', 'check', 'clear'] as $method) {
            $this->assertTrue(
                method_exists(LaravelSabre::class, $method),
                'LaravelSabre::'.$method.'() is part of the frozen surface'
            );
        }
    }

    public function test_the_default_principal_format_is_unchanged()
    {
        $capture = new CapturePrincipalPlugin();
        LaravelSabre::nodes([new PrincipalCollection(new PrincipalBackend())]);
        LaravelSabre::plugin(new AuthPlugin(new AuthBackend()));
        LaravelSabre::plugin($capture);

        $user = new Authenticated();
        $user->email = 'john@doe.com';
        $this->be($user);

        $this->call('PROPFIND', '/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']))
            ->assertStatus(207);

        $this->assertSame('principals/john@doe.com', $capture->principal);
    }

    public function test_the_service_provider_is_auto_discovered()
    {
        $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true);

        $this->assertSame(
            [LaravelSabreServiceProvider::class],
            $composer['extra']['laravel']['providers']
        );
    }
}

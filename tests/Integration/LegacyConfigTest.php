<?php

namespace LaravelSabre\Tests\Integration;

use LaravelSabre\Http\Middleware\Authorize;
use LaravelSabre\LaravelSabre;
use LaravelSabre\LaravelSabreServiceProvider;
use LaravelSabre\Tests\Sabre\DAVACL\PrincipalBackend\Mock as PrincipalBackend;
use Sabre\DAVACL\PrincipalCollection;

/**
 * An application whose config file was published under 1.x must keep working untouched, gaining the
 * new settings as defaults (FR-022, FR-030, SC-011).
 */
class LegacyConfigTest extends IntegrationTestCase
{
    public function test_a_config_file_published_under_1x_still_works_and_gains_the_new_defaults()
    {
        // Exactly the four keys a 1.x published file contains, and nothing else.
        $this->publishedAs([
            'domain' => null,
            'path' => 'dav',
            'enabled' => true,
            'middleware' => ['web', Authorize::class],
        ]);

        $this->assertSame('dav', config('laravelsabre.path'));
        $this->assertContains('MKCALENDAR', config('laravelsabre.methods'), 'new keys must merge underneath a published file');
        $this->assertSame('sabre/dav', config('laravelsabre.realm'));
        $this->assertSame('email', config('laravelsabre.principal_attribute'));
        $this->assertNull(config('laravelsabre.guard'));

        LaravelSabre::nodes([new PrincipalCollection(new PrincipalBackend())]);

        $this->call('PROPFIND', '/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']))
            ->assertStatus(207);
    }

    public function test_the_master_switch_still_guards_a_config_file_published_under_1x()
    {
        $this->publishedAs([
            'domain' => null,
            'path' => 'dav',
            'enabled' => false,
            'middleware' => ['web', Authorize::class],
        ]);

        $this->get('/dav')->assertStatus(404);
    }

    /**
     * Put the given array in place as if it were the application's published config file, then let
     * the provider merge the package defaults underneath it.
     *
     * In a real application the config file is loaded before providers register, so the merge fills
     * in keys the file does not mention. Testbench applies environment configuration after providers
     * have registered, so register() is run again here to reproduce the real order.
     *
     * @param  array<string, mixed>  $published
     */
    private function publishedAs(array $published): void
    {
        config(['laravelsabre' => $published]);

        (new LaravelSabreServiceProvider($this->app))->register();
    }
}

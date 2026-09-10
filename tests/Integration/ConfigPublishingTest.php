<?php

namespace LaravelSabre\Tests\Integration;

use Illuminate\Support\ServiceProvider;
use LaravelSabre\Http\Middleware\Authorize;
use LaravelSabre\LaravelSabre;
use LaravelSabre\LaravelSabreServiceProvider;
use LaravelSabre\Tests\Sabre\DAVACL\PrincipalBackend\Mock as PrincipalBackend;
use Sabre\DAVACL\PrincipalCollection;

/**
 * Every setting works without a published config file, a published file takes effect, and a file
 * published under 1.x keeps working because new keys merge underneath it
 * (FR-021, FR-022, FR-030, SC-011).
 */
class ConfigPublishingTest extends IntegrationTestCase
{
    public function test_the_config_file_is_publishable_under_the_frozen_tag()
    {
        $published = ServiceProvider::pathsToPublish(LaravelSabreServiceProvider::class, 'laravelsabre-config');

        $this->assertNotEmpty($published, 'the laravelsabre-config publish tag must exist');
        $this->assertStringEndsWith('laravelsabre.php', (string) array_key_first($published));
        $this->assertStringEndsWith('laravelsabre.php', (string) reset($published));
    }

    public function test_every_setting_has_a_working_default_without_a_published_file()
    {
        $this->assertSame('dav', config('laravelsabre.path'));
        $this->assertNull(config('laravelsabre.domain'));
        $this->assertTrue((bool) config('laravelsabre.enabled'));
        $this->assertContains(Authorize::class, config('laravelsabre.middleware'));
        $this->assertContains('MKCALENDAR', config('laravelsabre.methods'));
        $this->assertNull(config('laravelsabre.guard'));
        $this->assertSame('sabre/dav', config('laravelsabre.realm'));
        $this->assertSame('email', config('laravelsabre.principal_attribute'));
    }

    public function test_an_edited_setting_takes_effect()
    {
        $this->withConfig(['laravelsabre.path' => 'edited-path']);

        LaravelSabre::nodes([new PrincipalCollection(new PrincipalBackend())]);

        $this->call('PROPFIND', '/edited-path/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']))
            ->assertStatus(207);
    }
}

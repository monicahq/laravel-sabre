<?php

namespace LaravelSabre\Tests\Integration;

use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Sabre\DAVACL\PrincipalBackend\Mock as PrincipalBackend;
use Sabre\DAVACL\PrincipalCollection;

/**
 * An empty path mounts the endpoint at the application root. Supported, but cautionary: it captures
 * every path not matched by a route registered earlier (spec Edge Cases).
 */
class RootMountTest extends IntegrationTestCase
{
    public function test_an_empty_path_mounts_at_the_application_root()
    {
        $this->withConfig(['laravelsabre.path' => '']);

        LaravelSabre::nodes([new PrincipalCollection(new PrincipalBackend())]);

        $response = $this->call('PROPFIND', '/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(207);
        $response->assertSee('<d:href>/principals/admin</d:href>', false);
    }

    public function test_the_setting_is_not_silently_rewritten()
    {
        $this->withConfig(['laravelsabre.path' => '']);

        $this->assertSame('', config('laravelsabre.path'));
        $this->assertSame('http://localhost', route('sabre.dav'));
    }
}

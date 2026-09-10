<?php

namespace LaravelSabre\Tests\Integration;

use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Sabre\DAVACL\PrincipalBackend\Mock as PrincipalBackend;
use Sabre\DAVACL\PrincipalCollection;

/**
 * The path and the domain place the endpoint, and DAV href values follow the path
 * (FR-002, FR-011, FR-012).
 */
class EndpointPlacementTest extends IntegrationTestCase
{
    private function servePrincipals(): void
    {
        LaravelSabre::nodes([new PrincipalCollection(new PrincipalBackend())]);
    }

    public function test_the_default_path_answers_without_any_manual_registration()
    {
        $this->servePrincipals();

        $response = $this->call('PROPFIND', '/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(207);
        $response->assertSee('<d:href>/dav/principals/admin</d:href>', false);
        $this->assertSame('http://localhost/dav', route('sabre.dav'));
    }

    public function test_a_changed_path_moves_the_endpoint_and_the_href_base()
    {
        $this->withConfig(['laravelsabre.path' => 'remote.php/dav']);
        $this->servePrincipals();

        $response = $this->call('PROPFIND', '/remote.php/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(207);
        $response->assertSee('<d:href>/remote.php/dav/principals/admin</d:href>', false);
        $response->assertDontSee('<d:href>/dav/principals/admin</d:href>', false);
        $this->assertSame('http://localhost/remote.php/dav', route('sabre.dav'));

        $this->get('/dav/principals/admin')->assertStatus(404);
    }

    public function test_a_configured_domain_restricts_where_the_endpoint_answers()
    {
        $this->withConfig(['laravelsabre.domain' => 'dav.example.com']);
        $this->servePrincipals();

        $onDomain = $this->call('PROPFIND', 'http://dav.example.com/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));
        $onDomain->assertStatus(207);

        $elsewhere = $this->call('PROPFIND', 'http://other.example.com/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));
        $elsewhere->assertStatus(404);
        $elsewhere->assertHeaderMissing('X-Sabre-Version');
    }
}

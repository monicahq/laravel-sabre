<?php

namespace LaravelSabre\Tests\Integration;

use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Sabre\DAVACL\PrincipalBackend\Mock as PrincipalBackend;
use Sabre\CardDAV\Plugin as CardDAVPlugin;
use Sabre\DAVACL\PrincipalCollection;

class ServeDavTest extends IntegrationTestCase
{
    public function test_propfind_on_a_registered_principal_returns_the_engine_multistatus()
    {
        LaravelSabre::nodes([new PrincipalCollection(new PrincipalBackend())]);
        LaravelSabre::plugin(new CardDAVPlugin());

        $response = $this->call('PROPFIND', '/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(207);
        $this->assertCameFromEngine($response);
        $response->assertSee('<d:href>/dav/principals/admin</d:href>', false);
        $response->assertSee('multistatus', false);
    }

    public function test_a_get_with_nothing_registered_returns_the_engine_not_implemented_answer()
    {
        $response = $this->get('/dav');

        $response->assertStatus(501);
        $this->assertCameFromEngine($response);
        $response->assertSee('There was no plugin in the system that was willing to handle this GET method.', false);
    }

    public function test_a_single_node_registration_is_served()
    {
        LaravelSabre::nodes(new PrincipalCollection(new PrincipalBackend()));

        $response = $this->call('PROPFIND', '/dav/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(207);
        $this->assertCameFromEngine($response);
    }

    public function test_a_prebuilt_tree_registration_is_served()
    {
        LaravelSabre::nodes(new \Sabre\DAV\Tree(new PrincipalCollection(new PrincipalBackend())));

        $response = $this->call('PROPFIND', '/dav/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(207);
        $this->assertCameFromEngine($response);
    }

    public function test_an_engine_error_is_returned_as_a_dav_error_document()
    {
        LaravelSabre::nodes([new PrincipalCollection(new PrincipalBackend())]);

        $response = $this->call('PROPFIND', '/dav/does-not-exist', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(404);
        $this->assertCameFromEngine($response);
        $response->assertSee('Sabre\DAV\Exception\NotFound', false);
    }
}

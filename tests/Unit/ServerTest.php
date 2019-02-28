<?php

namespace LaravelSabre\Tests\Unit;

use LaravelSabre\LaravelSabre;
use Sabre\DAVACL\Plugin as AclPlugin;
use Sabre\DAVACL\PrincipalCollection;
use LaravelSabre\Tests\FeatureTestCase;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\CardDAV\Plugin as CardDAVPlugin;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;
use LaravelSabre\Tests\Sabre\DAV\Auth\Backend\Mock as AuthBackend;
use LaravelSabre\Tests\Sabre\DAVACL\PrincipalBackend\Mock as PrincipalBackend;

class ServerTest extends FeatureTestCase
{
    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp() : void
    {
        parent::setUp();

        $this->withoutMiddleware([VerifyCsrfToken::class]);
    }

    public function test_base_server()
    {
        $response = $this->get('/dav');

        $response->assertHeader('X-Sabre-Version');
        $response->assertStatus(501);
        $response->assertSee('There was no plugin in the system that was willing to handle this GET method.');
    }

    public function test_base_server_collection()
    {
        LaravelSabre::nodes([new PrincipalCollection(new PrincipalBackend())]);
        LaravelSabre::plugin(new CardDAVPlugin);

        $response = $this->call('PROPFIND', '/dav/principals/admin');

        $response->assertSee("<?xml version=\"1.0\"?>
<d:multistatus xmlns:d=\"DAV:\" xmlns:s=\"http://sabredav.org/ns\" xmlns:card=\"urn:ietf:params:xml:ns:carddav\">
 <d:response>
  <d:href>/dav/principals/admin</d:href>
  <d:propstat>
   <d:prop>
    <d:resourcetype/>
   </d:prop>
   <d:status>HTTP/1.1 200 OK</d:status>
  </d:propstat>
 </d:response>
</d:multistatus>
");
        $response->assertStatus(207);
    }
}

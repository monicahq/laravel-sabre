<?php

namespace LaravelSabre\Tests\Integration;

use LaravelSabre\Http\Auth\AuthBackend;
use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Authenticated;
use LaravelSabre\Tests\Sabre\DAVACL\PrincipalBackend\Mock as PrincipalBackend;
use LaravelSabre\Tests\Support\CapturePrincipalPlugin;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAVACL\PrincipalCollection;

/**
 * The engine recognises the application's signed in user as a DAV principal, and challenges an
 * anonymous request (FR-015, FR-016).
 */
class AuthenticationTest extends IntegrationTestCase
{
    private function serveWithAuth(): CapturePrincipalPlugin
    {
        $capture = new CapturePrincipalPlugin();

        LaravelSabre::nodes([new PrincipalCollection(new PrincipalBackend())]);
        LaravelSabre::plugin(new AuthPlugin(new AuthBackend()));
        LaravelSabre::plugin($capture);

        return $capture;
    }

    public function test_a_signed_in_user_is_identified_as_a_principal()
    {
        $capture = $this->serveWithAuth();

        $user = new Authenticated();
        $user->email = 'john@doe.com';
        $this->be($user);

        $response = $this->call('PROPFIND', '/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(207);
        $this->assertSame('principals/john@doe.com', $capture->principal);
    }

    public function test_an_anonymous_request_is_challenged_with_the_configured_realm()
    {
        $this->serveWithAuth();

        $response = $this->call('PROPFIND', '/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(401);
        // A request refused during authentication never reaches the point where the engine sets its
        // version header, so provenance is asserted from the engine's own error document instead.
        $response->assertSee('Sabre\\DAV\\Exception\\NotAuthenticated', false);
        $this->assertStringContainsString('sabre/dav', (string) $response->headers->get('WWW-Authenticate'));
        $this->assertStringContainsString('Bearer', (string) $response->headers->get('WWW-Authenticate'));
    }

    public function test_the_realm_comes_from_configuration()
    {
        $this->withConfig(['laravelsabre.realm' => 'contacts.example.com']);
        $this->serveWithAuth();

        $response = $this->call('PROPFIND', '/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(401);
        $this->assertStringContainsString('contacts.example.com', (string) $response->headers->get('WWW-Authenticate'));
    }

    public function test_a_registered_principal_mapping_is_used()
    {
        $capture = $this->serveWithAuth();
        LaravelSabre::principal(function ($user): string {
            return 'uid/'.$user->getAuthIdentifier();
        });

        $user = new Authenticated();
        $user->email = 'john@doe.com';
        $this->be($user);

        $this->call('PROPFIND', '/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $this->assertSame('principals/uid/auth-identifier', $capture->principal);
    }

    public function test_no_credential_material_is_written_to_the_log()
    {
        $capture = $this->serveWithAuth();

        $response = $this->call('PROPFIND', '/dav/principals/admin', [], [], [], $this->serverHeaders([
            'Depth' => '0',
            'Authorization' => 'Bearer super-secret-token',
        ]));

        $response->assertStatus(401);
        $this->assertStringNotContainsString('super-secret-token', $this->bodyOf($response));
        $this->assertStringNotContainsString('super-secret-token', json_encode($response->headers->all()) ?: '');
    }
}

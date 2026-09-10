<?php

namespace LaravelSabre\Tests\Unit;

use LaravelSabre\Exception\PrincipalResolutionException;
use LaravelSabre\Http\Auth\AuthBackend;
use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Authenticated;
use LaravelSabre\Tests\FeatureTestCase;
use Sabre\HTTP\Request;
use Sabre\HTTP\Response;

class AuthBackendTest extends FeatureTestCase
{
    protected function tearDown(): void
    {
        LaravelSabre::clear();

        parent::tearDown();
    }

    public function test_check_reports_an_anonymous_request()
    {
        $backend = new AuthBackend();

        $check = $backend->check(new Request('GET', '/'), new Response());

        $this->assertSame([false, 'User is not authenticated'], $check);
    }

    public function test_check_reports_the_principal_of_the_signed_in_user()
    {
        $this->signIn();
        $backend = new AuthBackend();

        $check = $backend->check(new Request('GET', '/'), new Response());

        $this->assertSame([true, 'principals/john@doe.com'], $check);
    }

    public function test_check_uses_a_registered_principal_mapping()
    {
        LaravelSabre::principal(function ($user): string {
            return 'uid/'.$user->getAuthIdentifier();
        });
        $this->signIn();

        $check = (new AuthBackend())->check(new Request('GET', '/'), new Response());

        $this->assertSame([true, 'principals/uid/auth-identifier'], $check);
    }

    public function test_check_raises_when_the_signed_in_user_has_no_mapped_value()
    {
        $user = new Authenticated();
        $user->email = null;
        $this->signIn($user);

        $this->expectException(PrincipalResolutionException::class);

        (new AuthBackend())->check(new Request('GET', '/'), new Response());
    }

    public function test_the_challenge_names_the_configured_realm()
    {
        $response = new Response();

        (new AuthBackend())->challenge(new Request('GET', '/'), $response);

        $this->assertStringContainsString('sabre/dav', (string) $response->getHeader('WWW-Authenticate'));
        $this->assertStringContainsString('Bearer', (string) $response->getHeader('WWW-Authenticate'));
    }

    public function test_the_realm_can_be_overridden_on_the_instance()
    {
        $response = new Response();

        $backend = new AuthBackend();
        $backend->setRealm('my-realm');
        $backend->challenge(new Request('GET', '/'), $response);

        $this->assertStringContainsString('my-realm', (string) $response->getHeader('WWW-Authenticate'));
    }

    public function test_the_realm_comes_from_configuration()
    {
        $this->withConfig(['laravelsabre.realm' => 'configured-realm']);
        $response = new Response();

        (new AuthBackend())->challenge(new Request('GET', '/'), $response);

        $this->assertStringContainsString('configured-realm', (string) $response->getHeader('WWW-Authenticate'));
    }

    public function test_an_injected_resolver_is_used_as_given()
    {
        $this->signIn();
        $resolver = $this->app->make(\LaravelSabre\Http\Auth\PrincipalResolver::class);

        $check = (new AuthBackend($resolver))->check(new Request('GET', '/'), new Response());

        $this->assertSame([true, 'principals/john@doe.com'], $check);
    }

    public function test_no_credential_material_is_kept_on_the_backend()
    {
        $request = new Request('GET', '/', ['Authorization' => 'Bearer super-secret-token']);
        $backend = new AuthBackend();

        $backend->check($request, new Response());

        $this->assertStringNotContainsString('super-secret-token', print_r($backend, true));
    }
}

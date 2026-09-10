<?php

namespace LaravelSabre\Tests\Integration;

use LaravelSabre\Http\Middleware\Authorize;
use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Authenticated;
use LaravelSabre\Tests\Sabre\DAVACL\PrincipalBackend\Mock as PrincipalBackend;
use RuntimeException;
use Sabre\DAVACL\PrincipalCollection;

/**
 * The access rule decides who may use the endpoint, before any DAV work happens (FR-013, FR-014).
 */
class AccessRuleTest extends IntegrationTestCase
{
    private int $providerCalls = 0;

    private function countingTree(): void
    {
        $this->providerCalls = 0;

        LaravelSabre::nodes(function () {
            $this->providerCalls++;

            return [new PrincipalCollection(new PrincipalBackend())];
        });
    }

    private function signInAs(string $email): void
    {
        $user = new Authenticated();
        $user->email = $email;
        $this->be($user);
    }

    public function test_a_denied_request_gets_403_and_touches_no_provider()
    {
        $this->countingTree();
        LaravelSabre::auth(function ($request): bool {
            return optional($request->user())->email === 'allowed@example.com';
        });

        $this->signInAs('someone-else@example.com');
        $response = $this->call('PROPFIND', '/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(403);
        $response->assertHeaderMissing('X-Sabre-Version');
        $this->assertSame(0, $this->providerCalls, 'no provider may run for a denied request');
    }

    public function test_an_admitted_request_proceeds_to_the_engine()
    {
        $this->countingTree();
        LaravelSabre::auth(function ($request): bool {
            return optional($request->user())->email === 'allowed@example.com';
        });

        $this->signInAs('allowed@example.com');
        $response = $this->call('PROPFIND', '/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(207);
        $this->assertCameFromEngine($response);
        $this->assertSame(1, $this->providerCalls);
    }

    public function test_requests_are_admitted_when_no_rule_is_registered()
    {
        $this->countingTree();

        $response = $this->call('PROPFIND', '/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(207);
        $this->assertCameFromEngine($response);
    }

    public function test_a_rule_that_throws_is_not_treated_as_admitted()
    {
        $this->countingTree();
        LaravelSabre::auth(function (): bool {
            throw new RuntimeException('the rule failed');
        });

        $this->withoutExceptionHandling();

        try {
            $this->call('PROPFIND', '/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));
            $this->fail('the exception from the access rule should have surfaced');
        } catch (RuntimeException $surfaced) {
            $this->assertSame('the rule failed', $surfaced->getMessage());
        }

        $this->assertSame(0, $this->providerCalls, 'no provider may run when the rule fails');
    }

    public function test_the_gate_is_part_of_the_default_middleware_list()
    {
        $defaults = require __DIR__.'/../../config/laravelsabre.php';

        $this->assertContains(Authorize::class, $defaults['middleware']);
        $this->assertContains('web', $defaults['middleware']);
        $this->assertContains(Authorize::class, config('laravelsabre.middleware'));
    }

    public function test_the_gate_still_applies_when_the_middleware_list_is_customised()
    {
        $this->withConfig(['laravelsabre.middleware' => ['web', Authorize::class]]);

        LaravelSabre::auth(function (): bool {
            return false;
        });

        $this->call('PROPFIND', '/dav', [], [], [], $this->serverHeaders(['Depth' => '0']))->assertStatus(403);
    }
}

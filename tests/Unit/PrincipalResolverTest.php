<?php

namespace LaravelSabre\Tests\Unit;

use Illuminate\Support\Facades\Auth;
use LaravelSabre\Exception\PrincipalResolutionException;
use LaravelSabre\Http\Auth\PrincipalResolver;
use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Authenticated;
use LaravelSabre\Tests\FeatureTestCase;

/**
 * Identity resolution: the configured guard, the configured attribute, an application mapping, and a
 * clear failure when the result would be empty (FR-015, FR-032, FR-033).
 */
class PrincipalResolverTest extends FeatureTestCase
{
    protected function tearDown(): void
    {
        LaravelSabre::clear();

        parent::tearDown();
    }

    private function resolver(): PrincipalResolver
    {
        return $this->app->make(PrincipalResolver::class);
    }

    private function user(?string $email = 'john@doe.com'): Authenticated
    {
        $user = new Authenticated();
        $user->email = $email;

        return $user;
    }

    public function test_no_user_resolves_to_null()
    {
        $this->assertNull($this->resolver()->user());
    }

    public function test_the_default_guard_resolves_the_signed_in_user()
    {
        $user = $this->user();
        $this->be($user);

        $this->assertSame($user, $this->resolver()->user());
    }

    public function test_the_principal_defaults_to_the_email_attribute()
    {
        $this->assertSame('principals/john@doe.com', $this->resolver()->principalFor($this->user()));
    }

    public function test_the_principal_attribute_is_configurable()
    {
        $this->withConfig(['laravelsabre.principal_attribute' => 'name']);

        $user = $this->user();
        $user->name = 'john';

        $this->assertSame('principals/john', $this->resolver()->principalFor($user));
    }

    public function test_a_registered_mapping_wins_over_the_configured_attribute()
    {
        LaravelSabre::principal(function ($user): string {
            return 'uid/'.$user->getAuthIdentifier();
        });

        $this->assertSame('principals/uid/auth-identifier', $this->resolver()->principalFor($this->user()));
    }

    public function test_a_missing_attribute_raises_a_clear_error_instead_of_an_empty_principal()
    {
        $user = $this->user(null);

        try {
            $this->resolver()->principalFor($user);
            $this->fail('a user without the mapped attribute must not produce a principal');
        } catch (PrincipalResolutionException $raised) {
            $this->assertStringContainsString('email', $raised->getMessage());
            $this->assertStringContainsString('auth-identifier', $raised->getMessage());
            $this->assertStringNotContainsString('principals/', $raised->getMessage());
        }
    }

    public function test_an_empty_mapping_result_raises_a_clear_error()
    {
        LaravelSabre::principal(function (): string {
            return '   ';
        });

        $this->expectException(PrincipalResolutionException::class);
        $this->expectExceptionMessage('registered principal mapping');

        $this->resolver()->principalFor($this->user());
    }

    public function test_the_guard_is_selectable()
    {
        $this->withConfig([
            'laravelsabre.guard' => 'dav',
            'auth.guards.dav' => ['driver' => 'session', 'provider' => 'users'],
        ]);

        $user = $this->user('guarded@example.com');

        Auth::guard('web')->setUser($this->user('default-guard@example.com'));
        $this->assertNull($this->resolver()->user(), 'a user on another guard must not be seen');

        Auth::guard('dav')->setUser($user);
        $this->assertSame($user, $this->resolver()->user());
        $this->assertSame('principals/guarded@example.com', $this->resolver()->principalFor($user));
    }
}

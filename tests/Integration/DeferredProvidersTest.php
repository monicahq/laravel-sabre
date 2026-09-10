<?php

namespace LaravelSabre\Tests\Integration;

use Illuminate\Support\Facades\Auth;
use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Authenticated;
use LaravelSabre\Tests\Support\CallableFile;
use LaravelSabre\Tests\Support\CaptureRequestPlugin;

/**
 * Providers must run once per request, after middleware, so they can depend on the signed in user
 * (FR-006).
 */
class DeferredProvidersTest extends IntegrationTestCase
{
    public function test_a_node_provider_runs_once_per_request()
    {
        $calls = 0;

        LaravelSabre::nodes(function () use (&$calls) {
            $calls++;

            return [new CallableFile('provided.txt', fn () => 'provided')];
        });

        $this->assertSame(0, $calls, 'a provider must not run at registration time');

        $first = $this->get('/dav/provided.txt');
        $first->assertStatus(200);
        $this->assertSame(1, $calls);

        $second = $this->get('/dav/provided.txt');
        $second->assertStatus(200);
        $this->assertSame(2, $calls, 'a provider runs again for the next request');
    }

    public function test_a_plugin_provider_runs_once_per_request()
    {
        $calls = 0;
        $capture = new CaptureRequestPlugin();

        LaravelSabre::nodes([new CallableFile('provided.txt', fn () => 'provided')]);
        LaravelSabre::plugins(function () use (&$calls, $capture) {
            $calls++;

            return [$capture];
        });

        $this->assertSame(0, $calls);

        $this->get('/dav/provided.txt')->assertStatus(200);

        $this->assertSame(1, $calls);
        $this->assertNotNull($capture->seen);
    }

    public function test_a_provider_can_depend_on_the_signed_in_user()
    {
        $user = new Authenticated();
        $user->email = 'provider@example.com';
        $this->be($user);

        LaravelSabre::nodes(function () {
            $email = Auth::user()?->email ?? 'anonymous';

            return [new CallableFile('user.txt', fn () => $email)];
        });

        $response = $this->get('/dav/user.txt');

        $response->assertStatus(200);
        $this->assertSame('provider@example.com', $this->bodyOf($response));
    }
}

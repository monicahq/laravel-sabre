<?php

namespace LaravelSabre\Tests\Integration;

use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Authenticated;
use LaravelSabre\Tests\Support\IdentityFile;

/**
 * No request may observe headers, body, identity or configuration from another request
 * (FR-024, FR-034, SC-005).
 */
class IsolationTest extends IntegrationTestCase
{
    private function signInAs(string $email): void
    {
        $user = new Authenticated();
        $user->email = $email;
        $this->be($user);
    }

    public function test_two_consecutive_requests_for_different_users_do_not_share_identity()
    {
        LaravelSabre::nodes([new IdentityFile()]);

        $this->signInAs('first@example.com');
        $first = $this->get('/dav/whoami.txt');
        $first->assertStatus(200);
        $this->assertSame('first@example.com', $this->bodyOf($first));

        $this->signInAs('second@example.com');
        $second = $this->get('/dav/whoami.txt');
        $second->assertStatus(200);
        $this->assertSame('second@example.com', $this->bodyOf($second));
    }

    public function test_a_thousand_alternating_requests_each_see_only_their_own_identity()
    {
        LaravelSabre::nodes([new IdentityFile()]);

        for ($i = 0; $i < 1000; $i++) {
            $email = $i % 2 === 0 ? 'even@example.com' : 'odd@example.com';
            $this->signInAs($email);

            $response = $this->get('/dav/whoami.txt');

            $this->assertSame(200, $response->getStatusCode(), 'request '.$i.' failed');
            $this->assertSame($email, $this->bodyOf($response), 'request '.$i.' saw another identity');
        }
    }

    public function test_response_headers_do_not_carry_over_between_requests()
    {
        LaravelSabre::nodes([new IdentityFile()]);

        $first = $this->call('PROPFIND', '/dav', [], [], [], $this->serverHeaders(['Depth' => '1']));
        $first->assertStatus(207);

        $second = $this->get('/dav/whoami.txt');
        $second->assertStatus(200);
        $this->assertSame('text/plain', explode(';', (string) $second->headers->get('Content-Type'))[0]);
    }
}

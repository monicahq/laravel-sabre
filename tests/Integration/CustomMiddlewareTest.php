<?php

namespace LaravelSabre\Tests\Integration;

use LaravelSabre\Http\Middleware\Authorize;
use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Support\MarksResponse;

/**
 * The middleware applied to the endpoint is configurable, and the access gate keeps working when the
 * list is customised (FR-014, FR-020).
 */
class CustomMiddlewareTest extends IntegrationTestCase
{
    public function test_a_custom_middleware_runs_for_dav_requests()
    {
        $this->withConfig(['laravelsabre.middleware' => ['web', Authorize::class, MarksResponse::class]]);

        $response = $this->get('/dav');

        $response->assertStatus(501);
        $this->assertSame('ran', $response->headers->get('X-Custom-Middleware'));
    }

    public function test_the_access_gate_still_denies_with_a_custom_list()
    {
        $this->withConfig(['laravelsabre.middleware' => ['web', Authorize::class, MarksResponse::class]]);

        LaravelSabre::auth(function (): bool {
            return false;
        });

        $this->get('/dav')->assertStatus(403);
    }

    public function test_a_list_without_the_gate_admits_everything()
    {
        $this->withConfig(['laravelsabre.middleware' => ['web']]);

        LaravelSabre::auth(function (): bool {
            return false;
        });

        // Removing the gate is the application's choice; the endpoint then serves every request.
        $this->get('/dav')->assertStatus(501);
    }
}

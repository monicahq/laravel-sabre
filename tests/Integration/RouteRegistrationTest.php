<?php

namespace LaravelSabre\Tests\Integration;

use Illuminate\Support\Facades\Route;
use LaravelSabre\Http\Controllers\DAVController;

class RouteRegistrationTest extends IntegrationTestCase
{
    public function test_the_endpoint_is_registered_under_the_configured_path()
    {
        $route = Route::getRoutes()->getByName('sabre.dav');

        $this->assertNotNull($route, 'the frozen route name sabre.dav must exist');
        $this->assertSame('dav/{path?}', $route->uri());
        $this->assertSame([DAVController::class, 'init'], [$route->getController()::class, $route->getActionMethod()]);
    }

    public function test_the_route_answers_every_configured_method()
    {
        $route = Route::getRoutes()->getByName('sabre.dav');
        $methods = $route->methods();

        foreach (config('laravelsabre.methods') as $method) {
            $this->assertContains($method, $methods, $method.' must be routed to the endpoint');
        }

        $this->assertContains('MKCALENDAR', $methods);
        $this->assertContains('ACL', $methods);
    }

    public function test_the_endpoint_url_is_generated_from_the_route_name()
    {
        $this->assertSame('http://localhost/dav', route('sabre.dav'));
        $this->assertSame('http://localhost/dav/principals/admin', route('sabre.dav', ['path' => 'principals/admin']));
    }

    public function test_the_middleware_group_is_declared_and_applied()
    {
        $route = Route::getRoutes()->getByName('sabre.dav');

        $this->assertContains('laravelsabre', $route->middleware());
        $this->assertContains('web', Route::getMiddlewareGroups()['laravelsabre']);
        $this->assertContains(\LaravelSabre\Http\Middleware\Authorize::class, Route::getMiddlewareGroups()['laravelsabre']);
    }

    public function test_the_endpoint_reaches_the_engine_with_nothing_registered()
    {
        $response = $this->get('/dav');

        $response->assertStatus(501);
        $response->assertHeader('X-Sabre-Version');
    }
}

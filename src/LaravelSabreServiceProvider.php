<?php

namespace LaravelSabre;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LaravelSabre\Http\Auth\PrincipalResolver;
use LaravelSabre\Http\Middleware\EnsureEnabled;

/**
 * Registered automatically through the package's Composer extra, so an application never lists it.
 *
 * @api
 */
class LaravelSabreServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     *
     * @return void
     */
    #[\Override]
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravelsabre.php', 'laravelsabre'
        );

        $this->app->singleton(Registry::class);

        $this->app->bind(PrincipalResolver::class, function (Application $app): PrincipalResolver {
            $config = $app->make('config');

            return new PrincipalResolver(
                $app->make(Registry::class),
                $app->make(AuthFactory::class),
                $config->get('laravelsabre.guard'),
                (string) $config->get('laravelsabre.principal_attribute', 'email')
            );
        });
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        Route::middlewareGroup('laravelsabre', config('laravelsabre.middleware', []));

        $this->registerRoutes();
        $this->registerPublishing();
    }

    /**
     * Register the package routes.
     *
     * @return void
     */
    private function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/routes.php');
        });
    }

    /**
     * Get the route group configuration array.
     *
     * @return array<string, mixed>
     */
    private function routeConfiguration()
    {
        return [
            // The master switch is attached here, ahead of the configurable group, so that a config
            // file published under 1.x is still guarded by it.
            'middleware' => [EnsureEnabled::class, 'laravelsabre'],
            'domain' => config('laravelsabre.domain', null),
            'prefix' => config('laravelsabre.path'),
        ];
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    private function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravelsabre.php' => config_path('laravelsabre.php'),
            ], 'laravelsabre-config');
        }
    }
}

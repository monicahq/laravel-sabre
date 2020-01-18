<?php

namespace LaravelSabre;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LaravelSabreServiceProvider extends ServiceProvider
{
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
     * @psalm-suppress InvalidArgument
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
     * @return array
     */
    private function routeConfiguration()
    {
        return [
            'middleware' => 'laravelsabre',
            'domain' => config('laravelsabre.domain', null),
            'namespace' => 'LaravelSabre\Http\Controllers',
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

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravelsabre.php', 'laravelsabre'
        );

        $this->commands([
            Console\PublishCommand::class,
        ]);
    }
}

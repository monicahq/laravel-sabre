<?php

namespace LaravelSabre\Tests;

use Orchestra\Testbench\TestCase;
use LaravelSabre\LaravelSabreServiceProvider;

class FeatureTestCase extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            LaravelSabreServiceProvider::class,
        ];
    }

    protected function resolveApplicationCore($app)
    {
        parent::resolveApplicationCore($app);

        $app->detectEnvironment(function () {
            return 'testing';
        });
    }
}

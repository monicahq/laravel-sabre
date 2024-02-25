<?php

namespace LaravelSabre\Tests;

use LaravelSabre\LaravelSabreServiceProvider;
use Orchestra\Testbench\TestCase;

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

    /**
     * Create a user and sign in as that user. If a user
     * object is passed, then sign in as that user.
     *
     * @param  null  $user
     * @return mixed
     */
    public function signIn($user = null)
    {
        if (is_null($user)) {
            $user = new Authenticated();
            $user->email = 'john@doe.com';
        }

        $this->be($user);

        return $user;
    }

    public static function setUpBeforeClass(): void
    {
        if (! class_exists('\Illuminate\Testing\TestResponse') && class_exists('\Illuminate\Foundation\Testing\TestResponse')) {
            class_alias('\Illuminate\Foundation\Testing\TestResponse', '\Illuminate\Testing\TestResponse');
        }
    }
}

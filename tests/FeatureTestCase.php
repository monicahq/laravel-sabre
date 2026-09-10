<?php

namespace LaravelSabre\Tests;

use LaravelSabre\LaravelSabreServiceProvider;
use Orchestra\Testbench\TestCase;

class FeatureTestCase extends TestCase
{
    /**
     * The environment the application under test runs in.
     *
     * The 1.x suite pinned this to testing, which was also the only environment in which the
     * adapter delivered the framework request to the DAV engine. The rebuild has one code path for
     * every environment, so the suite must be able to assert that (FR-007, SC-004).
     */
    protected string $environment = 'testing';

    /**
     * Configuration applied before the package provider registers, so package defaults merge under
     * it rather than over it.
     *
     * @var array<string, mixed>
     */
    protected array $configOverrides = [];

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
            return $this->environment;
        });
    }

    protected function defineEnvironment($app)
    {
        foreach ($this->configOverrides as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    /**
     * Rebuild the application under test in the given environment.
     */
    protected function runningInEnvironment(string $environment): void
    {
        $this->environment = $environment;

        $this->refreshApplication();
        $this->afterRefresh();
    }

    /**
     * Rebuild the application under test with the given configuration applied.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function withConfig(array $overrides): void
    {
        $this->configOverrides = array_replace($this->configOverrides, $overrides);

        $this->refreshApplication();
        $this->afterRefresh();
    }

    /**
     * Hook for subclasses that must re-apply per-test setup after the application is rebuilt.
     */
    protected function afterRefresh(): void
    {
        // Nothing by default.
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
}

<?php

namespace LaravelSabre\Tests\Integration;

use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Compatibility\Support\Fixtures;
use LaravelSabre\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;

/**
 * Shared setup for tests that drive the real HTTP route.
 */
abstract class IntegrationTestCase extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutCsrfProtection();
    }

    /**
     * Disable request-forgery protection for DAV methods, which are not read methods and would
     * otherwise be rejected with 419.
     *
     * The class doing this differs across the supported framework versions, and the substitution
     * Testbench applies to its own class is not reapplied when the application is rebuilt, so every
     * name is disabled explicitly.
     */
    protected function withoutCsrfProtection(): void
    {
        $this->withoutMiddleware([
            VerifyCsrfToken::class,
            'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
            'Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken',
        ]);
    }

    protected function afterRefresh(): void
    {
        $this->withoutCsrfProtection();
    }

    protected function tearDown(): void
    {
        LaravelSabre::clear();
        Fixtures::cleanUp();

        parent::tearDown();
    }

    /**
     * Turn a header map into the server array the test client expects.
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    protected function serverHeaders(array $headers): array
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $server;
    }

    /**
     * Read a response body whether it is buffered or streamed.
     */
    protected function bodyOf($response): string
    {
        if ($response->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return (string) $response->streamedContent();
        }

        return (string) $response->getContent();
    }

    /**
     * Assert the response came from the DAV engine rather than from the framework.
     *
     * The engine sets its version header after the beforeMethod hook, so a request refused during
     * authentication does not carry it; assert on the engine's error document in that case.
     */
    protected function assertCameFromEngine($response): void
    {
        $response->assertHeader('X-Sabre-Version');
    }
}

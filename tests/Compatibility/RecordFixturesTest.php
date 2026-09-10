<?php

namespace LaravelSabre\Tests\Compatibility;

use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Compatibility\Support\Fixtures;
use LaravelSabre\Tests\Compatibility\Support\Recorder;
use LaravelSabre\Tests\Compatibility\Support\Scenarios;
use LaravelSabre\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Captures the recordings. Runs only when LARAVELSABRE_RECORD=1, because it writes fixtures rather
 * than asserting behaviour, and because it must be run against the source it is recording.
 */
class RecordFixturesTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('LARAVELSABRE_RECORD') !== '1') {
            $this->markTestSkipped('Set LARAVELSABRE_RECORD=1 to re-record the compatibility fixtures.');
        }

        $this->withoutMiddleware([VerifyCsrfToken::class]);
    }

    protected function tearDown(): void
    {
        LaravelSabre::clear();
        Fixtures::cleanUp();

        parent::tearDown();
    }

    public static function scenarioProvider(): array
    {
        $cases = [];
        foreach (Scenarios::all() as $name => $scenario) {
            $cases[$name] = [$name, $scenario];
        }

        return $cases;
    }

    #[DataProvider('scenarioProvider')]
    public function test_record_scenario(string $name, array $scenario)
    {
        Fixtures::apply($scenario['fixture']);

        $response = $this->call(
            $scenario['method'],
            $scenario['uri'],
            [],
            [],
            [],
            $this->serverHeaders($scenario['headers']),
            $scenario['body']
        );

        Recorder::write(
            $name,
            $scenario,
            $response->getStatusCode(),
            $response->headers->all(),
            $this->responseBody($response)
        );

        $this->assertFileExists(Recorder::directory().'/'.$name.'.json');
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function serverHeaders(array $headers): array
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $server;
    }

    private function responseBody($response): string
    {
        if (method_exists($response, 'streamedContent') && $response->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return (string) $response->streamedContent();
        }

        return (string) $response->getContent();
    }
}

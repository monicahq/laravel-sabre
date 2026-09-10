<?php

namespace LaravelSabre\Tests\Compatibility;

use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Compatibility\Support\Fixtures;
use LaravelSabre\Tests\Compatibility\Support\Recorder;
use LaravelSabre\Tests\FeatureTestCase;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Replays the recordings captured from 1.x.
 *
 * A recording without a deviation must reproduce exactly: same status, same comparable headers, same
 * body. A recording with a deviation must not reproduce, because the spec authorises that change,
 * and the deviation text says what changed (SC-003).
 */
class ReplayTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            VerifyCsrfToken::class,
            'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
            'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
        ]);
    }

    protected function tearDown(): void
    {
        LaravelSabre::clear();
        Fixtures::cleanUp();

        parent::tearDown();
    }

    public static function recordingProvider(): array
    {
        $cases = [];
        foreach (Recorder::all() as $recording) {
            $cases[$recording['name']] = [$recording];
        }

        return $cases;
    }

    public function test_the_recordings_exist()
    {
        $recordings = Recorder::all();

        $this->assertNotEmpty($recordings, 'the 1.x recordings are missing; re-record with LARAVELSABRE_RECORD=1');
        $this->assertGreaterThanOrEqual(20, count($recordings));
    }

    #[DataProvider('recordingProvider')]
    public function test_the_recording_replays(array $recording)
    {
        $scenario = $recording['scenario'];
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

        $status = $response->getStatusCode();
        $headers = Recorder::normalizeHeaders($response->headers->all());
        $body = Recorder::normalizeBody($this->bodyOf($response));

        $expected = $recording['expected'];
        $deviation = $recording['deviation'] ?? null;

        if (! is_null($deviation)) {
            $this->assertNotSame(
                $expected['status'],
                $status,
                'the authorised deviation did not happen: '.$deviation
            );

            return;
        }

        $this->assertSame($expected['status'], $status, 'status changed for '.$recording['name']);

        // A framework error page is compared by status only: its markup belongs to the framework and
        // changes between framework versions, which is not a change in this package.
        if (! str_starts_with(trim($expected['body']), '<!DOCTYPE html')) {
            $this->assertSame($expected['body'], $body, 'body changed for '.$recording['name']);
        }

        $this->assertSame($expected['headers'], $headers, 'headers changed for '.$recording['name']);
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

    private function bodyOf($response): string
    {
        if ($response->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return (string) $response->streamedContent();
        }

        return (string) $response->getContent();
    }
}

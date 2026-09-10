<?php

namespace LaravelSabre\Tests\Unit\Sabre;

use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use LaravelSabre\Sabre\ResponseFactory;
use LaravelSabre\Tests\FeatureTestCase;
use Sabre\HTTP\Response as SabreResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Engine responses are translated into framework responses, streaming without buffering and
 * honouring a valid declared length (FR-008, FR-009, SC-006).
 */
class ResponseFactoryTest extends FeatureTestCase
{
    private function stream(string $contents)
    {
        $handle = fopen('php://temp', 'r+b');
        fwrite($handle, $contents);
        rewind($handle);

        return $handle;
    }

    public function test_a_string_body_becomes_an_ordinary_response()
    {
        $engine = new SabreResponse(200, ['Content-Type' => 'text/plain'], 'Alright');

        $response = ResponseFactory::toLaravel($engine);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('Alright', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/plain', $response->headers->get('Content-Type'));
    }

    public function test_a_stream_body_becomes_a_streamed_response()
    {
        $engine = new SabreResponse(200, [], $this->stream("It's magical\n"));

        $response = ResponseFactory::toLaravel($engine);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame("It's magical\n", (new TestResponse($response))->streamedContent());
    }

    public function test_a_declared_length_caps_the_stream_exactly()
    {
        $engine = new SabreResponse(200, ['Content-Length' => '4'], $this->stream("It's magical\n"));

        $response = ResponseFactory::toLaravel($engine);

        $this->assertSame("It's", (new TestResponse($response))->streamedContent());
    }

    public function test_an_invalid_declared_length_does_not_truncate_the_body()
    {
        foreach (['not-a-number', '', '-5'] as $value) {
            $engine = new SabreResponse(200, ['Content-Length' => $value], $this->stream('complete body'));

            $response = ResponseFactory::toLaravel($engine);

            $this->assertSame('complete body', (new TestResponse($response))->streamedContent(), 'length '.$value);
        }
    }

    public function test_a_callable_body_becomes_a_streamed_response()
    {
        $engine = new SabreResponse(200, [], function (): void {
            echo 'generated';
        });

        $response = ResponseFactory::toLaravel($engine);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('generated', (new TestResponse($response))->streamedContent());
    }

    public function test_an_absent_body_keeps_the_status_and_headers()
    {
        $engine = new SabreResponse(201, ['Location' => '/dav/created.txt'], null);

        $response = ResponseFactory::toLaravel($engine);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('/dav/created.txt', $response->headers->get('Location'));
        $this->assertSame('', $response->getContent());
    }

    public function test_repeated_headers_all_reach_the_client()
    {
        $engine = new SabreResponse(401, [], '');
        $engine->addHeader('WWW-Authenticate', 'Basic realm="one"');
        $engine->addHeader('WWW-Authenticate', 'Bearer realm="two"');

        $response = ResponseFactory::toLaravel($engine);

        $this->assertSame(
            ['Basic realm="one"', 'Bearer realm="two"'],
            $response->headers->all('WWW-Authenticate')
        );
    }

    public function test_body_less_statuses_are_preserved()
    {
        foreach ([204, 304] as $status) {
            $engine = new SabreResponse($status, ['X-Marker' => 'kept'], '');

            $response = ResponseFactory::toLaravel($engine);

            $this->assertSame($status, $response->getStatusCode());
            $this->assertSame('kept', $response->headers->get('X-Marker'));
        }
    }

    public function test_a_hundred_megabyte_body_is_not_buffered()
    {
        $path = tempnam(sys_get_temp_dir(), 'laravel-sabre-stream-');
        $handle = fopen($path, 'w+b');
        $chunk = str_repeat('x', 1024 * 1024);
        for ($i = 0; $i < 100; $i++) {
            fwrite($handle, $chunk);
        }
        unset($chunk);
        rewind($handle);

        $engine = new SabreResponse(200, ['Content-Length' => (string) filesize($path)], $handle);
        $response = ResponseFactory::toLaravel($engine);

        gc_collect_cycles();
        $before = memory_get_peak_usage(true);

        ob_start(function (): string {
            return '';
        }, 8192);
        $response->sendContent();
        ob_end_clean();

        $after = memory_get_peak_usage(true);

        @unlink($path);

        $increase = ($after - $before) / 1048576;
        $this->assertLessThan(20, $increase, 'streaming 100 MB raised peak memory by '.$increase.' MB');
    }
}

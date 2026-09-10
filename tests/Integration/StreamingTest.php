<?php

namespace LaravelSabre\Tests\Integration;

use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Support\CallableFile;

/**
 * Streamed and generated bodies must be delivered complete, without buffering the whole body, and a
 * valid declared content length must be honoured exactly (FR-009, SC-006).
 */
class StreamingTest extends IntegrationTestCase
{
    private function stream(string $contents)
    {
        $handle = fopen('php://temp', 'r+b');
        fwrite($handle, $contents);
        rewind($handle);

        return $handle;
    }

    public function test_a_streamed_body_is_delivered_complete()
    {
        LaravelSabre::nodes([new CallableFile('stream.txt', fn () => $this->stream('streamed contents'))]);

        $response = $this->get('/dav/stream.txt');

        $response->assertStatus(200);
        $this->assertSame('streamed contents', $this->bodyOf($response));
    }

    public function test_a_declared_content_length_caps_the_transfer_exactly()
    {
        LaravelSabre::nodes([new CallableFile('stream.txt', fn () => $this->stream('streamed contents'), 8)]);

        $response = $this->get('/dav/stream.txt');

        $response->assertStatus(200);
        $this->assertSame('8', $response->headers->get('Content-Length'));
        $this->assertSame('streamed', $this->bodyOf($response));
    }

    public function test_an_absent_content_length_still_delivers_the_whole_body()
    {
        LaravelSabre::nodes([new CallableFile('stream.txt', fn () => $this->stream('no declared length at all'))]);

        $response = $this->get('/dav/stream.txt');

        $response->assertStatus(200);
        $this->assertNull($response->headers->get('Content-Length'));
        $this->assertSame('no declared length at all', $this->bodyOf($response));
    }

    public function test_a_generated_body_is_delivered()
    {
        LaravelSabre::nodes([new CallableFile('generated.txt', fn () => 'generated on the fly')]);

        $response = $this->get('/dav/generated.txt');

        $response->assertStatus(200);
        $this->assertSame('generated on the fly', $this->bodyOf($response));
    }

    public function test_a_head_request_keeps_the_status_and_headers_without_a_body()
    {
        LaravelSabre::nodes([new CallableFile('stream.txt', fn () => $this->stream('streamed contents'), 17)]);

        $response = $this->call('HEAD', '/dav/stream.txt');

        $response->assertStatus(200);
        $this->assertSame('17', $response->headers->get('Content-Length'));
    }
}

<?php

namespace LaravelSabre\Tests\Unit;

use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use LaravelSabre\Sabre\Server;
use LaravelSabre\Tests\FeatureTestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServerTest extends FeatureTestCase
{
    public function test_response_get_resource()
    {
        $server = new Server(null);

        $file = fopen(__DIR__.'/../stubs/file.txt', 'r');
        $server->httpResponse->setBody($file);
        $server->httpResponse->setStatus(200);

        $response = $server->getResponse();
        $this->assertInstanceOf(StreamedResponse::class, $response);

        $response = new TestResponse($response);

        $response->assertOk();
        $this->assertEquals("It's magical\n", $response->streamedContent());
    }

    public function test_response_get_resource_contentlength()
    {
        $server = new Server(null);

        $file = fopen(__DIR__.'/../stubs/file.txt', 'r');
        $server->httpResponse->setBody($file);
        $server->httpResponse->setStatus(200);
        $server->httpResponse->setHeader('Content-Length', 4);

        $response = $server->getResponse();
        $this->assertInstanceOf(StreamedResponse::class, $response);

        $response = new TestResponse($response);

        $response->assertOk();
        $this->assertEquals("It's", $response->streamedContent());
    }

    public function test_response_get_string()
    {
        $server = new Server(null);

        $server->httpResponse->setBody('Alright');
        $server->httpResponse->setStatus(200);

        $response = $server->getResponse();
        $this->assertInstanceOf(Response::class, $response);

        $response = new TestResponse($response);

        $response->assertOk();
        $this->assertEquals('Alright', $response->getContent());
    }
}

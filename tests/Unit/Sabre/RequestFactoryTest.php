<?php

namespace LaravelSabre\Tests\Unit\Sabre;

use Illuminate\Http\Request;
use LaravelSabre\Sabre\RequestFactory;
use LaravelSabre\Tests\FeatureTestCase;

/**
 * The engine request is built from the framework request, with no superglobal involved (FR-007).
 */
class RequestFactoryTest extends FeatureTestCase
{
    public function test_it_carries_the_method_url_and_base()
    {
        $request = Request::create('/dav/principals/admin', 'PROPFIND');

        $sabre = RequestFactory::fromLaravel($request, '/dav/');

        $this->assertSame('PROPFIND', $sabre->getMethod());
        $this->assertSame('/dav/principals/admin', $sabre->getUrl());
        $this->assertSame('/dav/', $sabre->getBaseUrl());
        $this->assertSame('principals/admin', $sabre->getPath());
    }

    public function test_it_keeps_the_query_string_and_exposes_the_parameters()
    {
        $request = Request::create('/dav/files?a=1&b=2', 'PROPFIND');

        $sabre = RequestFactory::fromLaravel($request, '/dav/');

        $this->assertSame('/dav/files?a=1&b=2', $sabre->getUrl());
        $this->assertSame('files', $sabre->getPath());
        $this->assertSame(['a' => '1', 'b' => '2'], $sabre->getQueryParameters());
    }

    public function test_it_carries_every_header_including_repeated_ones()
    {
        $request = Request::create('/dav', 'PROPFIND');
        $request->headers->set('Depth', '1');
        $request->headers->set('X-Multi', ['first', 'second']);

        $sabre = RequestFactory::fromLaravel($request, '/dav/');

        $this->assertSame('1', $sabre->getHeader('Depth'));
        $this->assertSame(['first', 'second'], $sabre->getHeaderAsArray('X-Multi'));
    }

    public function test_it_carries_the_body_as_a_stream()
    {
        $request = Request::create('/dav/file.txt', 'PUT', [], [], [], [], 'the body');

        $sabre = RequestFactory::fromLaravel($request, '/dav/');

        $body = $sabre->getBody();
        $this->assertTrue(is_resource($body), 'the body must be handed over as a stream, not a string');
        $this->assertSame('the body', stream_get_contents($body));
    }

    public function test_it_reads_no_superglobal()
    {
        $server = $_SERVER;
        unset($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
        $_SERVER['HTTP_X_PROCESS_LEVEL'] = 'should-not-appear';

        try {
            $request = Request::create('/dav/principals/admin', 'REPORT');

            $sabre = RequestFactory::fromLaravel($request, '/dav/');

            $this->assertSame('REPORT', $sabre->getMethod());
            $this->assertSame('/dav/principals/admin', $sabre->getUrl());
            $this->assertNull($sabre->getHeader('X-Process-Level'));
        } finally {
            $_SERVER = $server;
        }
    }

    public function test_it_falls_back_to_the_buffered_body_when_the_stream_was_already_taken()
    {
        $request = new class('/dav/file.txt', 'PUT') extends Request
        {
            public function __construct(string $uri, string $method)
            {
                parent::__construct();
                $this->initialize([], [], [], [], [], ['REQUEST_URI' => $uri, 'REQUEST_METHOD' => $method]);
            }

            public function getContent($asResource = false)
            {
                if ($asResource === true) {
                    throw new \LogicException('the body stream was already taken');
                }

                return 'buffered body';
            }
        };

        $sabre = RequestFactory::fromLaravel($request, '/dav/');

        $this->assertSame('buffered body', $sabre->getBodyAsString());
    }

    public function test_the_endpoint_root_resolves_to_an_empty_path()
    {
        $request = Request::create('/dav', 'PROPFIND');

        $sabre = RequestFactory::fromLaravel($request, '/dav/');

        $this->assertSame('', $sabre->getPath());
    }
}

<?php

namespace LaravelSabre\Tests\Integration;

use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Compatibility\Support\Fixtures;
use LaravelSabre\Tests\Support\CaptureRequestPlugin;
use Sabre\DAV\FS\Directory;
use Sabre\DAV\Tree;

/**
 * Encoded characters, nested segments, trailing slashes and query strings must reach the engine as a
 * URL relative to the DAV base, and href values must stay consistent with what the client asked for.
 */
class UrlShapesTest extends IntegrationTestCase
{
    private function serveFiles(): CaptureRequestPlugin
    {
        $capture = new CaptureRequestPlugin();
        LaravelSabre::nodes(new Tree(new Directory(Fixtures::prepareFiles())));
        LaravelSabre::plugin($capture);

        return $capture;
    }

    public function test_an_encoded_path_reaches_the_engine_decoded()
    {
        $capture = $this->serveFiles();

        $response = $this->call('PROPFIND', '/dav/hello%20world.txt', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(207);
        $this->assertSame('hello world.txt', $capture->seen['path']);
        $response->assertSee('/dav/hello%20world.txt', false);
    }

    public function test_a_nested_path_reaches_the_engine()
    {
        $capture = $this->serveFiles();

        $response = $this->call('PROPFIND', '/dav/sub/nested.txt', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(207);
        $this->assertSame('sub/nested.txt', $capture->seen['path']);
    }

    public function test_a_trailing_slash_resolves_to_the_same_node()
    {
        $capture = $this->serveFiles();

        $response = $this->call('PROPFIND', '/dav/sub/', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(207);
        $this->assertSame('sub', $capture->seen['path']);
    }

    public function test_the_endpoint_root_resolves_to_the_tree_root()
    {
        $capture = $this->serveFiles();

        $response = $this->call('PROPFIND', '/dav', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(207);
        $this->assertSame('', $capture->seen['path']);
    }

    public function test_a_query_string_reaches_the_engine_without_changing_the_path()
    {
        $capture = $this->serveFiles();

        $response = $this->call('PROPFIND', '/dav/hello.txt?a=1&b=2', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(207);
        $this->assertSame('hello.txt', $capture->seen['path']);
        $this->assertSame(['a' => '1', 'b' => '2'], $capture->seen['query']);
    }
}

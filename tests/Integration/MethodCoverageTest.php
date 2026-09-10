<?php

namespace LaravelSabre\Tests\Integration;

use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Compatibility\Support\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use Sabre\DAV\Tree;

/**
 * Every method in the configured list must reach the DAV engine. A response without the engine
 * version header means the framework answered instead, which is the 1.x defect for MKCALENDAR and
 * ACL (FR-003, SC-002).
 */
class MethodCoverageTest extends IntegrationTestCase
{
    public static function methodProvider(): array
    {
        $methods = require __DIR__.'/../../config/laravelsabre.php';

        $cases = [];
        foreach ($methods['methods'] as $method) {
            $cases[$method] = [$method];
        }

        return $cases;
    }

    #[DataProvider('methodProvider')]
    public function test_the_method_reaches_the_engine(string $method)
    {
        LaravelSabre::nodes(new Tree(new \Sabre\DAV\FS\Directory(Fixtures::prepareFiles())));

        $response = $this->call($method, '/dav/hello.txt', [], [], [], $this->serverHeaders([
            'Destination' => '/dav/destination.txt',
            'Depth' => '0',
        ]));

        // The engine's own answer may legitimately be an error, for example 405 for MKCOL on a
        // resource that already exists. What matters is that the engine answered at all: the version
        // header is only present when the request reached it, so it proves provenance.
        $this->assertNotSame(419, $response->getStatusCode(), $method.' was rejected by the framework');
        $this->assertNotNull(
            $response->headers->get('X-Sabre-Version'),
            $method.' never reached the DAV engine (status '.$response->getStatusCode().')'
        );
    }

    public function test_the_configured_method_list_is_what_gets_routed()
    {
        $response = $this->call('SEARCH', '/dav/hello.txt');

        $this->assertSame(405, $response->getStatusCode(), 'a method outside the configured list is not routed');
    }

    public function test_a_method_added_to_the_configuration_is_routed()
    {
        $defaults = require __DIR__.'/../../config/laravelsabre.php';
        $this->withConfig(['laravelsabre.methods' => array_merge($defaults['methods'], ['SEARCH'])]);

        LaravelSabre::nodes(new Tree(new \Sabre\DAV\FS\Directory(Fixtures::prepareFiles())));

        $response = $this->call('SEARCH', '/dav/hello.txt');

        $this->assertNotSame(405, $response->getStatusCode(), 'SEARCH must be routed once configured');
        $response->assertHeader('X-Sabre-Version');
    }
}

<?php

namespace LaravelSabre\Tests\Unit\Sabre;

use LaravelSabre\Sabre\Sapi;
use LaravelSabre\Sabre\Server;
use LaravelSabre\Tests\FeatureTestCase;

class ServerTest extends FeatureTestCase
{
    public function test_the_base_uri_follows_the_configured_path()
    {
        $this->assertSame('/dav/', (new Server())->getBaseUri());

        $this->withConfig(['laravelsabre.path' => 'remote.php/dav']);
        $this->assertSame('/remote.php/dav/', (new Server())->getBaseUri());

        $this->withConfig(['laravelsabre.path' => '/leading-and-trailing/']);
        $this->assertSame('/leading-and-trailing/', (new Server())->getBaseUri());

        $this->withConfig(['laravelsabre.path' => '']);
        $this->assertSame('/', (new Server())->getBaseUri());
    }

    public function test_the_package_response_sender_is_injected()
    {
        $server = new Server();

        $this->assertInstanceOf(Sapi::class, $server->sapi);
    }

    public function test_no_request_is_read_from_the_process()
    {
        $server = $_SERVER;
        unset($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
        $_SERVER['HTTP_X_PROCESS_LEVEL'] = 'should-not-appear';

        try {
            $built = new Server();

            $this->assertNull($built->httpRequest->getHeader('X-Process-Level'));
        } finally {
            $_SERVER = $server;
        }
    }

    public function test_diagnostics_are_enabled_outside_production_and_disabled_in_it()
    {
        $this->runningInEnvironment('local');
        $this->assertTrue((new Server())->debugExceptions);

        $this->runningInEnvironment('testing');
        $this->assertTrue((new Server())->debugExceptions);

        $this->runningInEnvironment('production');
        $this->assertFalse((new Server())->debugExceptions);
    }
}

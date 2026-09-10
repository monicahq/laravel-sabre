<?php

namespace LaravelSabre\Tests\Integration;

use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Sabre\DAVACL\PrincipalBackend\Mock as PrincipalBackend;
use Sabre\DAVACL\PrincipalCollection;

/**
 * The master switch answers 404 before any node, plugin or provider is touched (FR-019).
 */
class MasterSwitchTest extends IntegrationTestCase
{
    private int $nodeCalls = 0;

    private int $pluginCalls = 0;

    private function countingRegistration(): void
    {
        $this->nodeCalls = 0;
        $this->pluginCalls = 0;

        LaravelSabre::nodes(function () {
            $this->nodeCalls++;

            return [new PrincipalCollection(new PrincipalBackend())];
        });

        LaravelSabre::plugins(function () {
            $this->pluginCalls++;

            return [];
        });
    }

    public function test_every_path_under_the_endpoint_is_404_when_disabled()
    {
        $this->withConfig(['laravelsabre.enabled' => false]);
        $this->countingRegistration();

        foreach (['/dav', '/dav/principals/admin', '/dav/deep/nested/path'] as $path) {
            $response = $this->call('PROPFIND', $path, [], [], [], $this->serverHeaders(['Depth' => '0']));

            $response->assertStatus(404);
            $response->assertHeaderMissing('X-Sabre-Version');
        }

        $this->assertSame(0, $this->nodeCalls, 'no node provider may run when disabled');
        $this->assertSame(0, $this->pluginCalls, 'no plugin provider may run when disabled');
    }

    public function test_the_endpoint_url_still_resolves_when_disabled()
    {
        $this->withConfig(['laravelsabre.enabled' => false]);

        $this->assertSame('http://localhost/dav', route('sabre.dav'));
    }

    public function test_the_endpoint_answers_again_when_enabled()
    {
        $this->withConfig(['laravelsabre.enabled' => true]);
        $this->countingRegistration();

        $response = $this->call('PROPFIND', '/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(207);
        $this->assertSame(1, $this->nodeCalls);
    }
}

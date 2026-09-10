<?php

namespace LaravelSabre\Tests\Integration;

use Illuminate\Support\Facades\Route;
use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Compatibility\Support\Fixtures;
use LaravelSabre\Tests\Sabre\DAVACL\PrincipalBackend\Mock as PrincipalBackend;
use LaravelSabre\Tests\Support\CaptureRequestPlugin;
use LaravelSabre\Tests\Support\InjectsHeaderAndUser;
use PHPUnit\Framework\Attributes\DataProvider;
use Sabre\DAV\FS\Directory;
use Sabre\DAV\Tree;
use Sabre\DAVACL\PrincipalCollection;

/**
 * The engine must process the framework request as it stands after middleware, identically in every
 * environment, and must never read the raw process request (FR-007, SC-004).
 *
 * In 1.x these assertions held only under the testing environment, because the adapter copied method,
 * body and headers from the framework request only there.
 */
class RequestFidelityTest extends IntegrationTestCase
{
    public static function environmentProvider(): array
    {
        return [
            'testing' => ['testing'],
            'local' => ['local'],
            'production' => ['production'],
        ];
    }

    #[DataProvider('environmentProvider')]
    public function test_the_engine_sees_the_request_as_middleware_left_it(string $environment)
    {
        $this->runningInEnvironment($environment);
        Route::pushMiddlewareToGroup('laravelsabre', InjectsHeaderAndUser::class);

        $capture = new CaptureRequestPlugin();
        LaravelSabre::nodes([new PrincipalCollection(new PrincipalBackend())]);
        LaravelSabre::plugin($capture);

        $response = $this->call('PROPFIND', '/dav/principals/admin?preview=1', [], [], [], $this->serverHeaders([
            'Depth' => '0',
            'X-From-Client' => 'client-value',
        ]));

        $response->assertStatus(207);
        $this->assertNotNull($capture->seen, 'the engine never ran in the '.$environment.' environment');
        $this->assertSame('PROPFIND', $capture->seen['method']);
        $this->assertSame('principals/admin', $capture->seen['path']);
        $this->assertSame('/dav/', $capture->seen['base']);
        $this->assertSame('client-value', $capture->header('X-From-Client'));
        $this->assertSame('yes', $capture->header('X-Injected-By-Middleware'));
        $this->assertSame(['preview' => '1'], $capture->seen['query']);
    }

    #[DataProvider('environmentProvider')]
    public function test_the_engine_receives_the_request_body_in_every_environment(string $environment)
    {
        $this->runningInEnvironment($environment);
        LaravelSabre::nodes(new Tree(new Directory(Fixtures::prepareFiles())));

        $written = $this->call('PUT', '/dav/written.txt', [], [], [], [], 'body written in '.$environment);
        $this->assertContains($written->getStatusCode(), [201, 204]);

        $read = $this->get('/dav/written.txt');
        $read->assertStatus(200);
        $this->assertSame('body written in '.$environment, $this->bodyOf($read));
    }

    #[DataProvider('environmentProvider')]
    public function test_the_same_request_produces_the_same_answer_in_every_environment(string $environment)
    {
        $this->runningInEnvironment($environment);
        LaravelSabre::nodes([new PrincipalCollection(new PrincipalBackend())]);

        $response = $this->call('PROPFIND', '/dav/principals/admin', [], [], [], $this->serverHeaders(['Depth' => '0']));

        $response->assertStatus(207);
        $response->assertSee('<d:href>/dav/principals/admin</d:href>', false);
    }

    public function test_diagnostic_detail_is_withheld_in_production_and_included_outside_it()
    {
        $this->runningInEnvironment('local');
        LaravelSabre::nodes([new PrincipalCollection(new PrincipalBackend())]);
        $local = $this->call('PROPFIND', '/dav/missing', [], [], [], $this->serverHeaders(['Depth' => '0']));
        $local->assertStatus(404);
        $local->assertSee('<s:stacktrace>', false);

        LaravelSabre::clear();
        $this->runningInEnvironment('production');
        LaravelSabre::nodes([new PrincipalCollection(new PrincipalBackend())]);
        $production = $this->call('PROPFIND', '/dav/missing', [], [], [], $this->serverHeaders(['Depth' => '0']));
        $production->assertStatus(404);
        $production->assertDontSee('<s:stacktrace>', false);
        $production->assertSee('Sabre\DAV\Exception\NotFound', false);
    }
}

<?php

namespace LaravelSabre\Sabre;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Sabre\DAV\Server as SabreServer;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The DAV engine, wired to the framework.
 *
 * Subclassing exists only to bridge framework concerns: the endpoint base URI comes from
 * configuration, the request comes from the framework, the response goes back through the framework,
 * and the engine is told to keep diagnostic detail out of production. Protocol behaviour stays in
 * sabre/dav.
 *
 * @internal
 */
final class Server extends SabreServer
{
    /**
     * @param  \Sabre\DAV\Tree|\Sabre\DAV\INode|array<int, mixed>|null  $treeOrNode
     */
    public function __construct($treeOrNode = null)
    {
        parent::__construct($treeOrNode, new Sapi());

        $this->setBaseUri(self::baseUri());
        $this->debugExceptions = App::environment('production') !== true;
    }

    /**
     * Run one framework request through the engine and return the framework response.
     */
    public function handle(Request $request): SymfonyResponse
    {
        $this->httpRequest = RequestFactory::fromLaravel($request, $this->getBaseUri());

        $this->start();

        return ResponseFactory::toLaravel($this->httpResponse);
    }

    /**
     * The endpoint base, normalised with a leading and trailing slash so that DAV href values in
     * responses follow the configured path.
     */
    public static function baseUri(): string
    {
        return Str::start(Str::finish((string) config('laravelsabre.path'), '/'), '/');
    }
}

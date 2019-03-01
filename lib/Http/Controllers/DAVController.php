<?php

namespace LaravelSabre\Http\Controllers;

use Illuminate\Http\Request;
use LaravelSabre\LaravelSabre;
use LaravelSabre\Sabre\Server;
use Illuminate\Routing\Controller;

class DAVController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function init(Request $request)
    {
        abort_if(! config('laravelsabre.enabled'), 404);

        $server = $this->getServer($request);

        $this->addPlugins($server);

        // Execute sabre requests
        $server->exec();

        return $server->getResponse();
    }

    /**
     * @return Server
     */
    private function getServer(Request $request)
    {
        $nodes = LaravelSabre::getNodes() ?: [];

        // Initiate Sabre server
        $server = new Server($nodes);
        $server->setRequest($request);

        return $server;
    }

    /**
     * Add required plugins.
     *
     * @return void
     */
    private function addPlugins(Server $server)
    {
        $plugins = LaravelSabre::getPlugins() ?: [];

        foreach ($plugins as $plugin) {
            $server->addPlugin($plugin);
        }
    }
}

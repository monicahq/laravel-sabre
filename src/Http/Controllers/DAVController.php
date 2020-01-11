<?php

namespace LaravelSabre\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaravelSabre\LaravelSabre;
use LaravelSabre\Sabre\Server;

class DAVController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\Response
     */
    public function init(Request $request)
    {
        abort_if(! config('laravelsabre.enabled'), 404);

        $server = $this->getServer($request);

        $this->addPlugins($server);

        // Execute sabre requests
        $server->start();

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

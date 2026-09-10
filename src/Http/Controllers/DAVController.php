<?php

namespace LaravelSabre\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaravelSabre\Registry;
use LaravelSabre\ServerFactory;
use Symfony\Component\HttpFoundation\Response;

final class DAVController extends Controller
{
    /**
     * Serve one DAV request.
     *
     * This is the route action, invoked by the framework router rather than from inside the package.
     *
     * @api
     */
    public function init(Request $request, Registry $registry): Response
    {
        return ServerFactory::make($registry)->handle($request);
    }
}

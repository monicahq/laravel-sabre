<?php

namespace LaravelSabre\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers 404 for every request under the DAV path while the endpoint is switched off, before any
 * tree, plugin or provider is touched.
 *
 * This middleware is attached by the package's own route group rather than through the `middleware`
 * config list, so that an application whose config file was published under 1.x, and therefore does
 * not mention this class, is still guarded by the switch.
 *
 * @internal
 */
final class EnsureEnabled
{
    /**
     * Handle the incoming request.
     *
     * Invoked by the framework's middleware pipeline rather than from inside the package.
     *
     * @api
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(! (bool) config('laravelsabre.enabled'), 404);

        return $next($request);
    }
}

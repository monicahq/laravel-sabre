<?php

namespace LaravelSabre\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LaravelSabre\LaravelSabre;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a request the application's access rule does not admit, before any DAV work happens.
 *
 * An exception thrown by the rule is left to propagate: a failing rule is never an admission.
 *
 * @api
 */
class Authorize
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! LaravelSabre::check($request)) {
            abort(403);
        }

        return $next($request);
    }
}

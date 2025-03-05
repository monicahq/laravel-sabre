<?php

namespace LaravelSabre\Http\Middleware;

use LaravelSabre\LaravelSabre;

/**
 * @psalm-suppress UnusedClass
 * @psalm-suppress ClassMustBeFinal
 */
class Authorize
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response|mixed|null
     */
    public function handle($request, $next)
    {
        if (! LaravelSabre::check($request)) {
            abort(403);
        }

        return $next($request);
    }
}

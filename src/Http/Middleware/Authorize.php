<?php

namespace LaravelSabre\Http\Middleware;

use LaravelSabre\LaravelSabre;

class Authorize
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     */
    public function handle($request, $next)
    {
        return LaravelSabre::check($request) ? $next($request) : abort(403);
    }
}

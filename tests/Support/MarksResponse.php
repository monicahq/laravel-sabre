<?php

namespace LaravelSabre\Tests\Support;

/**
 * Middleware that marks the response, so a test can prove a custom middleware list is applied.
 */
class MarksResponse
{
    public function handle($request, $next)
    {
        $response = $next($request);

        $response->headers->set('X-Custom-Middleware', 'ran');

        return $response;
    }
}

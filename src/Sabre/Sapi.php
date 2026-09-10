<?php

namespace LaravelSabre\Sabre;

use Sabre\HTTP\Request;
use Sabre\HTTP\ResponseInterface;
use Sabre\HTTP\Sapi as BaseSapi;

/**
 * The server-side interface the DAV engine uses to reach the outside world, neutralised.
 *
 * Upstream reason for this class, in sabre/dav 4.x:
 *
 * - `Sabre\DAV\Server::__construct()` ends with `$this->httpRequest = $this->sapi->getRequest()`,
 *   and `Sabre\HTTP\Sapi::getRequest()` reads `$_SERVER`, `php://input` and `$_POST`. Returning a
 *   blank request here is what lets the adapter hand the engine the framework request instead, with
 *   one code path in every environment.
 * - `Sabre\DAV\Server::start()` calls `$this->sapi->sendResponse()` from its own exception handler,
 *   which would emit headers and a body outside the framework response pipeline. Doing nothing here
 *   keeps the engine's error document available to the adapter as a normal response.
 *
 * Both overrides can be removed if sabre/dav ever accepts an externally supplied request and stops
 * sending responses itself.
 *
 * @internal
 */
final class Sapi extends BaseSapi
{
    /**
     * Return a blank request instead of reading the process environment.
     */
    #[\Override]
    public static function getRequest(): Request
    {
        return new Request('GET', '/');
    }

    /**
     * Do not send anything: the adapter returns the response through the framework instead.
     *
     * @return void
     */
    #[\Override]
    public static function sendResponse(ResponseInterface $response)
    {
        // Intentionally empty. See the class comment for the upstream reason.
    }
}

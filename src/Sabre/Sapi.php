<?php

namespace LaravelSabre\Sabre;

use Sabre\HTTP\ResponseInterface;
use Sabre\HTTP\Sapi as BaseSapi;

/**
 * Mock version of Sapi server to avoid 'header()' calls.
 */
final class Sapi extends BaseSapi
{
    /**
     * Sends the response to the client.
     *
     * @param  ResponseInterface  $response
     * @return void
     */
    #[\Override]
    public static function sendResponse(ResponseInterface $response)
    {
        // Remove header() calls
    }
}

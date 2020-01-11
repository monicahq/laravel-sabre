<?php

namespace LaravelSabre\Sabre;

use Sabre\HTTP\ResponseInterface;
use Sabre\HTTP\Sapi as BaseSapi;

/**
 * Mock version of Sapi server to avoid 'header()' calls.
 */
class Sapi extends BaseSapi
{
    public static function sendResponse(ResponseInterface $response)
    {
    }
}

<?php

namespace LaravelSabre\Sabre;

use Sabre\HTTP\Request;
use Sabre\HTTP\Sapi as BaseSapi;
use Sabre\HTTP\ResponseInterface;

/**
 * Mock version of Sapi server to avoid 'header()' calls.
 */
class Sapi extends BaseSapi
{
    public static function sendResponse(ResponseInterface $response)
    {
    }
}

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

    public static function getRequest() : Request
    {
        $r = self::createFromServerArray($_SERVER);
        $r->setBody(fopen('php://input', 'r'));
        $r->setPostData($_POST);

        return $r;
    }
}

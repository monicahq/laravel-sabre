<?php

namespace LaravelSabre\Sabre;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Sabre\DAV\Server as SabreServer;

class Server extends SabreServer
{
    /**
     * @psalm-suppress InvalidPropertyAssignmentValue
     * @psalm-suppress TooManyArguments
     *
     * @param \Sabre\DAV\Tree|\Sabre\DAV\INode|array|null $treeOrNode The tree object
     */
    public function __construct($treeOrNode = null)
    {
        parent::__construct($treeOrNode);

        /** @var \Sabre\HTTP\Sapi */
        $sapi = new Sapi();
        $this->sapi = $sapi;

        if (! App::environment('production')) {
            $this->debugExceptions = true;
        }
    }

    /**
     * @psalm-suppress UndefinedClass
     * @psalm-suppress TooManyArguments
     *
     * @return void
     */
    public function setRequest(Request $request)
    {
        // Base Uri of dav requests
        $this->setBaseUri($this->getBasePathUri());

        // Set Url with trailing slash
        $this->httpRequest->setUrl($this->fullUrl($request));

        if (App::environment('testing')) {
            // Testing needs request to be set manually
            $this->httpRequest->setMethod($request->method());
            $this->httpRequest->setBody($request->getContent(true));
            $this->httpRequest->setHeaders($request->headers->all());
        }
    }

    /**
     * Get response for Laravel.
     *
     * @return \Illuminate\Http\Response
     */
    public function getResponse()
    {
        // Transform to Laravel response
        $body = $this->httpResponse->getBody();
        $status = $this->httpResponse->getStatus();
        $headers = $this->httpResponse->getHeaders();

        if (is_string($body)) {
            return response($body, $status, $headers);
        }

        $contentLength = $this->httpResponse->getHeader('Content-Length');
        return response()->stream(function () use ($body, $contentLength) {
            if (is_int($contentLength) || (! is_null($contentLength) && ctype_digit($contentLength))) {
                echo stream_get_contents($body, $contentLength);
            } else {
                echo stream_get_contents($body);
            }
        }, $status, $headers);
    }

    /**
     * Get the full URL for the request.
     *
     * @return string
     */
    private function fullUrl(Request $request)
    {
        $query = $request->getQueryString();
        $url = str_finish($request->getPathInfo(), '/');

        return $query ? $url.'?'.$query : $url;
    }

    /**
     * @return string
     */
    public function getBasePathUri()
    {
        return str_start(str_finish(config('laravelsabre.path'), '/'), '/');
    }
}

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
    function __construct($treeOrNode = null)
    {
        parent::__construct($treeOrNode);

        /** @var \Sabre\HTTP\Sapi */
        $sapi = new Sapi();
        $this->sapi = $sapi;

        /** @var \Sabre\HTTP\Response */
        $response = new Response();
        $this->httpResponse = $response;

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
     * @return \Illuminate\Http\Response
     */
    public function getResponse()
    {
        /** @var \LaravelSabre\Sabre\Response */
        $httpResponse = $this->httpResponse;

        return $httpResponse->response;
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

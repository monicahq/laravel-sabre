<?php

namespace LaravelSabre\Sabre;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Sabre\DAV\Server as SabreServer;

use function Safe\ob_start;
use function Safe\ob_get_clean;
use function Safe\stream_get_contents;

final class Server extends SabreServer
{
    /**
     * Creates a new instance of Sabre Server.
     *
     * @param  \Sabre\DAV\Tree|\Sabre\DAV\INode|array|null  $treeOrNode  The tree object
     *
     * @psalm-suppress InvalidPropertyAssignmentValue
     * @psalm-suppress TooManyArguments
     */
    public function __construct($treeOrNode = null)
    {
        if (App::environment('testing') === true) {
            $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
            $_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        }

        parent::__construct($treeOrNode);

        $sapi = new Sapi();
        $this->sapi = $sapi;

        if (App::environment('production') === false) {
            $this->debugExceptions = true;
        }
    }

    /**
     * Set request from Laravel.
     *
     * @psalm-suppress UndefinedClass
     * @psalm-suppress TooManyArguments
     *
     * @param  Request  $request
     * @return void
     */
    public function setRequest(Request $request)
    {
        // Base Uri of dav requests
        $this->setBaseUri($this->getBasePathUri());

        // Set Url with trailing slash
        $this->httpRequest->setUrl(self::fullUrl($request));

        if (App::environment('testing') === true) {
            // Testing needs request to be set manually
            $this->httpRequest->setMethod($request->method());
            $this->httpRequest->setBody($request->getContent(true));
            $this->httpRequest->setHeaders($request->headers->all());
        }
    }

    /**
     * Get response for Laravel.
     *
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\Response
     *
     * @psalm-suppress InvalidReturnStatement
     */
    public function getResponse()
    {
        // Transform to Laravel response
        /** @var (callable(): mixed)|resource|string */
        $body = $this->httpResponse->getBody();
        $status = $this->httpResponse->getStatus();
        $headers = $this->httpResponse->getHeaders();

        if (is_string($body)) {
            return response($body, $status, $headers);
        }

        $contentLength = $this->httpResponse->getHeader('Content-Length');

        return response()->stream(function () use ($body, $contentLength): void {
            if (is_callable($body)) {
                ob_start();
                $body();
                echo ob_get_clean();
            } elseif (is_resource($body)) {
                $length = is_numeric($contentLength) || (! is_null($contentLength) && ctype_digit($contentLength))
                    ? intval($contentLength)
                    : null;
                echo stream_get_contents($body, $length);
            }
        }, $status, $headers);
    }

    /**
     * Get the full URL for the request.
     *
     * @param  Request  $request
     * @return string
     */
    private static function fullUrl(Request $request)
    {
        $query = $request->getQueryString();
        $url = Str::finish($request->getPathInfo(), '/');

        return is_null($query) ? $url : $url.'?'.$query;
    }

    /**
     * @return string
     */
    public function getBasePathUri()
    {
        return Str::start(Str::finish(config('laravelsabre.path'), '/'), '/');
    }
}

<?php

namespace LaravelSabre\Sabre;

use Sabre\HTTP\Response as BaseResponse;
use Illuminate\Http\Response as HttpResponse;

class Response extends BaseResponse
{
    /**
     * @var HttpResponse
     */
    public $response;

    /**
     * Creates the response object
     *
     * @param string|int $status
     * @param array $headers
     * @param resource $body
     */
    function __construct($status = null, array $headers = null, $body = null)
    {
        $this->response = new HttpResponse;

        parent::__construct($status, $headers, $body);
    }

    /**
     * Returns the body as a readable stream resource.
     *
     * Note that the stream may not be rewindable, and therefore may only be
     * read once.
     *
     * @return resource
     */
    function getBodyAsStream()
    {
        throw new \Exception('Not implemented');
    }

    /**
     * Returns the body as a string.
     *
     * Note that because the underlying data may be based on a stream, this
     * method could only work correctly the first time.
     *
     * @return string
     */
    function getBodyAsString()
    {
        return $this->response->getContent();
    }

    /**
     * Returns the message body, as it's internal representation.
     *
     * This could be either a string or a stream.
     *
     * @return resource|string
     */
    function getBody()
    {
        return $this->getBodyAsString();
    }

    /**
     * Updates the body resource with a new stream.
     *
     * @param resource|string $body
     * @return void
     */
    function setBody($body)
    {
        $this->response->setContent($body);
    }

    /**
     * Returns all the HTTP headers as an array.
     *
     * Every header is returned as an array, with one or more values.
     *
     * @return array
     */
    function getHeaders()
    {
        return $this->response->headers->all();
    }

    /**
     * Will return true or false, depending on if a HTTP header exists.
     *
     * @param string $name
     * @return bool
     */
    function hasHeader($name)
    {
        return $this->response->headers->has($name);
    }

    /**
     * Returns a specific HTTP header, based on it's name.
     *
     * The name must be treated as case-insensitive.
     * If the header does not exist, this method must return null.
     *
     * If a header appeared more than once in a HTTP request, this method will
     * concatenate all the values with a comma.
     *
     * Note that this not make sense for all headers. Some, such as
     * `Set-Cookie` cannot be logically combined with a comma. In those cases
     * you *should* use getHeaderAsArray().
     *
     * @param string $name
     * @return string|null
     */
    function getHeader($name)
    {
        $header = $this->response->headers->get($name);

        if (is_array($header)) {
            return $header[0];
        }

        return $header;
    }

    /**
     * Returns a HTTP header as an array.
     *
     * For every time the HTTP header appeared in the request or response, an
     * item will appear in the array.
     *
     * If the header did not exists, this method will return an empty array.
     *
     * @param string $name
     * @return string[]
     */
    function getHeaderAsArray($name)
    {
        $header = $this->response->headers->get($name);

        if (is_null($header)) {
            return [];
        } else if (is_string($header)) {
            return [$header];
        }

        return $header;
    }

    /**
     * Updates a HTTP header.
     *
     * The case-sensitity of the name value must be retained as-is.
     *
     * If the header already existed, it will be overwritten.
     *
     * @param string $name
     * @param string|string[] $value
     * @return void
     */
    function setHeader($name, $value)
    {
        $this->response->headers->set($name, $value, true);
    }

    /**
     * Sets a new set of HTTP headers.
     *
     * The headers array should contain headernames for keys, and their value
     * should be specified as either a string or an array.
     *
     * Any header that already existed will be overwritten.
     *
     * @param array $headers
     * @return void
     */
    function setHeaders(array $headers)
    {
        foreach ($headers as $name => $value)
        {
            $this->setHeader($name, $value);
        }
    }

    /**
     * Adds a HTTP header.
     *
     * This method will not overwrite any existing HTTP header, but instead add
     * another value. Individual values can be retrieved with
     * getHeadersAsArray.
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    function addHeader($name, $value)
    {
        $this->response->headers->set($name, $value, false);
    }

    /**
     * Adds a new set of HTTP headers.
     *
     * Any existing headers will not be overwritten.
     *
     * @param array $headers
     * @return void
     */
    function addHeaders(array $headers)
    {
        foreach ($headers as $name => $value)
        {
            $this->addHeader($name, $value);
        }
    }

    /**
     * Removes a HTTP header.
     *
     * The specified header name must be treated as case-insenstive.
     * This method should return true if the header was successfully deleted,
     * and false if the header did not exist.
     *
     * @param string $name
     * @return bool
     */
    function removeHeader($name)
    {
        $this->response->headers->remove($name);
        return true;
    }

    /**
     * Sets the HTTP version.
     *
     * Should be 1.0 or 1.1.
     *
     * @param string $version
     * @return void
     */
    function setHttpVersion($version)
    {
        $this->response->setProtocolVersion($version);
    }

    /**
     * Returns the HTTP version.
     *
     * @return string
     */
    function getHttpVersion()
    {
        return $this->response->getProtocolVersion();
    }

    /**
     * Returns the current HTTP status code.
     *
     * @return int
     */
    function getStatus()
    {
        return $this->response->getStatusCode();
    }

    /**
     * Returns the human-readable status string.
     *
     * In the case of a 200, this may for example be 'OK'.
     *
     * @return string
     */
    function getStatusText()
    {
        throw new \Exception('Not implemented');
    }

    /**
     * Sets the HTTP status code.
     *
     * This can be either the full HTTP status code with human readable string,
     * for example: "403 I can't let you do that, Dave".
     *
     * Or just the code, in which case the appropriate default message will be
     * added.
     *
     * @param string|int $status
     * @throws \InvalidArgumentException
     * @return void
     */
    function setStatus($status)
    {
        if (is_int($status)) {
            $this->response->setStatusCode($status);
        }
    }
}

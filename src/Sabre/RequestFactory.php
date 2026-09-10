<?php

namespace LaravelSabre\Sabre;

use Illuminate\Http\Request;
use LogicException;
use Sabre\HTTP\Request as SabreRequest;

/**
 * Builds the engine's request from the framework request.
 *
 * Everything comes from the request object as it stands after middleware: no superglobal is read, so
 * the result is the same in every environment and under any server, including long-lived workers.
 *
 * @internal
 */
final class RequestFactory
{
    /**
     * @param  string  $baseUri  The endpoint base, with a leading and trailing slash.
     */
    public static function fromLaravel(Request $request, string $baseUri): SabreRequest
    {
        $sabreRequest = new SabreRequest(
            $request->getMethod(),
            $request->getRequestUri(),
            $request->headers->all(),
            self::body($request)
        );

        $sabreRequest->setBaseUrl($baseUri);

        return $sabreRequest;
    }

    /**
     * The body as a stream, so a large upload is never materialised as a string.
     *
     * @return resource|string
     */
    private static function body(Request $request)
    {
        try {
            return $request->getContent(true);
        } catch (LogicException $alreadyConsumed) {
            // Something upstream already read the body as a resource; fall back to the buffered copy
            // the framework kept, so the engine still sees a body.
            return $request->getContent();
        }
    }
}

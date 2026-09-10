<?php

namespace LaravelSabre\Sabre;

use Illuminate\Http\Response;
use Sabre\HTTP\ResponseInterface;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Safe\fclose;
use function Safe\fopen;
use function Safe\stream_copy_to_stream;

/**
 * Translates the engine's response into a framework response.
 *
 * String bodies become an ordinary response. Stream and generated bodies become a streamed response
 * so the body is never held in memory, and a valid declared content length caps the transfer.
 *
 * @internal
 */
final class ResponseFactory
{
    public static function toLaravel(ResponseInterface $engineResponse): SymfonyResponse
    {
        return self::translate(
            $engineResponse->getBody(),
            $engineResponse->getStatus(),
            $engineResponse->getHeaders(),
            self::declaredLength($engineResponse)
        );
    }

    /**
     * Narrow whatever the engine put in the body and build the matching framework response.
     *
     * The body parameter is deliberately untyped: sabre/http documents a string, a stream or a
     * callable, but a response built without a body carries null, so every shape is checked here
     * rather than trusted from the upstream docblock.
     *
     * @param  mixed  $body
     * @param  array<string, array<int, string>>  $headers
     */
    private static function translate($body, int $status, array $headers, ?int $length): SymfonyResponse
    {
        if (is_resource($body)) {
            return new StreamedResponse(function () use ($body, $length): void {
                self::copyToOutput($body, $length);
            }, $status, $headers);
        }

        if (is_callable($body)) {
            return new StreamedResponse(function () use ($body): void {
                // The engine writes generated bodies straight to output.
                $body();
            }, $status, $headers);
        }

        return new Response(is_string($body) ? $body : '', $status, $headers);
    }

    /**
     * @param  resource  $body
     */
    private static function copyToOutput($body, ?int $length): void
    {
        $output = fopen('php://output', 'wb');

        try {
            stream_copy_to_stream($body, $output, $length);
        } finally {
            fclose($output);
        }
    }

    /**
     * The declared length, when it is a usable non-negative integer.
     */
    private static function declaredLength(ResponseInterface $engineResponse): ?int
    {
        $value = $engineResponse->getHeader('Content-Length');

        if (is_null($value)) {
            return null;
        }

        $value = trim($value);

        return ctype_digit($value) ? (int) $value : null;
    }
}

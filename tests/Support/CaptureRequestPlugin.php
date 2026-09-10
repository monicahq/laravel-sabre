<?php

namespace LaravelSabre\Tests\Support;

use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * Records what the DAV engine actually received, so a test can assert the engine sees the
 * framework request rather than the raw process request.
 */
class CaptureRequestPlugin extends ServerPlugin
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $seen = null;

    public function initialize(Server $server)
    {
        $server->on('beforeMethod:*', function (RequestInterface $request, ResponseInterface $response): void {
            $this->seen = [
                'method' => $request->getMethod(),
                'url' => $request->getUrl(),
                'path' => $request->getPath(),
                'base' => $request->getBaseUrl(),
                'headers' => $request->getHeaders(),
                'query' => $request->getQueryParameters(),
            ];
        }, 5);
    }

    public function header(string $name): ?string
    {
        $headers = $this->seen['headers'] ?? [];

        foreach ($headers as $key => $values) {
            if (strtolower((string) $key) === strtolower($name)) {
                return is_array($values) ? implode(', ', $values) : (string) $values;
            }
        }

        return null;
    }
}

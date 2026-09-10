<?php

namespace LaravelSabre\Tests\Compatibility\Support;

/**
 * Reads and writes the recorded 1.x request and response pairs used to prove the upgrade path.
 *
 * Recordings are captured once, from the 1.x source, before the rebuild replaces it. Replaying them
 * afterwards is what proves parity (US4, SC-003). Volatile values are normalised on both sides so a
 * recording stays comparable across runs and machines.
 */
final class Recorder
{
    /**
     * Headers whose value changes between runs or depends on the engine version.
     */
    private const VOLATILE_HEADERS = [
        'date',
        'set-cookie',
        'cache-control',
        'expires',
        'pragma',
        'x-sabre-version',
        'last-modified',
        'etag',
        'lock-token',
        'x-powered-by',
        'content-security-policy',
    ];

    public static function directory(): string
    {
        return __DIR__.'/../recordings';
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, array<int, string>>  $headers
     */
    public static function write(string $name, array $scenario, int $status, array $headers, string $body): void
    {
        $payload = [
            'name' => $name,
            'scenario' => $scenario,
            'expected' => [
                'status' => $status,
                'headers' => self::normalizeHeaders($headers),
                'body' => self::normalizeBody($body),
            ],
            'deviation' => $scenario['deviation'] ?? null,
        ];

        file_put_contents(
            self::directory().'/'.$name.'.json',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        $files = glob(self::directory().'/*.json') ?: [];
        sort($files);

        $recordings = [];
        foreach ($files as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $recordings[] = $decoded;
            }
        }

        return $recordings;
    }

    /**
     * Drop headers that cannot be compared, and lower-case the rest so comparison is stable.
     *
     * @param  array<string, array<int, string>|string>  $headers
     * @return array<string, array<int, string>>
     */
    public static function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $key = strtolower((string) $name);
            if (in_array($key, self::VOLATILE_HEADERS, true)) {
                continue;
            }

            $normalized[$key] = array_values(array_map('strval', is_array($value) ? $value : [$value]));
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * Replace values that differ per run: lock tokens, modification times, etags and temp paths.
     */
    public static function normalizeBody(string $body): string
    {
        $replacements = [
            '/opaquelocktoken:[0-9a-f-]+/i' => 'opaquelocktoken:TOKEN',
            '/urn:uuid:[0-9a-f-]+/i' => 'urn:uuid:TOKEN',
            '/<d:getlastmodified[^>]*>[^<]*<\/d:getlastmodified>/i' => '<d:getlastmodified>TIME</d:getlastmodified>',
            '/<d:getetag[^>]*>[^<]*<\/d:getetag>/i' => '<d:getetag>ETAG</d:getetag>',
            '/<s:file>[^<]*<\/s:file>/i' => '<s:file>FILE</s:file>',
            '/<s:line>[^<]*<\/s:line>/i' => '<s:line>LINE</s:line>',
            '/<s:stacktrace>[^<]*<\/s:stacktrace>/is' => '<s:stacktrace>TRACE</s:stacktrace>',
            '/<s:sabredav-version>[^<]*<\/s:sabredav-version>/i' => '<s:sabredav-version>VERSION</s:sabredav-version>',
            '/\/tmp\/[A-Za-z0-9_.-]+/' => '/tmp/PATH',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $body = (string) preg_replace($pattern, $replacement, $body);
        }

        return trim($body);
    }
}

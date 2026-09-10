<?php

namespace LaravelSabre\Tests\Compatibility\Support;

/**
 * The representative client requests recorded against 1.x and replayed against the rebuild.
 *
 * Each entry names the fixture that must be registered, the request to send, and, where the rebuild
 * deliberately changes behaviour, the authorised deviation with its before and after description.
 */
final class Scenarios
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'propfind-principal-depth-0' => [
                'fixture' => 'principals',
                'method' => 'PROPFIND',
                'uri' => '/dav/principals/admin',
                'headers' => ['Depth' => '0'],
                'body' => '',
            ],
            'propfind-principal-depth-1' => [
                'fixture' => 'principals',
                'method' => 'PROPFIND',
                'uri' => '/dav/principals',
                'headers' => ['Depth' => '1'],
                'body' => '',
            ],
            'propfind-principal-named-properties' => [
                'fixture' => 'principals',
                'method' => 'PROPFIND',
                'uri' => '/dav/principals/admin',
                'headers' => ['Depth' => '0', 'Content-Type' => 'application/xml'],
                'body' => '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:displayname/><d:resourcetype/></d:prop></d:propfind>',
            ],
            'propfind-encoded-path' => [
                'fixture' => 'files',
                'method' => 'PROPFIND',
                'uri' => '/dav/hello%20world.txt',
                'headers' => ['Depth' => '0'],
                'body' => '',
            ],
            'propfind-with-query-string' => [
                'fixture' => 'files',
                'method' => 'PROPFIND',
                'uri' => '/dav/hello.txt?preview=1',
                'headers' => ['Depth' => '0'],
                'body' => '',
            ],
            'get-root-nothing-registered' => [
                'fixture' => 'empty',
                'method' => 'GET',
                'uri' => '/dav',
                'headers' => [],
                'body' => '',
            ],
            'get-file' => [
                'fixture' => 'files',
                'method' => 'GET',
                'uri' => '/dav/hello.txt',
                'headers' => [],
                'body' => '',
            ],
            'head-file' => [
                'fixture' => 'files',
                'method' => 'HEAD',
                'uri' => '/dav/hello.txt',
                'headers' => [],
                'body' => '',
            ],
            'options-root' => [
                'fixture' => 'files',
                'method' => 'OPTIONS',
                'uri' => '/dav',
                'headers' => [],
                'body' => '',
            ],
            'put-new-file' => [
                'fixture' => 'files',
                'method' => 'PUT',
                'uri' => '/dav/created.txt',
                'headers' => ['Content-Type' => 'text/plain'],
                'body' => 'created by the compatibility suite',
            ],
            'put-existing-file' => [
                'fixture' => 'files',
                'method' => 'PUT',
                'uri' => '/dav/hello.txt',
                'headers' => ['Content-Type' => 'text/plain'],
                'body' => 'overwritten',
            ],
            'delete-file' => [
                'fixture' => 'files',
                'method' => 'DELETE',
                'uri' => '/dav/hello.txt',
                'headers' => [],
                'body' => '',
            ],
            'mkcol-collection' => [
                'fixture' => 'files',
                'method' => 'MKCOL',
                'uri' => '/dav/newdir',
                'headers' => [],
                'body' => '',
            ],
            'copy-file' => [
                'fixture' => 'files',
                'method' => 'COPY',
                'uri' => '/dav/hello.txt',
                'headers' => ['Destination' => '/dav/copied.txt'],
                'body' => '',
            ],
            'move-file' => [
                'fixture' => 'files',
                'method' => 'MOVE',
                'uri' => '/dav/hello.txt',
                'headers' => ['Destination' => '/dav/moved.txt'],
                'body' => '',
            ],
            'proppatch-file' => [
                'fixture' => 'files',
                'method' => 'PROPPATCH',
                'uri' => '/dav/hello.txt',
                'headers' => ['Content-Type' => 'application/xml'],
                'body' => '<?xml version="1.0"?><d:propertyupdate xmlns:d="DAV:"><d:set><d:prop><d:displayname>renamed</d:displayname></d:prop></d:set></d:propertyupdate>',
            ],
            'report-expand-property' => [
                'fixture' => 'principals',
                'method' => 'REPORT',
                'uri' => '/dav/principals/admin',
                'headers' => ['Depth' => '0', 'Content-Type' => 'application/xml'],
                'body' => '<?xml version="1.0"?><d:expand-property xmlns:d="DAV:"><d:property name="displayname"/></d:expand-property>',
            ],
            'report-unsupported' => [
                'fixture' => 'principals',
                'method' => 'REPORT',
                'uri' => '/dav/principals/admin',
                'headers' => ['Depth' => '0', 'Content-Type' => 'application/xml'],
                'body' => '<?xml version="1.0"?><d:no-such-report xmlns:d="DAV:"/>',
            ],
            'lock-file' => [
                'fixture' => 'locks',
                'method' => 'LOCK',
                'uri' => '/dav/hello.txt',
                'headers' => ['Content-Type' => 'application/xml'],
                'body' => '<?xml version="1.0"?><d:lockinfo xmlns:d="DAV:"><d:lockscope><d:exclusive/></d:lockscope><d:locktype><d:write/></d:locktype><d:owner><d:href>mailto:tester@example.com</d:href></d:owner></d:lockinfo>',
            ],
            'unlock-unknown-token' => [
                'fixture' => 'locks',
                'method' => 'UNLOCK',
                'uri' => '/dav/hello.txt',
                'headers' => ['Lock-Token' => '<opaquelocktoken:00000000-0000-0000-0000-000000000000>'],
                'body' => '',
            ],
            'anonymous-request-is-challenged' => [
                'fixture' => 'auth-required',
                'method' => 'PROPFIND',
                'uri' => '/dav/hello.txt',
                'headers' => ['Depth' => '0'],
                'body' => '',
            ],
            'access-rule-denies' => [
                'fixture' => 'denied',
                'method' => 'PROPFIND',
                'uri' => '/dav/hello.txt',
                'headers' => ['Depth' => '0'],
                'body' => '',
            ],
            'mkcalendar-is-routed' => [
                'fixture' => 'principals',
                'method' => 'MKCALENDAR',
                'uri' => '/dav/calendars/admin/default',
                'headers' => ['Content-Type' => 'application/xml'],
                'body' => '<?xml version="1.0"?><c:mkcalendar xmlns:c="urn:ietf:params:xml:ns:caldav"/>',
                'deviation' => 'MKCALENDAR was rejected by the router before reaching the engine in 1.x; the rebuild routes it so the engine answers.',
            ],
            'acl-is-routed' => [
                'fixture' => 'principals',
                'method' => 'ACL',
                'uri' => '/dav/principals/admin',
                'headers' => ['Content-Type' => 'application/xml'],
                'body' => '<?xml version="1.0"?><d:acl xmlns:d="DAV:"><d:ace><d:principal><d:href>/dav/principals/admin</d:href></d:principal><d:grant><d:privilege><d:read/></d:privilege></d:grant></d:ace></d:acl>',
                'deviation' => 'ACL was rejected by the router before reaching the engine in 1.x; the rebuild routes it so the engine answers.',
            ],
        ];
    }
}

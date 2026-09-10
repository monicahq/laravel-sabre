<?php

namespace LaravelSabre\Tests\Support;

use Closure;
use Sabre\DAV\File;

/**
 * A DAV file whose body is produced by a closure, so a test can serve a string, a stream or a
 * generated body without touching the filesystem.
 */
class CallableFile extends File
{
    private string $name;

    private Closure $body;

    private ?int $size;

    private string $contentType;

    public function __construct(string $name, Closure $body, ?int $size = null, string $contentType = 'text/plain')
    {
        $this->name = $name;
        $this->body = $body;
        $this->size = $size;
        $this->contentType = $contentType;
    }

    public function getName()
    {
        return $this->name;
    }

    public function get()
    {
        return ($this->body)();
    }

    public function getSize()
    {
        return $this->size;
    }

    public function getContentType()
    {
        return $this->contentType;
    }
}

<?php

namespace Atoms\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

class StreamFactory
{
    /**
     * Create a new stream from a string.
     *
     * The stream SHOULD be created with a temporary resource.
     *
     * @param  string $content
     * @return \Psr\Http\Message\StreamInterface
     */
    public static function createStream($content = ''): StreamInterface
    {
        $resource = fopen('php://temp', 'r+');

        $stream = new Stream($resource);
        $stream->write($content);

        return $stream;
    }

    /**
     * Create a stream from an existing file.
     *
     * The file MUST be opened using the given mode, which may be any mode
     * supported by the `fopen` function.
     *
     * The `$filename` MAY be any string supported by `fopen()`.
     *
     * @param  string $filename
     * @param  string $mode
     * @return \Psr\Http\Message\StreamInterface
     */
    public static function createStreamFromFile($filename, $mode = 'r'): StreamInterface
    {
        if (($resource = @fopen($filename, $mode)) === false) {
            throw new InvalidArgumentException('Invalid file; could not open ' . $filename);
        }

        return new Stream($resource);
    }

    /**
     * Create a new stream from an existing resource.
     *
     * The stream MUST be readable and may be writable.
     *
     * @param  resource $resource
     * @return \Psr\Http\Message\StreamInterface
     */
    public static function createStreamFromResource($resource): StreamInterface
    {
        if (!is_resource($resource)) {
            throw new InvalidArgumentException('Invalid resource; must be a valid resource');
        }

        return new Stream($resource);
    }
}

<?php

declare(strict_types=1);

namespace Atoms\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class StreamFactory implements StreamFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createStream(string $content = ''): StreamInterface
    {
        $resource = fopen('php://temp', 'r+');

        $stream = new Stream($resource);
        $stream->write($content);

        return $stream;
    }

    /**
     * {@inheritDoc}
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        if (($resource = @fopen($filename, $mode)) === false) {
            throw new InvalidArgumentException('Invalid file; could not open ' . $filename);
        }

        return new Stream($resource);
    }

    /**
     * {@inheritDoc}
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        if (!is_resource($resource)) {
            throw new InvalidArgumentException('Invalid resource; must be a valid resource');
        }

        return new Stream($resource);
    }
}

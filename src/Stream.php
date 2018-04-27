<?php

namespace Atom\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class Stream implements StreamInterface
{
    /**
     * @var resource|null
     */
    protected $resource;

    /**
     * Creates a new Stream instance.
     *
     * @param resource $resource
     */
    public function __construct($resource)
    {
        if (!is_resource($resource) || get_resource_type($resource) !== 'stream') {
            throw new InvalidArgumentException('Invalid resource; must be a valid resource');
        }

        $this->resource = $resource;
    }

    /**
     * Closes the stream when the object is desctructed.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        if (!$this->isReadable()) {
            return '';
        }

        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }
        } catch (RuntimeException $e) {
            return '';
        }

        return $this->getContents();
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        if (is_resource($resource = $this->detach())) {
            @fclose($resource);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function detach()
    {
        if (!is_resource($this->resource)) {
            return null;
        }

        $resource = $this->resource;
        $this->resource = null;

        return $resource;
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(): ?int
    {
        if (!is_resource($this->resource)) {
            return null;
        }

        return fstat($this->resource)['size'];
    }

    /**
     * {@inheritDoc}
     */
    public function tell(): int
    {
        if (!is_resource($this->resource)) {
            throw new RuntimeException('No resource available; cannot tell position');
        }

        $position = ftell($this->resource);

        if (!is_int($position)) {
            throw new RuntimeException('Unable to determine stream position');
        }

        return $position;
    }

    /**
     * {@inheritDoc}
     */
    public function eof(): bool
    {
        if (!is_resource($this->resource)) {
            return true;
        }

        return feof($this->resource);
    }

    /**
     * {@inheritDoc}
     */
    public function isSeekable(): bool
    {
        if (!is_resource($this->resource)) {
            return false;
        }

        return stream_get_meta_data($this->resource)['seekable'];
    }

    /**
     * {@inheritDoc}
     */
    public function seek($offset, $whence = SEEK_SET): void
    {
        if (!is_resource($this->resource)) {
            throw new RuntimeException('No resource available; cannot seek');
        }

        if (!$this->isSeekable()) {
            throw new RuntimeException('Stream not seekable');
        }

        if (fseek($this->resource, $offset, $whence) === -1) {
            throw new RuntimeException('Error seeking within stream');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function rewind(): void
    {
        if (!$this->isSeekable()) {
            throw new RuntimeException('Unable to rewind; stream not seekable');
        }

        $this->seek(0);
    }

    /**
     * {@inheritDoc}
     */
    public function isWritable(): bool
    {
        if (!is_resource($this->resource)) {
            return false;
        }

        $mode = stream_get_meta_data($this->resource)['mode'];

        return (
            strstr($mode, 'x') ||
            strstr($mode, 'w') ||
            strstr($mode, 'c') ||
            strstr($mode, 'a') ||
            strstr($mode, '+')
        );
    }

    /**
     * {@inheritDoc}
     */
    public function write($string): int
    {
        if (!is_resource($this->resource)) {
            throw new RuntimeException('No resource available; cannot write');
        }

        if (!$this->isWritable()) {
            throw new RuntimeException('Stream not writable');
        }

        $result = fwrite($this->resource, $string);

        if ($result === false) {
            throw new RuntimeException('Error writing to stream');
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function isReadable(): bool
    {
        if (!is_resource($this->resource)) {
            return false;
        }

        $mode = stream_get_meta_data($this->resource)['mode'];

        return (strstr($mode, 'r') || strstr($mode, '+'));
    }

    /**
     * {@inheritDoc}
     */
    public function read($length)
    {
        if (!is_resource($this->resource)) {
            throw new RuntimeException('No resource available; cannot read');
        }

        if (!$this->isReadable()) {
            throw new RuntimeException('Stream not readable');
        }

        $result = fread($this->resource, $length);

        if ($length === false) {
            throw new RuntimeException('Error reading from stream');
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getContents(): string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream not readable');
        }

        $result = stream_get_contents($this->resource);

        if ($result === false) {
            throw new RuntimeException('Error reading from stream');
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata($key = null)
    {
        if (!isset($this->resource)) {
            return is_null($key) ? null : [];
        }

        if (is_null($key)) {
            return stream_get_meta_data($this->resource);
        }

        $meta = stream_get_meta_data($this->resource);

        return $meta[$key] ?? null;
    }
}

<?php

declare(strict_types=1);

namespace Atoms\Http;

use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

class Message implements MessageInterface
{
    /**
     * A map of all registered headers, as original name => array of values.
     *
     * @var array
     */
    protected $headers = [];

    /**
     * A map of lowercase header name => original name at registration.
     *
     * @var string[]
     */
    protected $headerNames = [];

    /**
     * @var string
     */
    private $protocol;

    /**
     * @var \Psr\Http\Message\StreamInterface
     */
    private $stream;

    /**
     * Creates a new Message instance.
     *
     * @param array $headers
     * @param \Psr\Http\Message\StreamInterface $body
     * @param string $protocol
     */
    public function __construct(
        StreamInterface $body,
        array $headers = [],
        string $protocol = '1.1'
    ) {
        $this->stream = $body;
        $this->setHeaders($headers);
        $this->protocol = $protocol;
    }

    /**
     * {@inheritDoc}
     */
    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    /**
     * {@inheritDoc}
     */
    public function withProtocolVersion($protocol): self
    {
        if (!is_string($protocol) || $protocol === '') {
            throw new InvalidArgumentException('Invalid protocol version; must be non-empty string');
        }

        /**
         * HTTP/1 uses a "<major>.<minor>" numbering scheme to indicate
         * versions of the protocol, while HTTP/2 does not.
         */
        if (!preg_match('#^(1\.[01]|2)$#', $protocol)) {
            throw new InvalidArgumentException('Invalid protocol version; unsupported HTTP protocol');
        }

        if ($this->protocol === $protocol) {
            return $this;
        }

        $new = clone $this;
        $new->protocol = $protocol;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * {@inheritDoc}
     */
    public function hasHeader($name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    /**
     * {@inheritDoc}
     */
    public function getHeader($name): array
    {
        $name = strtolower($name);

        if (!isset($this->headerNames[$name])) {
            return [];
        }

        $name = $this->headerNames[$name];

        return $this->headers[$name];
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaderLine($name): string
    {
        return implode(',', $this->getHeader($name));
    }

    /**
     * {@inheritDoc}
     */
    public function withHeader($name, $value): self
    {
        self::assertValidHeaderName($name);

        if (!is_array($value)) {
            $value = [$value];
        }

        foreach ($value as &$v) {
            self::assertValidHeaderValue($v);

            $v = (string)$v;
        }

        $value = self::trimHeaderValues($value);
        $normalizedName = strtolower($name);

        $new = clone $this;

        if (isset($new->headerNames[$normalizedName])) {
            unset($new->headers[$new->headerNames[$normalizedName]]);
        }

        $new->headerNames[$normalizedName] = $name;
        $new->headers[$name] = $value;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withAddedHeader($name, $value): self
    {
        self::assertValidHeaderName($name);

        if (!is_array($value)) {
            $value = [$value];
        }

        foreach ($value as &$v) {
            self::assertValidHeaderValue($v);

            $v = (string)$v;
        }

        $value = self::trimHeaderValues($value);
        $normalizedName = strtolower($name);

        $new = clone $this;

        if (isset($new->headerNames[$normalizedName])) {
            $header = $new->headerNames[$normalizedName];
            $new->headers[$header] = array_merge($this->headers[$header], $value);
        } else {
            $new->headerNames[$normalizedName] = $name;
            $new->headers[$name] = $value;
        }

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withoutHeader($name): self
    {
        if (!$this->hasHeader($name)) {
            return $this;
        }

        $normalizedName = strtolower($name);

        $header = $this->headerNames[$normalizedName];

        $new = clone $this;

        unset($new->headers[$header], $new->headerNames[$normalizedName]);

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function getBody(): StreamInterface
    {
        return $this->stream;
    }

    /**
     * {@inheritDoc}
     */
    public function withBody(StreamInterface $body): self
    {
        if ($body === $this->stream) {
            return $this;
        }

        $new = clone $this;
        $new->stream = $body;

        return $new;
    }

    /**
     * Sets the message headers.
     *
     * @param array $originalHeaders
     */
    private function setHeaders(array $originalHeaders): void
    {
        $headers = [];
        $headerNames = [];

        foreach ($originalHeaders as $name => $value) {
            self::assertValidHeaderName($name);

            if (!is_array($value)) {
                $value = [$value];
            }

            foreach ($value as &$v) {
                self::assertValidHeaderValue($v);

                $v = (string)$v;
            }

            $value = self::trimHeaderValues($value);

            $headers[$name] = $value;
            $headerNames[strtolower($name)] = $name;
        }

        $this->headers = $headers;
        $this->headerNames = $headerNames;
    }

    /**
     * Asserts that a header name is valid.
     *
     * @param mixed $name
     */
    private static function assertValidHeaderName($name): void
    {
        if (!is_string($name) || $name === '') {
            throw new InvalidArgumentException('Invalid header name; must be non-empty string');
        }

        if (! preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $name)) {
            throw new InvalidArgumentException('Invalid header name; contains illegal characters');
        }
    }

    /**
     * Asserts that a header value is valid.
     *
     * @param mixed $value
     */
    private static function assertValidHeaderValue($value): void
    {
        if (!is_string($value) && !is_numeric($value)) {
            throw new InvalidArgumentException('Invalid header value; must be string or numeric');
        }

        /**
         * Look for the following:
         * \n not preceded by \r, OR
         * \r not followed by \n, OR
         * \r\n not followed by space or horizontal tab; these are all CRLF attacks
         */
        if (preg_match("#(?:(?:(?<!\r)\n)|(?:\r(?!\n))|(?:\r\n(?![ \t])))#", $value)) {
            throw new InvalidArgumentException('Invalid header value; contains illegal characters');
        }

        /**
         * Non-visible, non-whitespace characters:
         * 9 === horizontal tab
         * 10 === line feed
         * 13 === carriage return
         * 32-126, 128-254 === visible
         * 127 === DEL (disallowed)
         * 255 === null byte (disallowed)
         */
        if (preg_match('/[^\x09\x0a\x0d\x20-\x7E\x80-\xFE]/', $value)) {
            throw new InvalidArgumentException('Invalid header value; contains illegal characters');
        }
    }

    /**
     * Trims whitespaces from the header values.
     *
     * @param  array $values
     * @return array
     * @see    https://tools.ietf.org/html/rfc7230#section-3.2.4
     */
    private static function trimHeaderValues(array $values): array
    {
        return array_map(function ($value) {
            return trim($value, " \t");
        }, $values);
    }
}

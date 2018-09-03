<?php

declare(strict_types=1);

namespace Atoms\Http;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Request extends Message implements RequestInterface
{
    /**
     * @var string
     */
    private $method;

    /**
     * @var string|null
     */
    private $requestTarget;

    /**
     * @var \Psr\Http\Message\UriInterface
     */
    private $uri;

    /**
     * Creates a new Request instance.
     *
     * @param \Psr\Http\Message\StreamInterface $body
     * @param \Psr\Http\Message\UriInterface $uri
     * @param string $method
     * @param array $headers
     * @param string $protocol
     */
    public function __construct(
        StreamInterface $body,
        UriInterface $uri,
        string $method = '',
        array $headers = [],
        string $protocol = '1.1'
    ) {
        parent::__construct($body, $headers, $protocol);

        self::assertValidMethod($method);

        $this->method = $method ? strtoupper($method) : '';
        $this->uri = $uri;

        if (!$this->hasHeader('Host')) {
            $this->updateHostFromUri();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getRequestTarget(): string
    {
        if (!is_null($this->requestTarget)) {
            return $this->requestTarget;
        }

        $path = $this->uri->getPath();
        $query = $this->uri->getQuery();

        if ($query !== '') {
            $path .= '?' . $query;
        }

        return $path ?: '/';
    }

    /**
     * {@inheritDoc}
     */
    public function withRequestTarget($requestTarget): self
    {
        if (preg_match('#\s#', $requestTarget)) {
            throw new InvalidArgumentException('Invalid request target; cannot contain whitespace');
        }

        $new = clone $this;
        $new->requestTarget = $requestTarget;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * {@inheritDoc}
     */
    public function withMethod($method): self
    {
        self::assertValidMethod($method);

        $new = clone $this;
        $new->method = $method;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * {@inheritDoc}
     */
    public function withUri(UriInterface $uri, $preserveHost = false): self
    {
        if ($uri === $this->uri) {
            return $this;
        }

        $new = clone $this;
        $new->uri = $uri;

        if (!$preserveHost || !$this->hasHeader('Host')) {
            $new->updateHostFromUri();
        }

        return $new;
    }

    /**
     * Updates the host header with data from the URI.
     */
    private function updateHostFromUri(): void
    {
        $host = $this->uri->getHost();

        if ($host === '') {
            return;
        }

        if (!is_null($this->uri->getPort())) {
            $host .= ':' . $this->uri->getPort();
        }

        /**
         * Remove an existing host header if present, regardless of current
         * de-normalization of the header name.
         */
        if (isset($this->headerNames['host'])) {
            unset($this->headers[$this->headerNames['host']]);
        }

        $header = 'Host';
        $this->headerNames['host'] = $header;

        /**
         * Make sure Host is the first header.
         *
         * @see http://tools.ietf.org/html/rfc7230#section-5.4
         */
        $this->headers = [$header => [$host]] + $this->headers;
    }

    /**
     * Asserts that a method is valid.
     *
     * @param mixed $method
     */
    private static function assertValidMethod($method): void
    {
        if (is_null($method) || $method === '') {
            return;
        }

        if (!is_string($method)) {
            throw new InvalidArgumentException('Invalid method; must be a string');
        }

        if (!preg_match('/^[!#$%&\'*+.^_`\|~0-9a-z-]+$/i', $method)) {
            throw new InvalidArgumentException("Invalid method; {$method} is unsupported");
        }
    }
}

<?php

namespace Atom\Http;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
    /**
     * Unreserved characters used in user info, paths, query strings, and fragments.
     *
     * @const string
     */
    const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';

    /**
     * Sub-delimiters used in user info, query strings and fragments.
     *
     * @const string
     */
    const CHAR_SUBDELIMS = '!\$&\'\(\)\*\+,;=';

    /**
     * Array indexed by valid scheme names to their corresponding ports.
     *
     * @var int[]
     */
    private static $schemes = [
        'http'  => 80,
        'https' => 443
    ];

    /**
     * @var string
     */
    private $scheme = '';

    /**
     * @var string
     */
    private $userInfo = '';

    /**
     * @var string
     */
    private $host = '';

    /**
     * @var int|null
     */
    private $port;

    /**
     * @var string
     */
    private $path = '';

    /**
     * @var string
     */
    private $query = '';

    /**
     * @var string
     */
    private $fragment = '';

    /**
     * Creates a new Uri instance.
     *
     * @param string $uri
     */
    public function __construct($uri = '')
    {
        if (!is_string($uri)) {
            throw new InvalidArgumentException('Invalid URI; must be string');
        }

        if (!empty($uri)) {
            $this->parseUri($uri);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * {@inheritDoc}
     */
    public function getAuthority(): string
    {
        if (empty($this->host)) {
            return '';
        }

        $authority = $this->host;

        if (!empty($this->userInfo)) {
            $authority = $this->userInfo . '@' . $authority;
        }

        if (!is_null($this->port) && self::isNonStandardPort($this->port, $this->scheme)) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    /**
     * {@inheritDoc}
     */
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    /**
     * {@inheritDoc}
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * {@inheritDoc}
     */
    public function getPort(): ?int
    {
        return !is_null($this->port) && self::isNonStandardPort($this->port, $this->scheme)
            ? $this->port
            : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * {@inheritDoc}
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * {@inheritDoc}
     */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * {@inheritDoc}
     */
    public function withScheme($scheme): self
    {
        if (!is_string($scheme)) {
            throw new InvalidArgumentException('Scheme must be a string');
        }

        $scheme = $this->filterScheme($scheme);

        if ($scheme === $this->scheme) {
            return $this;
        }

        $new = clone $this;
        $new->scheme = $scheme;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withUserInfo($user, $password = null): self
    {
        if (!is_string($user)) {
            throw new InvalidArgumentException('User must be a string');
        }

        if (!is_string($password) && !is_null($password)) {
            throw new InvalidArgumentException('Password must be a string or null');
        }

        $info = $this->filterUserInfoPart($user);

        if (!empty($password)) {
            $info .= ':' . $this->filterUserInfoPart($password);
        }

        if ($info === $this->userInfo) {
            return $this;
        }

        $new = clone $this;
        $new->userInfo = $info;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withHost($host): self
    {
        if (!is_string($host)) {
            throw new InvalidArgumentException('Host must be a string');
        }

        $host = $this->filterHost($host);

        if ($host === $this->host) {
            return $this;
        }

        $new = clone $this;
        $new->host = $host;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withPort($port): self
    {
        if (!is_numeric($port) && !is_null($port)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid port: %s; must be an integer or null",
                (is_object($port) ? get_class($port) : gettype($port))
            ));
        }

        if (!is_null($port)) {
            $port = (int)$port;
        }

        if ($port === $this->port) {
            return $this;
        }

        if (!is_null($port) && ($port < 1 || $port > 65535)) {
            throw new InvalidArgumentException("Invalid port: {$port}; must be between 1-65535");
        }

        $new = clone $this;
        $new->port = $port;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withPath($path): self
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException('Invalid path; must be a string');
        }

        if (strpos($path, '?') !== false) {
            throw new InvalidArgumentException(
                'Invalid path; must not contain a query string'
            );
        }

        if (strpos($path, '#') !== false) {
            throw new InvalidArgumentException(
                'Invalid path; must not contain a URI fragment'
            );
        }

        $path = $this->filterPath($path);

        if ($path === $this->path) {
            return $this;
        }

        $new = clone $this;
        $new->path = $path;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withQuery($query): self
    {
        if (!is_string($query)) {
            throw new InvalidArgumentException('Invalid query; must be a string');
        }

        if (strpos($query, '#') !== false) {
            throw new InvalidArgumentException(
                'Invalid query; must not contain a URI fragment'
            );
        }

        $query = $this->filterQuery($query);

        if ($query === $this->query) {
            return $this;
        }

        $new = clone $this;
        $new->query = $query;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function withFragment($fragment): self
    {
        if (!is_string($fragment)) {
            throw new InvalidArgumentException('Invalid fragment; must be a string');
        }

        $fragment = $this->filterQueryAndFragment($fragment);

        if ($fragment === $this->fragment) {
            return $this;
        }

        $new = clone $this;
        $new->fragment = $fragment;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return self::createUriString(
            $this->scheme,
            $this->getAuthority(),
            $this->path,
            $this->query,
            $this->fragment
        );
    }

    /**
     * Create a URI string from its various parts.
     *
     * @param  string $scheme
     * @param  string $authority
     * @param  string $path
     * @param  string $query
     * @param  string $fragment
     * @return string
     */
    public function createUriString(
        string $scheme,
        string $authority,
        string $path,
        string $query,
        string $fragment
    ): string {
        $uri = '';

        if ($scheme !== '') {
            $uri .= $scheme . ':';
        }

        if ($authority !== '') {
            $uri .= '//' . $authority;
        }

        if ($path !== '') {
            if ($path[0] !== '/' && $authority !== '') {
                /**
                 * If the path is rootless and an authority is present, the path MUST
                 * be prefixed by "/",
                 */
                $path = '/' . $path;
            } elseif (isset($path[1]) && $path[1] === '/' &&  $authority === '') {
                /**
                 * If the path is starting with more than one "/" and no authority is
                 * present, the starting slashes MUST be reduced to one.
                 */
                $path = '/' . ltrim($path, '/');
            }

            $uri .= $path;
        }

        if ($query !== '') {
            $uri .= '?' . $query;
        }

        if ($fragment !== '') {
            $uri .= '#' . $fragment;
        }

        return $uri;
    }

    /**
     * Parse a URI into parts and populate the properties.
     *
     * @param  string $uri
     * @throws \InvalidArgumentException
     */
    private function parseUri(string $uri): void
    {
        $parts = parse_url($uri);

        if ($parts === false) {
            throw new InvalidArgumentException('Unable to parse URI: ' . $uri);
        }

        $this->scheme = isset($parts['scheme']) ? $this->filterScheme($parts['scheme']) : '';
        $this->userInfo = isset($parts['user']) ? $this->filterUserInfoPart($parts['user']) : '';
        $this->host = isset($parts['host']) ? $this->filterHost($parts['host']) : '';
        $this->port = isset($parts['port']) ? $parts['port'] : null;
        $this->path = isset($parts['path']) ? $this->filterPath($parts['path']) : '';
        $this->query = isset($parts['query']) ? $this->filterQueryAndFragment($parts['query']) : '';
        $this->fragment = isset($parts['fragment']) ? $this->filterQueryAndFragment($parts['fragment']) : '';

        if (isset($parts['pass'])) {
            $this->userInfo .= ':' . $parts['pass'];
        }
    }

    /**
     * Filters the URI scheme.
     *
     * @param  string $scheme
     * @return string
     * @throws \InvalidArgumentException
     */
    private function filterScheme($scheme): string
    {
        $scheme = strtolower($scheme);
        $scheme = preg_replace('#:(//)?$#', '', $scheme);

        if (empty($scheme)) {
            return '';
        }

        if (!array_key_exists($scheme, self::$schemes)) {
            throw new InvalidArgumentException(
                "Invalid scheme: {$scheme}; must be in the set (" .
                implode(', ', array_keys(self::$schemes)) . ') or null'
            );
        }

        return $scheme;
    }

    /**
     * Filters the URI user or password part.
     *
     * @param  string $part
     * @return string
     */
    private function filterUserInfoPart($part): string
    {
        return preg_replace_callback(
            '/(?:[^%' . self::CHAR_UNRESERVED . self::CHAR_SUBDELIMS . ']+|%(?![A-Fa-f0-9]{2}))/u',
            [$this, 'urlEncodeChar'],
            $part
        );
    }

    /**
     * Filters the URI host.
     *
     * @param  mixed $host
     * @return string
     * @throws \InvalidArgumentException
     */
    private function filterHost($host): string
    {
        return strtolower($host);
    }

    /**
     * Filters the URI path.
     *
     * @param  mixed $path
     * @return string
     */
    private function filterPath($path): string
    {
        $path = preg_replace_callback(
            '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUBDELIMS.'%:@\/]++|%(?![A-Fa-f0-9]{2}))/',
            [$this, 'urlEncodeChar'],
            $path
        );

        if (empty($path)) {
            return $path;
        }

        /** Check for relative path */
        if ($path[0] !== '/') {
            return $path;
        }

        /** Ensure only one leading slash to prevent XSS attempts */
        return '/' . ltrim($path, '/');
    }

    /**
     * Filters the URI query.
     *
     * @param  string $query
     * @return string
     */
    private function filterQuery($query): string
    {
        if (!empty($query) && strpos($query, '?') === 0) {
            $query = substr($query, 1);
        }

        return $this->filterQueryAndFragment($query);
    }

    /**
     * Filters the URI query string or fragment.
     *
     * @param  string $string
     * @return string
     */
    private function filterQueryAndFragment($string): string
    {
        return preg_replace_callback(
            '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUBDELIMS . '%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/',
            [$this, 'urlEncodeChar'],
            $string
        );
    }

    /**
     * URL encode a character returned by a regex.
     *
     * @param  array $matches
     * @return string
     */
    private function urlEncodeChar(array $matches): string
    {
        return rawurlencode($matches[0]);
    }

    /**
     * Checks wether a given port is non-standard for the scheme.
     *
     * @param  int $port
     * @param  string $scheme
     * @return bool
     */
    private static function isNonStandardPort(int $port, string $scheme): bool
    {
        return !isset(self::$schemes[$scheme]) || $port !== self::$schemes[$scheme];
    }
}

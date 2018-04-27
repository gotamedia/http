<?php

namespace Atom\Http;

use Psr\Http\Message\UriInterface;

class UriFactory
{
    /**
     * Creates a new URI from the server global.
     *
     * @param  array $server
     * @return \Psr\Http\Message\UriInterface
     */
    public static function createUriFromArray(array $server): UriInterface
    {
        $uri = new Uri();

        /** Try to find the scheme */
        if (isset($server['REQUEST_SCHEME'])) {
            $uri = $uri->withScheme($server['REQUEST_SCHEME']);
        } elseif (isset($server['HTTPS'])) {
            $uri = $uri->withScheme($server['HTTPS'] !== 'off' ? 'https' : 'http');
        }

        /** Try to find the host */
        if (isset($server['HTTP_HOST'])) {
            $uri = $uri->withHost($server['HTTP_HOST']);
        } elseif (isset($server['SERVER_NAME'])) {
            $uri = $uri->withHost($server['SERVER_NAME']);
        }

        /** Try to find the port */
        if (isset($server['SERVER_PORT'])) {
            $uri = $uri->withPort($server['SERVER_PORT']);
        }

        /** Try to find the path */
        if (isset($server['REQUEST_URI'])) {
            $uri = $uri->withPath(current(explode('?', $server['REQUEST_URI'])));
        }

        /** Try to find the query string */
        if (isset($server['QUERY_STRING'])) {
            $uri = $uri->withQuery($server['QUERY_STRING']);
        }

        return $uri;
    }
}

<?php

declare(strict_types=1);

namespace Atoms\Http;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriInterface;

class RequestFactory implements RequestFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createRequest(string $method, $uri): RequestInterface
    {
        if (is_string($uri)) {
            $uri = (new UriFactory())->createUri($uri);
        }

        return new Request($method, $uri);
    }
}

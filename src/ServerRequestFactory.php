<?php

declare(strict_types=1);

namespace Atoms\Http;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

class ServerRequestFactory implements ServerRequestFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        if (!$uri instanceof UriInterface) {
            $uri = new Uri($uri);
        }

        return new ServerRequest($method, $uri, StreamFactory::createStream(''));
    }

    /**
     * Creates a new server request from server variables.
     *
     * @param  array $server Typically $_SERVER or similar structure.
     * @return \Psr\Http\Message\ServerRequestInterface
     * @throws \InvalidArgumentException
     */
    public function createServerRequestFromArray(array $server): ServerRequestInterface
    {
        $method = self::getMethod($server);
        $uri = UriFactory::createUriFromArray($server);

        return new ServerRequest($method, $uri, StreamFactory::createStream(''));
    }

    /**
     * Creates a new, complete server request from global server variables.
     *
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    public function createServerRequestFromGlobals(): ServerRequestInterface
    {
        $serverParams = $_SERVER;
        $cookieParams = $_COOKIE;
        $queryParams = $_GET;
        $uploadedFiles = self::normalizeFiles($_FILES);
        $parsedBody = $_POST;

        $uri = UriFactory::createUriFromArray($serverParams);
        $body = StreamFactory::createStreamFromFile('php://input');

        $method = self::getMethod($serverParams);
        $headers = self::getHeaders($serverParams);
        $protocol = self::getProtocol($serverParams);

        return new ServerRequest(
            $method,
            $uri,
            $body,
            $headers,
            $protocol,
            $serverParams,
            $cookieParams,
            $queryParams,
            $uploadedFiles,
            $parsedBody
        );
    }

    /**
     * Returns the request HTTP method.
     *
     * @param  array $server
     * @return string
     * @throws \InvalidArgumentException
     */
    private static function getMethod(array $server): string
    {
        if (!isset($server['REQUEST_METHOD'])) {
            throw new InvalidArgumentException('Cannot determine HTTP method');
        }

        return $server['REQUEST_METHOD'];
    }

    /**
     * Returns the request protocol version.
     *
     * @param  array $server
     * @return string
     */
    private static function getProtocol(array $server): string
    {
        return isset($server['SERVER_PROTOCOL'])
            ? str_replace('HTTP/', '', $server['SERVER_PROTOCOL'])
            : '1.1';
    }

    /**
     * Returns an array with UploadedFile instances.
     *
     * @param  array $files
     * @return \Atoms\Http\UploadedFile[]
     * @throws \InvalidArgumentException
     */
    private static function normalizeFiles(array $files): array
    {
        $normalizedFiles = [];

        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalizedFiles[$key] = $value;
            } elseif (is_array($value) && isset($value['tmp_name'])) {
                $normalizedFiles[$key] = self::createUploadedFileFromSpec($value);
            } elseif (is_array($value)) {
                $normalizedFiles[$key] = self::normalizeFiles($value);
            } else {
                throw new InvalidArgumentException('Invalid value in file specifications');
            }
        }

        return $normalizedFiles;
    }

    /**
     * Returns an UploadedFile instance from a $_FILES specification.
     *
     * If the specification represents an array of values, the method will
     * delegate to normalizeNestedFileSpec() and return that value.
     *
     * @param  array $specification
     * @return \Atoms\Http\UploadedFile|array
     */
    private static function createUploadedFileFromSpec(array $specification)
    {
        if (is_array($specification['tmp_name'])) {
            return self::normalizeNestedFileSpec($specification);
        }

        return new UploadedFile(
            $specification['tmp_name'],
            $specification['size'],
            $specification['error'],
            $specification['name'],
            $specification['type']
        );
    }

    /**
     * Normalizes an array of file specifications.
     *
     * Loops through all nested files and returns a normalized array of
     * UploadedFileInterface instances.
     *
     * @param  array $files
     * @return \Atoms\Http\UploadedFile[]
     */
    private static function normalizeNestedFileSpec(array $files): array
    {
        $normalizedFiles = [];

        foreach (array_keys($files['tmp_name']) as $key) {
            $specification = [
                'tmp_name' => $files['tmp_name'][$key],
                'size' => $files['size'][$key],
                'error' => $files['error'][$key],
                'name' => $files['name'][$key],
                'type' => $files['type'][$key]
            ];

            $normalizedFiles[$key] = self::createUploadedFileFromSpec($specification);
        }

        return $normalizedFiles;
    }

    /**
     * Returns all request headers.
     *
     * @param  array $server
     * @return array
     */
    private static function getHeaders(array $server): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];

        foreach ($server as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$name] = $value;
            } elseif ($name == 'CONTENT_TYPE' || $name == 'CONTENT_LENGTH') {
                $headers[ucwords(strtolower($name), '_')] = $value;
            }
        }

        return $headers;
    }
}

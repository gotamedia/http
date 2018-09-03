<?php

declare(strict_types=1);

namespace Atoms\Http;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Response extends Message implements ResponseInterface
{
    /**
     * @var int The minimal HTTP status code value
     */
    const MIN_STATUS_CODE = 100;

    /**
     * @var int The maximal HTTP status code value
     */
    const MAX_STATUS_CODE = 599;

    /**
     * @var array Map of standard HTTP status code/reason phrases
     */
    const PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    /**
     * @var int
     */
    private $statusCode = 200;

    /**
     * @var string
     */
    private $reasonPhrase;

    /**
     * Creates a new Response instance.
     *
     * @param \Psr\Http\Message\StreamInterface $body
     * @param int $statusCode
     * @param array $headers
     * @param string $protocol
     * @param string $reasonPhrase
     */
    public function __construct(
        StreamInterface $body,
        int $statusCode = 200,
        array $headers = [],
        string $protocol = '1.1',
        string $reasonPhrase = ''
    ) {
        parent::__construct($body, $headers, $protocol);

        $this->setStatusCode($statusCode);
        $this->reasonPhrase = $reasonPhrase;
    }

    /**
     * {@inheritDoc}
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * {@inheritDoc}
     */
    public function withStatus($code, $reasonPhrase = ''): self
    {
        $new = clone $this;
        $new->setStatusCode($code);
        $new->reasonPhrase = $reasonPhrase;

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase === '' && isset(static::PHRASES[$this->statusCode])
            ? static::PHRASES[$this->statusCode]
            : $this->reasonPhrase;
    }

    /**
     * Sets the response status code.
     *
     * @param mixed $statusCode
     */
    private function setStatusCode($statusCode): void
    {
        if (!is_numeric($statusCode) || is_float($statusCode)) {
            throw new InvalidArgumentException('Invalid status code; must be an integer');
        }

        if ($statusCode < self::MIN_STATUS_CODE || $statusCode > self::MAX_STATUS_CODE) {
            throw new InvalidArgumentException(sprintf(
                'Invalid status code; must be an integer between %d and %d',
                static::MIN_STATUS_CODE,
                static::MAX_STATUS_CODE
            ));
        }

        $this->statusCode = (int)$statusCode;
    }
}

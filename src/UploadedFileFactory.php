<?php

declare(strict_types=1);

namespace Atoms\Http;

use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;

class UploadedFileFactory implements UploadedFileFactoryInterface
{
    /**
     * {@inheritDoc}
     */
     public function createUploadedFile(
         StreamInterface $stream,
         int $size = null,
         int $error = \UPLOAD_ERR_OK,
         string $clientFilename = null,
         string $clientMediaType = null
     ): UploadedFileInterface
     {
         return new UploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
     }
}

<?php

declare(strict_types=1);

namespace Atom\Http;

use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class UploadedFile implements UploadedFileInterface
{
    /**
     * @var int[]
     */
    private static $errors = [
        UPLOAD_ERR_OK,
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE,
        UPLOAD_ERR_PARTIAL,
        UPLOAD_ERR_NO_FILE,
        UPLOAD_ERR_NO_TMP_DIR,
        UPLOAD_ERR_CANT_WRITE,
        UPLOAD_ERR_EXTENSION,
    ];

    /**
     * @var \Psr\Http\Message\StreamInterface|null
     */
    private $stream;

    /**
     * @var string|null
     */
    private $file;

    /**
     * @var int
     */
    private $size;

    /**
     * @var int
     */
    private $error;

    /**
     * @var string|null
     */
    private $clientFilename;

    /**
     * @var string|null
     */
    private $clientMediaType;

    /**
     * @var bool
     */
    private $moved = false;

    /**
     * Creates a new UploadedFile instance.
     *
     * @param \Psr\Http\Message\StreamInterface|resource|string $streamOrFile
     * @param int $size
     * @param int $error
     * @param string|null $clientFilename
     * @param string|null $clientMediaType
     */
    public function __construct(
        $streamOrFile,
        int $size,
        int $error,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ) {
        if (in_array($error, self::$errors) === false) {
            throw new InvalidArgumentException('Invalid error status for UploadedFile');
        }

        if (!is_string($clientFilename) && !is_null($clientFilename)) {
            throw new InvalidArgumentException('Invalid filename; must be string or null');
        }

        if (!is_string($clientMediaType) && !is_null($clientMediaType)) {
            throw new InvalidArgumentException('Invalid media type; must be string or null');
        }

        $this->setStreamOrFile($streamOrFile);
        $this->size = $size;
        $this->error = $error;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;
    }

    /**
     * {@inheritDoc}
     */
    public function getStream(): StreamInterface
    {
        if ($this->moved) {
            throw new RuntimeException('Cannot retrieve stream; already moved');
        }

        if (!$this->isOk()) {
            throw new RuntimeException('Cannot retrieve stream; upload error');
        }

        if ($this->stream instanceof StreamInterface) {
            return $this->stream;
        }

        return new Stream(fopen($this->file, 'r+'));
    }

    /**
     * {@inheritDoc}
     */
    public function moveTo($targetPath): void
    {
        if ($this->moved) {
            throw new RuntimeException('Cannot move file; already moved');
        }

        if (!$this->isOk()) {
            throw new RuntimeException('Cannot move file; upload error');
        }

        if (!is_string($targetPath) || empty($targetPath)) {
            throw new InvalidArgumentException('Invalid target path; must be a non-empty string');
        }

        if (!is_null($this->file)) {
            $this->moved = php_sapi_name() == 'cli'
                ? rename($this->file, $targetPath)
                : move_uploaded_file($this->file, $targetPath);
        } else {
            $handle = fopen($targetPath, 'wb+');

            if ($handle === false) {
                throw new RuntimeException('Unable to write to designated path');
            }

            $stream = $this->getStream();
            $stream->rewind();

            while (!$stream->eof()) {
                fwrite($handle, $stream->read(4096));
            }

            fclose($handle);

            $this->moved = true;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * {@inheritDoc}
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * {@inheritDoc}
     */
    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    /**
     * {@inheritDoc}
     */
    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }

    private function setStreamOrFile($streamOrFile): void
    {
        if (is_string($streamOrFile)) {
            $this->file = $streamOrFile;
        } elseif (is_resource($streamOrFile)) {
            $this->stream = new Stream($streamOrFile);
        } elseif ($streamOrFile instanceof StreamInterface) {
            $this->stream = $streamOrFile;
        } else {
            throw new InvalidArgumentException('Invalid file or stream provided');
        }
    }

    /**
     * Returns whether or not the file was uploaded without errors.
     *
     * @return bool
     */
    private function isOk(): bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }
}

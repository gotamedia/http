<?php

declare(strict_types=1);

/**
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Atoms\Http;

use Atoms\Http\Stream;
use Atoms\Http\StreamFactory;
use Atoms\Http\UploadedFile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

class UploadedFileTest extends TestCase
{
    protected $tmpFile;

    public function setUp()
    {
        $this->tmpfile = null;
    }

    public function tearDown()
    {
        if (is_scalar($this->tmpFile) && file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function invalidStreams()
    {
        return [
            'null'         => [null],
            'true'         => [true],
            'false'        => [false],
            'int'          => [1],
            'float'        => [1.1],
            /* Have not figured out a valid way to test an invalid path yet; null byte injection
             * appears to get caught by fopen()
            'invalid-path' => [ ('WIN' === strtoupper(substr(PHP_OS, 0, 3))) ? '[:]' : 'foo' . chr(0) ],
             */
            'array'        => [['filename']],
            'object'       => [(object) ['filename']],
        ];
    }

    /**
     * @dataProvider invalidStreams
     */
    public function testRaisesExceptionOnInvalidStreamOrFile($streamOrFile)
    {
        $this->expectException(InvalidArgumentException::class);

        new UploadedFile($streamOrFile, 0, UPLOAD_ERR_OK);
    }

    public function invalidSizes()
    {
        return [
            'null'   => [null],
            'true'   => [true],
            'false'  => [false],
            'float'  => [1.1],
            'string' => ['1'],
            'array'  => [[1]],
            'object' => [(object) [1]],
        ];
    }

    /**
     * @dataProvider invalidSizes
     */
    // public function testRaisesExceptionOnInvalidSize($size)
    // {
    //     $this->expectException(InvalidArgumentException::class);
    //     $this->expectExceptionMessage('size');
    //
    //     new UploadedFile(fopen('php://temp', 'wb+'), $size, UPLOAD_ERR_OK);
    // }

    public function testValidSize()
    {
        $uploaded = new UploadedFile(fopen('php://temp', 'wb+'), 123, UPLOAD_ERR_OK);

        $this->assertSame(123, $uploaded->getSize());
    }

    public function invalidErrorStatuses()
    {
        return [
            // 'null'     => [null],
            // 'true'     => [true],
            // 'false'    => [false],
            // 'float'    => [1.1],
            // 'string'   => ['1'],
            // 'array'    => [[1]],
            // 'object'   => [(object) [1]],
            'negative' => [-1],
            'too-big'  => [9],
        ];
    }

    /**
     * @dataProvider invalidErrorStatuses
     */
    public function testRaisesExceptionOnInvalidErrorStatus($status)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('status');

        new UploadedFile(fopen('php://temp', 'wb+'), 0, $status);
    }

    public function invalidFilenamesAndMediaTypes()
    {
        return [
            'true'   => [true],
            'false'  => [false],
            'int'    => [1],
            'float'  => [1.1],
            'array'  => [['string']],
            'object' => [(object) ['string']],
        ];
    }

    /**
     * @dataProvider invalidFilenamesAndMediaTypes
     */
    // public function testRaisesExceptionOnInvalidClientFilename($filename)
    // {
    //     $this->expectException(InvalidArgumentException::class);
    //     $this->expectExceptionMessage('filename');
    //
    //     new UploadedFile(fopen('php://temp', 'wb+'), 0, UPLOAD_ERR_OK, $filename);
    // }

    public function testValidClientFilename()
    {
        $file = new UploadedFile(fopen('php://temp', 'wb+'), 0, UPLOAD_ERR_OK, 'boo.txt');
        $this->assertSame('boo.txt', $file->getClientFilename());
    }

    public function testValidNullClientFilename()
    {
        $file = new UploadedFile(fopen('php://temp', 'wb+'), 0, UPLOAD_ERR_OK, null);
        $this->assertSame(null, $file->getClientFilename());
    }

    /**
     * @dataProvider invalidFilenamesAndMediaTypes
     */
    // public function testRaisesExceptionOnInvalidClientMediaType($mediaType)
    // {
    //     $this->expectException(InvalidArgumentException::class);
    //     $this->expectExceptionMessage('media type');
    //
    //     new UploadedFile(fopen('php://temp', 'wb+'), 0, UPLOAD_ERR_OK, 'foobar.baz', $mediaType);
    // }

    public function testValidClientMediaType()
    {
        $file = new UploadedFile(fopen('php://temp', 'wb+'), 0, UPLOAD_ERR_OK, 'foobar.baz', 'mediatype');
        $this->assertSame('mediatype', $file->getClientMediaType());
    }

    public function testGetStreamReturnsOriginalStreamObject()
    {
        $stream = new Stream(fopen('php://temp', 'wb+'));
        $upload = new UploadedFile($stream, 0, UPLOAD_ERR_OK);
        $this->assertSame($stream, $upload->getStream());
    }

    public function testGetStreamReturnsWrappedPhpStream()
    {
        $stream = fopen('php://temp', 'wb+');
        $upload = new UploadedFile($stream, 0, UPLOAD_ERR_OK);
        $uploadStream = $upload->getStream()->detach();
        $this->assertSame($stream, $uploadStream);
    }

    public function testGetStreamReturnsStreamForFile()
    {
        $this->tmpFile = $stream = tempnam(sys_get_temp_dir(), 'diac');
        $upload = new UploadedFile($stream, 0, UPLOAD_ERR_OK);
        $uploadStream = $upload->getStream();
        $r = new ReflectionProperty($uploadStream, 'resource');
        $r->setAccessible(true);
        $this->assertSame($stream, $uploadStream->getMetadata()['uri']);
        //$this->assertSame($stream, $r->getValue($uploadStream));
    }

    public function testMovesFileToDesignatedPath()
    {
        $stream = new Stream(fopen('php://temp', 'wb+'));
        $stream->write('Foo bar!');
        $upload = new UploadedFile($stream, 0, UPLOAD_ERR_OK);

        $this->tmpFile = $to = tempnam(sys_get_temp_dir(), 'diac');
        $upload->moveTo($to);
        $this->assertTrue(file_exists($to));
        $contents = file_get_contents($to);
        $this->assertSame($stream->__toString(), $contents);
    }

    public function invalidMovePaths()
    {
        return [
            'null'   => [null],
            // 'true'   => [true],
            // 'false'  => [false],
            // 'int'    => [1],
            // 'float'  => [1.1],
            'empty'  => [''],
            'array'  => [['filename']],
            'object' => [(object) ['filename']],
        ];
    }

    /**
     * @dataProvider invalidMovePaths
     */
    public function testMoveRaisesExceptionForInvalidPath($path)
    {
        $stream = new Stream(fopen('php://temp', 'wb+'));
        $stream->write('Foo bar!');
        $upload = new UploadedFile($stream, 0, UPLOAD_ERR_OK);

        $this->tmpFile = $path;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('path');

        $upload->moveTo($path);
    }

    public function testMoveCannotBeCalledMoreThanOnce()
    {
        $stream = new Stream(fopen('php://temp', 'wb+'));
        $stream->write('Foo bar!');
        $upload = new UploadedFile($stream, 0, UPLOAD_ERR_OK);

        $this->tmpFile = $to = tempnam(sys_get_temp_dir(), 'diac');
        $upload->moveTo($to);
        $this->assertTrue(file_exists($to));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('moved');

        $upload->moveTo($to);
    }

    public function testCannotRetrieveStreamAfterMove()
    {
        $stream = new Stream(fopen('php://temp', 'wb+'));
        $stream->write('Foo bar!');
        $upload = new UploadedFile($stream, 0, UPLOAD_ERR_OK);

        $this->tmpFile = $to = tempnam(sys_get_temp_dir(), 'diac');
        $upload->moveTo($to);
        $this->assertTrue(file_exists($to));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('moved');

        $upload->getStream();
    }

    public function nonOkErrorStatus()
    {
        return [
            'UPLOAD_ERR_INI_SIZE'   => [ UPLOAD_ERR_INI_SIZE ],
            'UPLOAD_ERR_FORM_SIZE'  => [ UPLOAD_ERR_FORM_SIZE ],
            'UPLOAD_ERR_PARTIAL'    => [ UPLOAD_ERR_PARTIAL ],
            'UPLOAD_ERR_NO_FILE'    => [ UPLOAD_ERR_NO_FILE ],
            'UPLOAD_ERR_NO_TMP_DIR' => [ UPLOAD_ERR_NO_TMP_DIR ],
            'UPLOAD_ERR_CANT_WRITE' => [ UPLOAD_ERR_CANT_WRITE ],
            'UPLOAD_ERR_EXTENSION'  => [ UPLOAD_ERR_EXTENSION ],
        ];
    }

    /**
     * @dataProvider nonOkErrorStatus
     * @group 60
     */
    public function testConstructorDoesNotRaiseExceptionForInvalidStreamWhenErrorStatusPresent($status)
    {
        $uploadedFile = new UploadedFile('not ok', 0, $status);
        $this->assertSame($status, $uploadedFile->getError());
    }

    /**
     * @dataProvider nonOkErrorStatus
     * @group 60
     */
    public function testMoveToRaisesExceptionWhenErrorStatusPresent($status)
    {
        $uploadedFile = new UploadedFile('not ok', 0, $status);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('upload error');

        $uploadedFile->moveTo(__DIR__ . '/' . uniqid());
    }

    /**
     * @dataProvider nonOkErrorStatus
     * @group 60
     */
    public function testGetStreamRaisesExceptionWhenErrorStatusPresent($status)
    {
        $uploadedFile = new UploadedFile('not ok', 0, $status);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('upload error');

        $uploadedFile->getStream();
    }

    /**
     * @group 82
     */
    // public function testMoveToCreatesStreamIfOnlyAFilenameWasProvided()
    // {
    //     $this->tmpFile = tempnam(sys_get_temp_dir(), 'DIA');
    //
    //     $uploadedFile = new UploadedFile(__FILE__, 100, UPLOAD_ERR_OK, basename(__FILE__), 'text/plain');
    //     $uploadedFile->moveTo($this->tmpFile);
    //
    //     $original = file_get_contents(__FILE__);
    //     $test     = file_get_contents($this->tmpFile);
    //
    //     $this->assertSame($original, $test);
    // }

    public function errorConstantsAndMessages()
    {
        // foreach (UploadedFile::ERROR_MESSAGES as $constant => $message) {
        //     if ($constant === \UPLOAD_ERR_OK) {
        //         continue;
        //     }
        //     yield $constant => [$constant, $message];
        // }
    }

    /**
     * @dataProvider errorConstantsAndMessages
     * @param int $constant Upload error constant
     * @param string $message Associated error message
     */
    public function testGetStreamRaisesExceptionWithAppropriateMessageWhenUploadErrorDetected($constant, $message)
    {
        $uploadedFile = new UploadedFile(__FILE__, 100, $constant);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        $uploadedFile->getStream();
    }

    /**
     * @dataProvider errorConstantsAndMessages
     * @param int $constant Upload error constant
     * @param string $message Associated error message
     */
    public function testMoveToRaisesExceptionWithAppropriateMessageWhenUploadErrorDetected($constant, $message)
    {
        $uploadedFile = new UploadedFile(__FILE__, 100, $constant);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        $uploadedFile->moveTo('/tmp/foo');
    }
}

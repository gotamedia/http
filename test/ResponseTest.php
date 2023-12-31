<?php

declare(strict_types=1);

/**
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Atoms\Http;

use Atoms\Http\Response;
use Atoms\Http\Stream;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    /**
     * @var Response
    */
    protected $response;

    public function setUp()
    {
        $this->response = new Response(200, '', new Stream(fopen('php://temp', 'r+')), [], '');
    }

    public function testStatusCodeIs200ByDefault()
    {
        $this->assertSame(200, $this->response->getStatusCode());
    }

    public function testStatusCodeMutatorReturnsCloneWithChanges()
    {
        $response = $this->response->withStatus(400);
        $this->assertNotSame($this->response, $response);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testReasonPhraseDefaultsToStandards()
    {
        $response = $this->response->withStatus(422);
        $this->assertSame('Unprocessable Entity', $response->getReasonPhrase());
    }

    public function ianaCodesReasonPhrasesProvider()
    {
        $ianaHttpStatusCodes = new DOMDocument();

        libxml_set_streams_context(
            stream_context_create(
                [
                    'http' => [
                        'method'  => 'GET',
                        'timeout' => 30,
                    ],
                ]
            )
        );

        $ianaHttpStatusCodes->load('https://www.iana.org/assignments/http-status-codes/http-status-codes.xml');

        if (! $ianaHttpStatusCodes->relaxNGValidate(__DIR__ . '/assets/http-status-codes.rng')) {
            self::fail('Unable to retrieve IANA response status codes due to timeout or invalid XML');
        }

        $ianaCodesReasonPhrases = [];

        $xpath = new DOMXPath($ianaHttpStatusCodes);
        $xpath->registerNamespace('ns', 'http://www.iana.org/assignments');

        $records = $xpath->query('//ns:record');

        foreach ($records as $record) {
            $value = $xpath->query('.//ns:value', $record)->item(0)->nodeValue;
            $description = $xpath->query('.//ns:description', $record)->item(0)->nodeValue;

            if (in_array($description, ['Unassigned', '(Unused)'])) {
                continue;
            }

            if (preg_match('/^([0-9]+)\s*\-\s*([0-9]+)$/', $value, $matches)) {
                for ($value = $matches[1]; $value <= $matches[2]; $value++) {
                    $ianaCodesReasonPhrases[] = [$value, $description];
                }
            } else {
                $ianaCodesReasonPhrases[] = [$value, $description];
            }
        }

        return $ianaCodesReasonPhrases;
    }

    /**
     * @dataProvider ianaCodesReasonPhrasesProvider
     */
    public function testReasonPhraseDefaultsAgainstIana($code, $reasonPhrase)
    {
        $response = $this->response->withStatus($code);
        $this->assertSame($reasonPhrase, $response->getReasonPhrase());
    }

    public function testCanSetCustomReasonPhrase()
    {
        $response = $this->response->withStatus(422, 'Foo Bar!');
        $this->assertSame('Foo Bar!', $response->getReasonPhrase());
    }

    // public function testConstructorRaisesExceptionForInvalidStream()
    // {
    //     $this->expectException(InvalidArgumentException::class);
    //
    //     new Response([ 'TOTALLY INVALID' ]);
    // }

    public function testConstructorCanAcceptAllMessageParts()
    {
        $body = new Stream(fopen('php://memory', 'r+'));
        $status = 302;
        $headers = [
            'location' => [ 'http://example.com/' ],
        ];

        $response = new Response($status, '', $body, $headers, '');
        $this->assertSame($body, $response->getBody());
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($headers, $response->getHeaders());
    }

    /**
     * @dataProvider validStatusCodes
     */
    public function testCreateWithValidStatusCodes($code)
    {
        $response = $this->response->withStatus($code);

        $this->assertSame($code, $response->getStatusCode());
    }

    public function validStatusCodes()
    {
        return [
            'minimum' => [100],
            'middle' => [300],
            'maximum' => [599],
        ];
    }

    /**
     * @dataProvider invalidStatusCodes
     */
    public function testConstructorRaisesExceptionForInvalidStatus($code)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status code');

        new Response($code, '', new Stream(fopen('php://memory', 'r+')), [], '');
    }

    /**
     * @dataProvider invalidStatusCodes
     */
    public function testCannotSetInvalidStatusCode($code)
    {
        $this->expectException(InvalidArgumentException::class);

        $this->response->withStatus($code);
    }

    public function invalidStatusCodes()
    {
        return [
            // 'true' => [ true ],
            // 'false' => [ false ],
            // 'array' => [ [ 200 ] ],
            // 'object' => [ (object) [ 'statusCode' => 200 ] ],
            'too-low' => [99],
            // 'float' => [400.5],
            'too-high' => [600],
            // 'null' => [null],
            // 'string' => ['foo'],
        ];
    }

    public function invalidResponseBody()
    {
        return [
            // 'true'       => [ true ],
            // 'false'      => [ false ],
            // 'int'        => [ 1 ],
            // 'float'      => [ 1.1 ],
            // 'array'      => [ ['BODY'] ],
            // 'stdClass'   => [ (object) [ 'body' => 'BODY'] ],
        ];
    }

    /**
     * @dataProvider invalidResponseBody
     */
    public function testConstructorRaisesExceptionForInvalidBody($body)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('stream');

        new Response(200, '', $body, [], '');
    }


    public function invalidHeaderTypes()
    {
        return [
            'indexed-array' => [[['INVALID']], 'header name'],
            'null' => [['x-invalid-null' => null]],
            'true' => [['x-invalid-true' => true]],
            'false' => [['x-invalid-false' => false]],
            'object' => [['x-invalid-object' => (object) ['INVALID']]],
        ];
    }

    /**
     * @dataProvider invalidHeaderTypes
     * @group 99
     */
    public function testConstructorRaisesExceptionForInvalidHeaders($headers, $contains = 'Invalid header value; must be string or numeric')
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($contains);

        new Response(200, '', new Stream(fopen('php://memory', 'r+')), $headers, '');
    }

    // public function testInvalidStatusCodeInConstructor()
    // {
    //     $this->expectException(InvalidArgumentException::class);
    //
    //     new Response(new Stream('php://memory'), null);
    // }

    public function testReasonPhraseCanBeEmpty()
    {
        $response = $this->response->withStatus(555);
        $this->assertInternalType('string', $response->getReasonPhrase());
        $this->assertEmpty($response->getReasonPhrase());
    }

    public function headersWithInjectionVectors()
    {
        return [
            'name-with-cr'           => ["X-Foo\r-Bar", 'value'],
            'name-with-lf'           => ["X-Foo\n-Bar", 'value'],
            'name-with-crlf'         => ["X-Foo\r\n-Bar", 'value'],
            'name-with-2crlf'        => ["X-Foo\r\n\r\n-Bar", 'value'],
            'value-with-cr'          => ['X-Foo-Bar', "value\rinjection"],
            'value-with-lf'          => ['X-Foo-Bar', "value\ninjection"],
            'value-with-crlf'        => ['X-Foo-Bar', "value\r\ninjection"],
            'value-with-2crlf'       => ['X-Foo-Bar', "value\r\n\r\ninjection"],
            'array-value-with-cr'    => ['X-Foo-Bar', ["value\rinjection"]],
            'array-value-with-lf'    => ['X-Foo-Bar', ["value\ninjection"]],
            'array-value-with-crlf'  => ['X-Foo-Bar', ["value\r\ninjection"]],
            'array-value-with-2crlf' => ['X-Foo-Bar', ["value\r\n\r\ninjection"]],
        ];
    }

    /**
     * @group ZF2015-04
     * @dataProvider headersWithInjectionVectors
     */
    public function testConstructorRaisesExceptionForHeadersWithCRLFVectors($name, $value)
    {
        $this->expectException(InvalidArgumentException::class);

        new Response(new Stream('php://memory'), 200, [$name => $value], '');
    }
}

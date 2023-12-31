<?php

declare(strict_types=1);

/**
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Atoms\Http;

use Atoms\Http\Request;
use Atoms\Http\Stream;
use Atoms\Http\Uri;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

class RequestTest extends TestCase
{
    /**
     * @var Request
     */
    protected $request;

    public function setUp()
    {
        $this->request = new Request('', new Uri(), new Stream(fopen('php://temp', 'r+')), [], '');
    }

    public function testMethodIsEmptyByDefault()
    {
        $this->assertSame('', $this->request->getMethod());
    }

    public function testMethodMutatorReturnsCloneWithChangedMethod()
    {
        $request = $this->request->withMethod('GET');
        $this->assertNotSame($this->request, $request);
        $this->assertSame('GET', $request->getMethod());
    }

    public function testReturnsUnpopulatedUriByDefault()
    {
        $uri = $this->request->getUri();
        $this->assertInstanceOf(UriInterface::class, $uri);
        $this->assertInstanceOf(Uri::class, $uri);
        $this->assertEmpty($uri->getScheme());
        $this->assertEmpty($uri->getUserInfo());
        $this->assertEmpty($uri->getHost());
        $this->assertNull($uri->getPort());
        $this->assertEmpty($uri->getPath());
        $this->assertEmpty($uri->getQuery());
        $this->assertEmpty($uri->getFragment());
    }

    // public function testConstructorRaisesExceptionForInvalidStream()
    // {
    //     $this->expectException(InvalidArgumentException::class);
    //
    //     new Request(['TOTALLY INVALID']);
    // }

    public function testWithUriReturnsNewInstanceWithNewUri()
    {
        $request = $this->request->withUri(new Uri('https://example.com:10082/foo/bar?baz=bat'));
        $this->assertNotSame($this->request, $request);
        $request2 = $request->withUri(new Uri('/baz/bat?foo=bar'));
        $this->assertNotSame($this->request, $request2);
        $this->assertNotSame($request, $request2);
        $this->assertSame('/baz/bat?foo=bar', (string) $request2->getUri());
    }

    public function testConstructorCanAcceptAllMessageParts()
    {
        $uri     = new Uri('http://example.com/');
        $body    = new Stream(fopen('php://memory', 'r+'));
        $headers = [
            'x-foo' => ['bar'],
        ];
        $request = new Request(
            'POST',
            $uri,
            $body,
            $headers,
            ''
        );

        $this->assertSame($uri, $request->getUri());
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame($body, $request->getBody());
        $testHeaders = $request->getHeaders();
        foreach ($headers as $key => $value) {
            $this->assertArrayHasKey($key, $testHeaders);
            $this->assertSame($value, $testHeaders[$key]);
        }
    }

    public function testDefaultStreamIsWritable()
    {
        $request = new Request(
            'GET',
            new Uri(),
            new Stream(fopen('php://temp', 'r+')),
            [],
            ''
        );
        $request->getBody()->write("test");

        $this->assertSame("test", (string)$request->getBody());
    }

    // public function invalidRequestUri()
    // {
    //     return [
    //         'true'     => [ true ],
    //         'false'    => [ false ],
    //         'int'      => [ 1 ],
    //         'float'    => [ 1.1 ],
    //         'array'    => [ ['http://example.com'] ],
    //         'stdClass' => [ (object) [ 'href'         => 'http://example.com'] ],
    //     ];
    // }
    //
    // /**
    //  * @dataProvider invalidRequestUri
    //  */
    // public function testConstructorRaisesExceptionForInvalidUri($uri)
    // {
    //     $this->expectException(InvalidArgumentException::class);
    //     $this->expectExceptionMessage('Invalid URI');
    //
    //     new Request($uri);
    // }

    public function invalidRequestMethod()
    {
        return [
            // 'true'       => [ true ],
            // 'false'      => [ false ],
            // 'int'        => [ 1 ],
            // 'float'      => [ 1.1 ],
            'bad-string' => [ 'BOGUS METHOD' ],
            // 'array'      => [ ['POST'] ],
            // 'stdClass'   => [ (object) [ 'method' => 'POST'] ],
        ];
    }

    /**
     * @dataProvider invalidRequestMethod
     */
    public function testConstructorRaisesExceptionForInvalidMethod($method)
    {
        $this->expectException(InvalidArgumentException::class);
        //$this->expectExceptionMessage('Unsupported HTTP method');

        new Request($method, new Uri(), new Stream(fopen('php://temp', 'r+')), [], '');
    }

    public function customRequestMethods()
    {
        return[
            /* WebDAV methods */
            'TRACE'     => ['TRACE'],
            'PROPFIND'  => ['PROPFIND'],
            'PROPPATCH' => ['PROPPATCH'],
            'MKCOL'     => ['MKCOL'],
            'COPY'      => ['COPY'],
            'MOVE'      => ['MOVE'],
            'LOCK'      => ['LOCK'],
            'UNLOCK'    => ['UNLOCK'],
            'UNLOCK'    => ['UNLOCK'],
            /* Arbitrary methods */
            '#!ALPHA-1234&%' => ['#!ALPHA-1234&%'],
        ];
    }

    /**
     * @dataProvider customRequestMethods
     * @group 29
     */
    public function testAllowsCustomRequestMethodsThatFollowSpec($method)
    {
        $request = new Request($method, new Uri(), new Stream(fopen('php://temp', 'r+')), [], '');
        $this->assertSame($method, $request->getMethod());
    }

    public function invalidRequestBody()
    {
        return [
            'true'       => [ true ],
            'false'      => [ false ],
            'int'        => [ 1 ],
            'float'      => [ 1.1 ],
            'array'      => [ ['BODY'] ],
            'stdClass'   => [ (object) [ 'body' => 'BODY'] ],
        ];
    }

    /**
     * @dataProvider invalidRequestBody
     */
    // public function testConstructorRaisesExceptionForInvalidBody($body)
    // {
    //     $this->expectException(InvalidArgumentException::class);
    //     $this->expectExceptionMessage('stream');
    //
    //     new Request('GET', new Uri(), new Stream(fopen('php://temp', 'r+')), $body);
    // }

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
    public function testConstructorRaisesExceptionForInvalidHeaders($headers, $contains = 'header value type')
    {
        $this->expectException(InvalidArgumentException::class);
        //$this->expectExceptionMessage($contains);

        new Request('GET', new Uri(), new Stream(fopen('php://memory', 'r+')), $headers, '');
    }

    public function testRequestTargetIsSlashWhenNoUriPresent()
    {
        $request = new Request('GET', new Uri(), new Stream(fopen('php://temp', 'r+')), [], '');
        $this->assertSame('/', $request->getRequestTarget());
    }

    public function testRequestTargetIsSlashWhenUriHasNoPathOrQuery()
    {
        $request = (new Request('GET', new Uri(), new Stream(fopen('php://temp', 'r+')), [], ''))
            ->withUri(new Uri('http://example.com'));
        $this->assertSame('/', $request->getRequestTarget());
    }

    public function requestsWithUri()
    {
        return [
            'absolute-uri' => [
                (new Request('GET', new Uri(), new Stream(fopen('php://temp', 'r+')), [], ''))
                ->withUri(new Uri('https://api.example.com/user'))
                ->withMethod('POST'),
                '/user'
            ],
            'absolute-uri-with-query' => [
                (new Request('GET', new Uri(), new Stream(fopen('php://temp', 'r+')), [], ''))
                ->withUri(new Uri('https://api.example.com/user?foo=bar'))
                ->withMethod('POST'),
                '/user?foo=bar'
            ],
            'relative-uri' => [
                (new Request('GET', new Uri(), new Stream(fopen('php://temp', 'r+')), [], ''))
                ->withUri(new Uri('/user'))
                ->withMethod('GET'),
                '/user'
            ],
            'relative-uri-with-query' => [
                (new Request('GET', new Uri(), new Stream(fopen('php://temp', 'r+')), [], ''))
                ->withUri(new Uri('/user?foo=bar'))
                ->withMethod('GET'),
                '/user?foo=bar'
            ],
        ];
    }

    /**
     * @dataProvider requestsWithUri
     */
    public function testReturnsRequestTargetWhenUriIsPresent($request, $expected)
    {
        $this->assertSame($expected, $request->getRequestTarget());
    }

    public function validRequestTargets()
    {
        return [
            'asterisk-form'         => [ '*' ],
            'authority-form'        => [ 'api.example.com' ],
            'absolute-form'         => [ 'https://api.example.com/users' ],
            'absolute-form-query'   => [ 'https://api.example.com/users?foo=bar' ],
            'origin-form-path-only' => [ '/users' ],
            'origin-form'           => [ '/users?id=foo' ],
        ];
    }

    /**
     * @dataProvider validRequestTargets
     */
    public function testCanProvideARequestTarget($requestTarget)
    {
        $request = (new Request('GET', new Uri(), new Stream(fopen('php://temp', 'r+')), [], ''))
            ->withRequestTarget($requestTarget);
        $this->assertSame($requestTarget, $request->getRequestTarget());
    }

    public function testRequestTargetCannotContainWhitespace()
    {
        $request = new Request(
            'GET',
            new Uri(),
            new Stream(fopen('php://temp', 'r+')),
            [],
            ''
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid request target');

        $request->withRequestTarget('foo bar baz');
    }

    public function testRequestTargetDoesNotCacheBetweenInstances()
    {
        $request = (new Request('GET', new Uri(), new Stream(fopen('php://temp', 'r+')), [], ''))
            ->withUri(new Uri('https://example.com/foo/bar'));
        $original = $request->getRequestTarget();
        $newRequest = $request->withUri(new Uri('http://mwop.net/bar/baz'));
        $this->assertNotSame($original, $newRequest->getRequestTarget());
    }

    public function testSettingNewUriResetsRequestTarget()
    {
        $request = (new Request('GET', new Uri(), new Stream(fopen('php://temp', 'r+')), [], ''))
            ->withUri(new Uri('https://example.com/foo/bar'));
        $newRequest = $request->withUri(new Uri('http://mwop.net/bar/baz'));

        $this->assertNotSame($request->getRequestTarget(), $newRequest->getRequestTarget());
    }

    /**
     * @group 39
     */
    public function testGetHeadersContainsHostHeaderIfUriWithHostIsPresent()
    {
        $request = new Request(
            'GET',
            new Uri('http://example.com'),
            new Stream(fopen('php://temp', 'r+')),
            [],
            ''
        );
        $headers = $request->getHeaders();
        $this->assertArrayHasKey('Host', $headers);
        $this->assertContains('example.com', $headers['Host']);
    }

    /**
     * @group 39
     */
    // public function testGetHeadersContainsHostHeaderIfUriWithHostIsDeleted()
    // {
    //     $request = (new Request(new Stream(fopen('php://temp', 'r+')), new Uri('http://example.com')))->withoutHeader('host');
    //     $headers = $request->getHeaders();
    //     $this->assertArrayHasKey('Host', $headers);
    //     $this->assertContains('example.com', $headers['Host']);
    // }

    /**
     * @group 39
     */
    public function testGetHeadersContainsNoHostHeaderIfNoUriPresent()
    {
        $request = new Request(
            'GET',
            new Uri(),
            new Stream(fopen('php://temp', 'r+')),
            [],
            ''
        );
        $headers = $request->getHeaders();
        $this->assertArrayNotHasKey('Host', $headers);
    }

    /**
     * @group 39
     */
    public function testGetHeadersContainsNoHostHeaderIfUriDoesNotContainHost()
    {
        $request = new Request(
            'GET',
            new Uri(),
            new Stream(fopen('php://temp', 'r+')),
            [],
            ''
        );
        $headers = $request->getHeaders();
        $this->assertArrayNotHasKey('Host', $headers);
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderReturnsUriHostWhenPresent()
    {
        $request = new Request(
            'GET',
            new Uri('http://example.com'),
            new Stream(fopen('php://temp', 'r+')),
            [],
            ''
        );
        $header = $request->getHeader('host');
        $this->assertSame(['example.com'], $header);
    }

    /**
     * @group 39
     */
    // public function testGetHostHeaderReturnsUriHostWhenHostHeaderDeleted()
    // {
    //     $request = (new Request(new Stream(fopen('php://temp', 'r+')), new Uri('http://example.com')));//->withoutHeader('host');
    //     $header = $request->getHeader('host');
    //
    //     $this->assertSame(['example.com'], $header);
    // }

    /**
     * @group 39
     */
    public function testGetHostHeaderReturnsEmptyArrayIfNoUriPresent()
    {
        $request = new Request(
            'GET',
            new Uri(),
            new Stream(fopen('php://temp', 'r+')),
            [],
            ''
        );
        $this->assertSame([], $request->getHeader('host'));
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderReturnsEmptyArrayIfUriDoesNotContainHost()
    {
        $request = new Request(
            'GET',
            new Uri(),
            new Stream(fopen('php://temp', 'r+')),
            [],
            ''
        );
        $this->assertSame([], $request->getHeader('host'));
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderLineReturnsUriHostWhenPresent()
    {
        $request = new Request(
            'GET',
            new Uri('http://example.com'),
            new Stream(fopen('php://temp', 'r+')),
            [],
            ''
        );
        $header = $request->getHeaderLine('host');
        $this->assertContains('example.com', $header);
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderLineReturnsEmptyStringIfNoUriPresent()
    {
        $request = new Request(
            'GET',
            new Uri(),
            new Stream(fopen('php://temp', 'r+')),
            [],
            ''
        );
        $this->assertEmpty($request->getHeaderLine('host'));
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderLineReturnsEmptyStringIfUriDoesNotContainHost()
    {
        $request = new Request(
            'GET',
            new Uri(),
            new Stream(fopen('php://temp', 'r+')),
            [],
            ''
        );
        $this->assertEmpty($request->getHeaderLine('host'));
    }

    public function testHostHeaderSetFromUriOnCreationIfNoHostHeaderSpecified()
    {
        $request = new Request(
            'GET',
            new Uri('http://www.example.com'),
            new Stream(fopen('php://temp', 'r+')),
            [],
            ''
        );
        $this->assertTrue($request->hasHeader('Host'));
        $this->assertSame('www.example.com', $request->getHeaderLine('host'));
    }

    public function testHostHeaderNotSetFromUriOnCreationIfHostHeaderSpecified()
    {
        $request = new Request(
            'GET',
            new Uri('http://www.example.com'),
            new Stream(fopen('php://memory', 'r+')),
            ['Host' => 'www.test.com'],
            ''
        );
        $this->assertSame('www.test.com', $request->getHeaderLine('host'));
    }

    public function testPassingPreserveHostFlagWhenUpdatingUriDoesNotUpdateHostHeader()
    {
        $request = (new Request('GET', new Uri(), new Stream(fopen('php://temp', 'r+')), [], ''))
            ->withAddedHeader('Host', 'example.com');

        $uri = (new Uri())->withHost('www.example.com');
        $new = $request->withUri($uri, true);

        $this->assertSame('example.com', $new->getHeaderLine('Host'));
    }

    public function testNotPassingPreserveHostFlagWhenUpdatingUriWithoutHostDoesNotUpdateHostHeader()
    {
        $request = (new Request('GET', new Uri(), new Stream(fopen('php://temp', 'r+')), [], ''))
            ->withAddedHeader('Host', 'example.com');

        $uri = new Uri();
        $new = $request->withUri($uri);

        $this->assertSame('example.com', $new->getHeaderLine('Host'));
    }

    public function testHostHeaderUpdatesToUriHostAndPortWhenPreserveHostDisabledAndNonStandardPort()
    {
        $request = (new Request('GET', new Uri(), new Stream(fopen('php://temp', 'r+')), [], ''))
            ->withAddedHeader('Host', 'example.com');

        $uri = (new Uri())
            ->withHost('www.example.com')
            ->withPort(10081);
        $new = $request->withUri($uri);

        $this->assertSame('www.example.com:10081', $new->getHeaderLine('Host'));
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

        new Request('GET', new Uri(), new Stream(fopen('php://temp', 'r+')), [$name => $value], '');
    }

    public function hostHeaderKeys()
    {
        return [
            'lowercase'            => ['host'],
            'mixed-4'              => ['hosT'],
            'mixed-3-4'            => ['hoST'],
            'reverse-titlecase'    => ['hOST'],
            'uppercase'            => ['HOST'],
            'mixed-1-2-3'          => ['HOSt'],
            'mixed-1-2'            => ['HOst'],
            'titlecase'            => ['Host'],
            'mixed-1-4'            => ['HosT'],
            'mixed-1-2-4'          => ['HOsT'],
            'mixed-1-3-4'          => ['HoST'],
            'mixed-1-3'            => ['HoSt'],
            'mixed-2-3'            => ['hOSt'],
            'mixed-2-4'            => ['hOsT'],
            'mixed-2'              => ['hOst'],
            'mixed-3'              => ['hoSt'],
        ];
    }

    /**
     * @group 91
     * @dataProvider hostHeaderKeys
     */
    public function testWithUriAndNoPreserveHostWillOverwriteHostHeaderRegardlessOfOriginalCase($hostKey)
    {
        $request = (new Request('GET', new Uri(), new Stream(fopen('php://temp', 'r+')), [], ''))
            ->withHeader($hostKey, 'example.com');

        $uri  = new Uri('http://example.org/foo/bar');
        $new  = $request->withUri($uri);
        $host = $new->getHeaderLine('host');
        $this->assertSame('example.org', $host);
        $headers = $new->getHeaders();
        $this->assertArrayHasKey('Host', $headers);
        if ($hostKey !== 'Host') {
            $this->assertArrayNotHasKey($hostKey, $headers);
        }
    }
}

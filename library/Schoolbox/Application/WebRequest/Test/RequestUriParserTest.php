<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\WebRequest\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Schoolbox\Application\WebRequest\RequestUriParser;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Application\WebRequest\RequestUriParser::class)]
class RequestUriParserTest extends TestCase
{
    /**
     * @param string $requestUri
     * @param string $expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestParseUrlPathData')]
    public function testParseUrlPath(string $requestUri, string $expected): void
    {
        $this->assertSame(
            $expected,
            RequestUriParser::parseUrlPath($this->buildServerVars($requestUri))
        );
    }

    /**
     * Data provider for `testParseUrlPath`
     *
     * @return iterable
     */
    public static function getTestParseUrlPathData(): iterable
    {
        yield 'empty' => [
            '',
            '',
        ];
        yield 'empty w/ trailing slash' => [
            '/',
            '',
        ];
        yield 'path' => [
            '/test',
            '/test',
        ];
        yield 'path w/ trailing slash' => [
            '/test/',
            '/test',
        ];
        yield 'path + query string' => [
            '/test?a=b',
            '/test',
        ];
        yield 'path + query string w/ trailing slash' => [
            '/test/?a=b',
            '/test',
        ];
        yield 'path w/ colon' => [
            '/test:2',
            '/test:2',
        ];
        yield 'path w/ colon + trailing slash' => [
            '/test:2/',
            '/test:2',
        ];
        yield 'multi-level path' => [
            '/test/level/1',
            '/test/level/1',
        ];
        yield 'multi-level path w/ trailing slash' => [
            '/test/level/1/',
            '/test/level/1',
        ];
        yield 'no path at all' => [
            'path',
            'path',
        ];
    }

    /**
     * @return void
     */
    public function testParseUrlPathWithNoRequestUri(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RequestUriParser::parseUrlPath([]);
    }

    /**
     * @param string $requestUri
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestParseUrlPathWithMultipleLeadingSlashesData')]
    public function testParseUrlPathWithMultipleLeadingSlashes(string $requestUri): void
    {
        $this->expectException(BadRequestException::class);
        RequestUriParser::parseUrlPath($this->buildServerVars($requestUri));
    }

    /**
     * Data provider for `testParseUrlPathWithMultipleLeadingSlashes`
     *
     * @return iterable
     */
    public static function getTestParseUrlPathWithMultipleLeadingSlashesData(): iterable
    {
        yield 'empty w/ two leading slashes' => [
            '//',
        ];
        yield 'empty w/ three leading slashes' => [
            '///',
        ];
        yield 'path w/ two leading slashes' => [
            '//path',
        ];
        yield 'path w/ three leading slashes' => [
            '///path',
        ];
    }

    /**
     * Helper for building an array of server vars to be passed into
     * `parseUrlPath`.
     *
     * @param string $requestUri
     * @return string[]
     */
    private function buildServerVars(string $requestUri): array
    {
        return [
            'REQUEST_URI' => $requestUri,
        ];
    }
}

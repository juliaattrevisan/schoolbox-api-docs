<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Alaress\Module\Test;

use Alaress\Module\RouteParser;
use Schoolbox\Test\TestCase;

/**
 * @covers \Alaress\Module\RouteParser
 */
class RouterParserTest extends TestCase
{
    /** @var RouteParser */
    private static $routeParser;


    /** @inheritDoc */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::$routeParser = new RouteParser();
    }


    /**
     * @param string $route
     * @param string $expectedPath
     * @param array  $expectedTokens
     * @dataProvider providerRoutes
     */
    public function testParse(string $route, string $expectedPath, array $expectedTokens): void
    {
        [$actualPath, $actualTokens] = self::$routeParser->parse($route);
        $this->assertSame([$expectedPath, $expectedTokens], [$actualPath, $actualTokens], "Route: $route\n");
    }


    /**
     * @return array
     */
    public function providerRoutes(): array
    {
        return [
            ['/',                                     '/',                               []],
            ['/path',                                 '/path',                           []],
            ['/path{/option1}',                       '/path{/option1}',                 []],
            ['/path{/option1:X}',                     '/path{/option1}',                 ['option1' => 'X']],
            ['/path/{option1:X}',                     '/path/{option1}',                 ['option1' => 'X']],
            ['/path{/option1,option2}',               '/path{/option1,option2}',         []],
            ['/path{/option1,option2:X}',             '/path{/option1,option2}',         ['option2' => 'X']],
            ['/path{/option1:X,option2}',             '/path{/option1,option2}',         ['option1' => 'X']],
            ['/path{/option1:X,option2:X}',           '/path{/option1,option2}',         ['option1' => 'X', 'option2' => 'X']],
            ['/path/path{/option1,option2}',          '/path/path{/option1,option2}',    []],
            ['/path/path{/option1,option2:X}',        '/path/path{/option1,option2}',    ['option2' => 'X']],
            ['/path/path{/option1:X,option2}',        '/path/path{/option1,option2}',    ['option1' => 'X']],
            ['/path/path{/option1:X,option2:X}',      '/path/path{/option1,option2}',    ['option1' => 'X', 'option2' => 'X']],
            ['/path/{token}{/option1,option2}',       '/path/{token}{/option1,option2}', []],
            ['/path/{token}{/option1,option2:X}',     '/path/{token}{/option1,option2}', ['option2' => 'X']],
            ['/path/{token}{/option1:X,option2}',     '/path/{token}{/option1,option2}', ['option1' => 'X']],
            ['/path/{token}{/option1:X,option2:X}',   '/path/{token}{/option1,option2}', ['option1' => 'X', 'option2' => 'X']],
            ['/path/{token:X}{/option1,option2}',     '/path/{token}{/option1,option2}', ['token' => 'X']],
            ['/path/{token:X}{/option1,option2:X}',   '/path/{token}{/option1,option2}', ['token' => 'X', 'option2' => 'X']],
            ['/path/{token:X}{/option1:X,option2}',   '/path/{token}{/option1,option2}', ['token' => 'X', 'option1' => 'X']],
            ['/path/{token:X}{/option1:X,option2:X}', '/path/{token}{/option1,option2}', ['token' => 'X', 'option1' => 'X', 'option2' => 'X']],
            ['/path{/a:X,b:X,c:X}',                   '/path{/a,b,c}',                   ['a' => 'X', 'b' => 'X', 'c' => 'X']],
            ['/path/{token}{/a:X,b:X,c:X}',           '/path/{token}{/a,b,c}',           ['a' => 'X', 'b' => 'X', 'c' => 'X']],
            ['/path/{token}/path',                    '/path/{token}/path',              []],
            ['/path/{token}/path{/a:X,b,c:X}',        '/path/{token}/path{/a,b,c}',      ['a' => 'X', 'c' => 'X']],
            ['/path/{token:X}/path{/a:X,b,c:X}',      '/path/{token}/path{/a,b,c}',      ['token' => 'X', 'a' => 'X', 'c' => 'X']],

            // TODO cases, we should probably support, but currently don't
//            ['/path/{token:\d{4}}',                   '/path/{token}',                   ['token' => '\d{4}']],
        ];
    }
}

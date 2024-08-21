<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Routing\Test;

use Alaress\Routing\Route\Route;
use Alaress\Routing\Trie;
use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    /** @var Trie */
    private static $root;

    /**
     * This method is called before the first test of this test class is run.
     *
     * @since Method available since Release 3.4.0
     */
    public static function setUpBeforeClass(): void
    {
        // this can be static, since the tests will not mutate it
        self::$root = new Trie('', function () {
            return 0;
        });

        // manual construction of the tree foo/(:int)/{bar,baz}
        $foo = self::$root->put('foo');
        $int = $foo->put('(\d+)'); // note the capture in the pattern
        $bar = $int->put('bar');
        $baz = $int->put('baz');

        // set data
        $bar->set([ 2 ], 'barK', 'barV');
        $baz->set([ 4 ], 'bazK', 'bazV');
    }


    /**
     * Helper method to create a new Route object
     *
     * @param string $path
     * @param string $delim
     * @return Route
     */
    private function route($path, $delim)
    {
        return new Route($path, self::$root, $delim);
    }


    /**
     * @return array
     */
    public static function dataValidConstruction()
    {
        return [
            [ 'foo:42:bar',   ':' ],
            [ 'foo:0:bar',    ':' ],
            [ 'foo:42:baz',   ':' ],
            [ 'foo/42/bar',   '/' ],
            [ 'foo 9001 bar', ' ' ],
        ];
    }


    /**
     * Test that, for a given Trie hierarchy, valid routes can be constructed
     *
     *
     * @param string $path
     * @param string $delim
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataValidConstruction')]
    public function testValidConstruction($path, $delim)
    {
        $route = $this->route($path, $delim);
        $this->assertEquals(true, $route instanceof Route);
    }


    /**
     * @return array
     */
    public static function dataInvalidConstruction()
    {
        return [
            [ 'f00:42:bar',       ':' ], // mismatch position 1
            [ 'foo:fortytwo:bar', ':' ], // mismatch position 2
            [ 'foo:42:bingo',     ':' ], // mismatch position 3
            [ 'foo/42/bar',       ':' ], // wrong delimiter
            [ 'foo/42/bar/baz',   '/' ], // path not found
        ];
    }


    /**
     * Test that, for a given Trie hierarchy, invalid routes cannot be constructed
     *
     *
     *
     * @param string $path
     * @param string $delim
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataInvalidConstruction')]
    public function testInvalidPathConstruction($path, $delim)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->route($path, $delim);
    }


    /**
     * @return array
     */
    public static function dataValidDataFetch()
    {
        return [
            [ 'foo:2:bar', [ 'barK' => 'barV' ] ],
            [ 'foo:4:baz', [ 'bazK' => 'bazV' ] ],
        ];
    }


    /**
     * Test that, for a given Trie hierarchy, data payloads can be retrieved
     *
     *
     * @param string $path
     * @param array  $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataValidDataFetch')]
    public function testValidDataFetch($path, $expected)
    {
        $route = $this->route($path, ':');
        $this->assertEquals($expected, $route->data());
    }


    /**
     * @return array
     */
    public static function dataInvalidDataFetch()
    {
        // note the key inversion from the payloads set in self::$root
        return [
            [ 'foo:4:bar' ],
            [ 'foo:2:baz' ],
        ];
    }

    /**
     * Test that, for a given Trie hierarchy, attempting to retrieve a payload from a path
     * which IS a valid pattern but does NOT contain a payload throws an exception
     *
     *
     *
     * @param string $path
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataInvalidDataFetch')]
    public function testInvalidDataFetch($path)
    {
        $this->expectException(\InvalidArgumentException::class);
        $route = $this->route($path, ':');
        $route->data();
    }
}

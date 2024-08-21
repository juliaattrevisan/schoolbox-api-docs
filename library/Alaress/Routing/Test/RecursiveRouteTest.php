<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Routing\Test;

use Alaress\Routing\Route\RecursiveRoute;
use Alaress\Routing\Trie;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RecursiveRouteTest extends TestCase
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
        $all = $foo->put('.*');
        $bar = $int->put('bar');
        $baz = $int->put('baz');
        $bin = $baz->put('bin');

        // set data
        $bar->set([ 2 ], 'barK', 'barV');
        $baz->set([ 4 ], 'bazK', 'bazV');
        $bin->set([ 4 ], 'binK', 'binV');
        $all->set([   ], 'allK', 'allV');
    }


    /**
     * Helper method to create a new Route object
     *
     * @param string $path
     * @param string $delim
     * @return RecursiveRoute
     */
    private function route($path, $delim)
    {
        return new RecursiveRoute($path, self::$root, $delim);
    }


    /**
     * @return array[]
     */
    public static function dataMultipleRoutesMatch()
    {
        return [
            [ 'foo:2:bar',      [ 'bar', 'all' ] ],
            [ 'foo:not:an:int', [        'all' ] ],
            [ 'foo:4:baz:bin',  [ 'bin', 'all' ] ],
        ];
    }


    /**
     *
     * @param string $path
     * @param array  $expected
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataMultipleRoutesMatch')]
    public function testMultipleRoutesMatch($path, array $expected)
    {
        $exp = [];

        // flesh out the "compressed" expectation array
        foreach ($expected as $e) {
            $exp[$e . 'K'] = $e . 'V';
        }

        $this->assertEquals($exp, $this->route($path, ':')->data());
    }
}

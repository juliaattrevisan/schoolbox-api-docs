<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Routing\Test;


use Alaress\Routing\Dictionary\SubscriptionDictionary;
use Alaress\Routing\Router;
use InvalidArgumentException;
use PHPUnit_Framework_Exception;
use PHPUnit_Framework_TestCase;

class RouterTest extends PHPUnit_Framework_TestCase
{
    /** @var Router */
    private static $testGet;


    /**
     * @param string $delimiter
     * @param bool   $recursive
     * @return Router
     */
    public static function newRouter($delimiter, $recursive)
    {
        // we are running a
        return new Router(new SubscriptionDictionary($delimiter), $recursive);
    }


    /**
     * This method is called before the first test of this test class is run.
     *
     * @since Method available since Release 3.4.0
     */
    public static function setUpBeforeClass()
    {
        // preload a router for use in pattern match tests
        self::$testGet = $r = self::newRouter(':', false);

        // the order of these patterns is important: by inserting the more generic ones
        // before their specific counterparts it ensures that the sorting works as expected
        $patterns = [
            '*:42:*'             => 'wordFortyTwoWord',
            '*:42:42:foo:42:bar' => 'wordFortyTwoFortyTwoFooFortyTwoBar',
            '*:42:baz'           => 'wordFortyTwoBaz',
            'foo:#'              => 'fooHash',
            'foo:42:#'           => 'fooFortyTwoHash',
            'foo:42:bar'         => 'fooFortyTwoBar',
            '*:foo:42:#'         => 'wordFooFortyTwoHash',
            '*:foo:42:*:*'       => 'wordFooFortyTwoWordWord'
        ];

        foreach ($patterns as $ptn => $key) {
            $r->add($ptn, $key, true);
        }
    }


    /**
     * Test that fully-literal paths can be matched and their payload retrieved
     */
    public function testLiteralPathGet()
    {
        $router = static::newRouter(':', false);

        $router->add('foo:42:bar', 'foobar42key', 'foobar42value');
        $router->add('foo:42:baz', 'foobaz42key', 'foobaz42value');

        $this->assertEquals([ 'foobar42key' => 'foobar42value' ], $router->get('foo:42:bar'));
        $this->assertEquals([ 'foobaz42key' => 'foobaz42value' ], $router->get('foo:42:baz'));
    }


    /**
     * @return array
     */
    public function dataPatternPathGet()
    {
        return [
            [ 'bing:foo:42:bar:baz', 'wordFooFortyTwoWordWord' ],
            [ 'foo:42:randomness',   'fooFortyTwoHash' ],
            [ 'foo:randomness',      'fooHash' ],
            [ 'foo:42:bar',          'fooFortyTwoBar' ],
        ];
    }


    /**
     * @dataProvider dataPatternPathGet
     *
     * @param string $path
     * @param string $expectedKey
     * @throws InvalidArgumentException
     * @throws PHPUnit_Framework_Exception
     */
    public function testPatternPathGet($path, $expectedKey)
    {
        $result = array_keys(self::$testGet->get($path));

        $this->assertSameSize([ true ], $result);
        $this->assertEquals($expectedKey, $result[0]);
    }

}

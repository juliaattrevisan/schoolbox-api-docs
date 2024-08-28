<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Routing\Test;

use Alaress\Routing\Dictionary\SubscriptionDictionary;
use Alaress\Routing\Router;
use InvalidArgumentException;
use OutOfBoundsException;
use PHPUnit_Framework_AssertionFailedError;
use PHPUnit_Framework_Exception;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    /** @var Router */
    private static $testGet;

    /**
     * The order of these patterns is important: by inserting the more generic ones before their
     * specific counterparts it ensures that the sorting works as expected.
     * @var string[]
     */
    private static $patterns = [
        '*:42:*'             => 'wordFortyTwoWord',
        '*:42:42:foo:42:bar' => 'wordFortyTwoFortyTwoFooFortyTwoBar',
        '*:42:baz'           => 'wordFortyTwoBaz',
        'foo:#'              => 'fooHash',
        'foo:42:#'           => 'fooFortyTwoHash',
        'foo:42:bar'         => 'fooFortyTwoBar',
        '*:foo:42:#'         => 'wordFooFortyTwoHash',
        '*:foo:42:*:*'       => 'wordFooFortyTwoWordWord'
    ];

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
    public static function setUpBeforeClass(): void
    {
        // preload a router for use in pattern match tests
        self::$testGet = $r = self::newRouter(':', false);

        foreach (self::$patterns as $ptn => $key) {
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
    public static function dataPatternPathGet()
    {
        return [
            [ 'bing:foo:42:bar:baz', 'wordFooFortyTwoWordWord' ],
            [ 'foo:42:randomness',   'fooFortyTwoHash' ],
            [ 'foo:randomness',      'fooHash' ],
            [ 'foo:42:bar',          'fooFortyTwoBar' ],
        ];
    }


    /**
     *
     * @param string $path
     * @param string $expectedKey
     * @throws InvalidArgumentException
     * @throws PHPUnit_Framework_Exception
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataPatternPathGet')]
    public function testPatternPathGet($path, $expectedKey)
    {
        $result = array_keys(self::$testGet->get($path));

        $this->assertSameSize([ true ], $result);
        $this->assertEquals($expectedKey, $result[0]);
    }


    /**
     * @return array[]
     */
    public static function dataDropAll()
    {
        return [
            'recursive'     => [ true ],
            'non-recursive' => [ false ]
        ];
    }


    /**
     * @param bool $recursive
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     * @throws PHPUnit_Framework_AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataDropAll')]
    public function testDropAll($recursive)
    {
        // don't use the global router, we need to mutate this one
        $router = static::newRouter(':', $recursive);

        // load up the router
        foreach (self::$patterns as $ptn => $key) {
            $router->add($ptn, $key, true);
        }

        // aaaaand now dump it all out!
        $router->dropAll();

        try {
            $router->get('foo');
            $this->fail('Did not throw ' . InvalidArgumentException::class);
        } catch (InvalidArgumentException $e) {
            // good, we got to here
            $router->add('foo:#', 'fooHash', true);

            // if this throws an IAE, that's our fail-case
            $this->assertTrue($router->get('foo:something')['fooHash']);
        }
    }


    /**
     *
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    public function testLocking()
    {
        $this->expectException(InvalidArgumentException::class);
        // don't use the global router, we need to mutate this one
        $router = static::newRouter(':', true);

        // load up the router
        foreach (self::$patterns as $ptn => $key) {
            $router->add($ptn, $key, true);
        }

        $router->lock();

        $router->add('something:silly', 'oops', true);

        // will throw IAE, because the add operation did not work
        $router->get('something:silly');
    }
}

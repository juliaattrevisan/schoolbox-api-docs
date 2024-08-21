<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Subscription\Test;

use Alaress\Routing\Dictionary\IDictionary;
use Alaress\Routing\Dictionary\SubscriptionDictionary;
use Alaress\Routing\IRouter;
use Alaress\Routing\Router;
use InvalidArgumentException;
use OutOfBoundsException;
use OverflowException;
use PHPUnit\Framework\TestCase;
use Schoolbox\Realtime\Routing\Subscription\Resolver;

class ResolverTest extends TestCase
{
    const CLASS_GUEST = 46;
    const CLASS_HOST  = 85;

    /** @var IRouter */
    private static $routerFromFile;


    /**
     * This method is called before the first test of this test class is run.
     *
     * @since Method available since Release 3.4.0
     */
    public static function setUpBeforeClass(): void
    {
        self::$routerFromFile = self::newRealWorldRouter();
        self::$routerFromFile->lock();
    }


    /**
     * @param IRouter $router
     * @return Resolver
     */
    private function newResolver(IRouter $router = null)
    {
        return new Resolver($router ?: self::newRouter());
    }


    /**
     * @param IDictionary $dict
     * @return Router
     */
    private static function newRouter(IDictionary $dict = null)
    {
        return new Router($dict ?: new SubscriptionDictionary('.'));
    }


    /**
     * @param IDictionary $dict
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     * @return IRouter
     */
    private function newLoadedRouter(IDictionary $dict = null)
    {
        $router = self::newRouter($dict);

        // IDs are 1 through 9 EXCEPT 6, unsorted
        $stack = [
            'i.j.foo.1.bar' => [ 2, 4 ],
            'i.j.bar.2.baz' => [ 3, 5 ],
            'i.j.baz.3.bam' => [ 5, 7 ],
            'i.j.bam.4.wop' => [ 8, 9 ],
            'i.k.wop.5.win' => [ 4, 3 ],
            'i.k.win.6.jol' => [ 5, 8 ],
            'i.k.jol.7.foo' => [ 9, 1 ],
        ];

        $prev = false;

        foreach ($stack as $path => $userIds) {
            if ($prev) {
                // link the previous path to this one
                $router->add($prev, $path, true);
            }
            foreach ($userIds as $userId) {
                // link users into their various paths
                $router->add($path, $userId, true);
            }
            $prev = $path;
        }

        $router->lock();

        return $router;
    }


    /**
     * @param int $id
     * @return int[]
     */
    private function getRealWorldClassList($id)
    {
        $classes = [
            self::CLASS_GUEST => [ 10, 111, 132, 14, 169, 198, 2, 200, 217, 222, 3, 3959, 4, 41, 4516, 4614, 4691, 47, 60, 62, 75, 79, 8, 87, 90, 96, 98, 99 ],
            self::CLASS_HOST  => [ 10, 125, 130, 182, 213, 219, 25, 49, 61, 68, 73, 83, 85 ],
        ];

        return isset($classes[$id]) ? $classes[$id] : [];
    }


    /**
     * @param IDictionary $dict
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     * @return IRouter
     */
    private static function newRealWorldRouter(IDictionary $dict = null)
    {
        $router = self::newRouter($dict);

        $fp = fopen(__DIR__ . '/bind.list', 'rb');

        while ($line = trim(fgets($fp))) {
            [$exchange, $route] = explode("\t", $line, 2);

            if (strpos($route, 'notify') === false) {
                // exclude all deprecated route types
                continue;
            }

            $userId = substr($exchange, strrpos($exchange, '.') + 1);

            $router->add($route, $userId, true);
        }

        $guest = self::CLASS_GUEST;
        $host  = self::CLASS_HOST;

        // add alt-source news component
        $router->add("*.notify.service.1.$guest.1.news.#", "*.notify.service.1.$host.1.#", true);

        return $router;
    }

    /**
     * Test that locking a router actually saves space
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     * static::markTestSkipped
     */
    public function testLockSavesMemory()
    {
        if (PHP_SAPI === 'phpdbg') {
            $this->markTestSkipped();
        }
        gc_collect_cycles();
        $before = memory_get_usage();

        $router = self::newRealWorldRouter();

        gc_collect_cycles();
        $inflated = memory_get_usage() - $before;

        $router->lock();

        gc_collect_cycles();
        $locked = memory_get_usage() - $before;

        $delta = $locked - $inflated;

//        printf("inflated : %s\n", \Alaress_Byte_Format::bytesToString($inflated));
//        printf("locked   : %s\n", \Alaress_Byte_Format::bytesToString($locked));
//        printf("-delta   : %s\n", \Alaress_Byte_Format::bytesToString($delta * -1));

        $this->assertLessThan(0, $delta);
    }


    /**
     * Test that attempting to resolve on an empty router simply gives us an empty array
     */
    public function testEmptyRouter()
    {
        $this->assertEmpty(
            $this->newResolver()->getSubscribedUserIds('anything.at.all')
        );
    }


    /**
     * Test that resolving a trivial path returns the expected subscription array
     */
    public function testSimplePath()
    {
        $resolver = $this->newResolver(self::newRouter()->add('foo.bar', 2, true));

        $this->assertEquals([ 2 ], $resolver->getSubscribedUserIds('foo.bar'));
    }


    /**
     * Test that a topic redirecting onto itself triggers a recursion
     *
     *
     */
    public function testBasicSelfRecursion()
    {
        $this->expectException(OverflowException::class);
        $topic = 'foo.bar';

        $resolver = $this->newResolver(
            self::newRouter()
                 ->add($topic, $topic, true)
        );

        $resolver->getSubscribedUserIds($topic);
    }


    /**
     * Test that a string of multiple connected redirecting in a circle triggers a recursion
     *
     *
     */
    public function testExtendedRecursion()
    {
        $this->expectException(OverflowException::class);
        $stack = [
            'i.j.foo.bar',
            'i.j.bar.baz',
            'i.j.baz.bam',
            'i.j.bam.wop',
            'i.k.wop.win',
            'i.k.win.jol',
            'i.k.jol.foo',
        ];

        $r = self::newRouter();

        for ($i = 0, $im = count($stack); $i < $im; ++$i) {
            $r->add($stack[$i], $stack[($i + 1) % $im], true);
        }

        $resolver = $this->newResolver($r);

        $resolver->getSubscribedUserIds('i.j.foo.bar');
    }


    /**
     * @return array[]
     */
    public static function dataUserCollation()
    {
        return [
            'i.k.jol.7.foo' => [ 'i.k.jol.7.foo', [ 1,                   9 ] ],
            'i.k.win.6.jol' => [ 'i.k.win.6.jol', [ 1,          5,    8, 9 ] ],
            'i.k.wop.5.win' => [ 'i.k.wop.5.win', [ 1,    3, 4, 5,    8, 9 ] ],
            'i.j.bam.4.wop' => [ 'i.j.bam.4.wop', [ 1,    3, 4, 5,    8, 9 ] ],
            'i.j.baz.3.bam' => [ 'i.j.baz.3.bam', [ 1,    3, 4, 5, 7, 8, 9 ] ],
            'i.j.bar.2.baz' => [ 'i.j.bar.2.baz', [ 1,    3, 4, 5, 7, 8, 9 ] ],
            'i.j.foo.1.bar' => [ 'i.j.foo.1.bar', [ 1, 2, 3, 4, 5, 7, 8, 9 ] ],
        ];
    }


    /**
     * Test that users in nested paths are collated, deduplicated, and returned as a flat array
     *
     *
     * @param string $path
     * @param array  $expected
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     * @throws OverflowException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataUserCollation')]
    public function testUserCollation($path, array $expected)
    {
        $ids = $this->newResolver($this->newLoadedRouter())->getSubscribedUserIds($path);

        sort($ids);

        $this->assertEquals($expected, $ids);
    }


    /**
     * @return array[]
     */
    public static function dataRealWorldExample()
    {
        return [
            'guest not news' => [ self::CLASS_GUEST, 'notnews', [ self::CLASS_GUEST ] ],
            'host not news'  => [ self::CLASS_HOST,  'notnews', [ self::CLASS_HOST ] ],
            'guest news'     => [ self::CLASS_GUEST, 'news',    [ self::CLASS_GUEST, self::CLASS_HOST ] ],
            'host news'      => [ self::CLASS_HOST,  'news',    [ self::CLASS_HOST ] ],
        ];
    }


    /**
     * Test that a simple-but-common real-world case works
     * (The news topic for homepage 10 should now also encompass everyone in homepage 61 as well)
     *
     *
     * @param int   $folderId
     * @param int   $action
     * @param array $expectedClasses
     * @throws OverflowException
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataRealWorldExample')]
    public function testRealWorldExample($folderId, $action, array $expectedClasses)
    {

        $actualUsers = $this->newResolver(self::$routerFromFile)
            ->getSubscribedUserIds("INFO.notify.service.1.$folderId.1.$action.47.published");

        $expectedUsers = array_reduce($expectedClasses, function (array $carry, $classId) {
            return array_unique(array_merge($this->getRealWorldClassList($classId), $carry));
        }, []);

        sort($actualUsers);
        sort($expectedUsers);

        $this->assertEquals($expectedUsers, $actualUsers);
    }
}

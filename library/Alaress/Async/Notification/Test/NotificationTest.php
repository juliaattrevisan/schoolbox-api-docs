<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Notification\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NotificationTest extends TestCase
{

    /**
     * @param string $title
     * @param array  $params
     * @param string $ns
     * @param string $href
     * @param string $icon
     * @param int    $level
     * @param array  $topics
     * @param int    $time
     * @param array  $meta
     * @param array  $context
     * @return \Alaress\Async\Notification\Instance\Instance
     */
    protected function newInstance(
        $title,
        $params = [],
        $ns = 'mock',
        $href = '#',
        $icon = '#',
        $level = 0,
        $topics = [],
        $time = 0,
        $meta = [],
        $context = []
    ) {
        return new \Alaress\Async\Notification\Instance\Instance(
            $title,
            $ns,
            $icon,
            $href,
            $params,
            $level,
            $topics,
            0,
            $time,
            $meta,
            $context
        );
    }

    public function testConstructSingleInstance()
    {
        $n = new \Alaress\Async\Notification\Notification([
            $this->newInstance('foobar'),
        ]);

        $this->assertInstanceOf('Alaress\Async\Notification\Notification', $n);
    }

    public function testConstructMultipleInstances()
    {
        $n = new \Alaress\Async\Notification\Notification([
            $this->newInstance('foobar'),
            $this->newInstance('foobar'),
        ]);

        $this->assertInstanceOf('Alaress\Async\Notification\Notification', $n);
    }


    public function testAppendInvalidInstance()
    {
        $this->expectException(InvalidArgumentException::class);
        new \Alaress\Async\Notification\Notification([
            $this->newInstance('foobar'),
            $this->newInstance('globber'),
        ]);
    }

    public function testAppendInstanceWithReplaceParam()
    {
        $this->markTestIncomplete();
    }

    public function testAppendInstanceWithAppendParam()
    {
        $this->markTestIncomplete();
    }

    public function testAppendInstanceWithIgnoreParam()
    {
        $this->markTestIncomplete();
    }

    public function testGetTitle()
    {
        $n = new \Alaress\Async\Notification\Notification([
            $this->newInstance('foobar'),
        ]);

        $this->assertEquals('foobar', $n->getTitle());
    }

    public function testGetTimestamp()
    {
        // the params must differ for the second to survive deduping
        $pBar = new \Alaress\Async\Notification\Parameter\Param('bar');
        $pBaz = new \Alaress\Async\Notification\Parameter\Param('baz');

        $n = new \Alaress\Async\Notification\Notification([
            $this->newInstance(':foo', ['foo' => $pBar], 'mock', '#', '#', 0, [], 9001),
            $this->newInstance(':foo', ['foo' => $pBaz], 'mock', '#', '#', 0, [], 90001),
        ]);

        $this->assertEquals(90001, $n->getTimestamp());
    }

    public function testGetNamespace()
    {
        $n = new \Alaress\Async\Notification\Notification([
            $this->newInstance('foobar', [], 'bazbin'),
        ]);

        $this->assertEquals('bazbin', $n->getNamespace());
    }

    public function testGetIcon()
    {
        $n = new \Alaress\Async\Notification\Notification([
            $this->newInstance('foobar', [], 'mock', '#', '/super-awesome/icon.jpg'),
        ]);

        $this->assertEquals('/super-awesome/icon.jpg', $n->getIcon());
    }

    public function testGetHref()
    {
        $n = new \Alaress\Async\Notification\Notification([
            $this->newInstance('foobar', [], 'mock', '/mega-awesome/link'),
        ]);

        $this->assertEquals('/mega-awesome/link', $n->getHref());
    }

    public function testGetMeta()
    {
        $n = new \Alaress\Async\Notification\Notification([
            $this->newInstance('foobar', [], 'mock', '#', '#', 0, [], 0, [
                'body' => 'contentment',
                'byline' => 'authorman',
                'foo' => 'bar',
            ]),
        ]);

        $this->assertCount(3, $n->getMeta());
        $this->assertEquals('bar', $n->getMeta('foo'));
        $this->assertEquals('', $n->getMeta('empty'));
        $this->assertEquals('contentment', $n->getBody());
        $this->assertEquals('authorman', $n->getByline());
    }

    public function testGetParameters()
    {
        $n = new \Alaress\Async\Notification\Notification([
            $this->newInstance(':foobar', [
                'foobar' => new \Alaress\Async\Notification\Parameter\Param(
                    'bazbin',
                    '#',
                    \Alaress\Async\Notification\Parameter\ParameterInterface::STACKING_REPLACE
                ),
            ]),
        ]);

        $p = $n->getParameters();

        $this->assertArrayHasKey('foobar', $p);

        $v = array_values($p['foobar']);

        $this->assertEquals('bazbin', $v[0][0]->getText());
    }
}

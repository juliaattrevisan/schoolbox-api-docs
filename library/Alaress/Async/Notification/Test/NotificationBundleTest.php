<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Notification\Test;

use PHPUnit\Framework\TestCase;

class NotificationBundleTest extends TestCase
{

    public function testAppendSingleInstance()
    {
        $bundle = new \Alaress\Async\Notification\NotificationBundle();

        $bundle->appendInstance(new \Alaress\Async\Notification\Instance\Instance(
            'foobar', 'mock', '#', '#', [], 0, [], 0, [], []
        ));

        $this->assertEquals(1, $bundle->count());
    }

    public function testAppendMultipleInstances()
    {
        $bundle = new \Alaress\Async\Notification\NotificationBundle();

        $bundle->appendInstances([
            new \Alaress\Async\Notification\Instance\Instance(
                'foobar', 'mock', '#', '#', [], 0, [], 0, [], []
            ),
            new \Alaress\Async\Notification\Instance\Instance(
                'globber', 'mock', '#', '#', [], 0, [], 0, [], []
            ),
        ]);

        $this->assertEquals(2, $bundle->count());
    }

    public function testAppendInstanceMultipleTimes()
    {
        $bundle = new \Alaress\Async\Notification\NotificationBundle();

        $inst = new \Alaress\Async\Notification\Instance\Instance(
            'foobar', 'mock', '#', '#', [], 0, [], 0, [], []
        );

        $bundle->appendInstance($inst);

        // add it again
        $bundle->appendInstance($inst);

        // and add a fresh-but-identical instance
        $bundle->appendInstance(new \Alaress\Async\Notification\Instance\Instance(
            'foobar', 'mock', '#', '#', [], 0, [], 0, [], []
        ));

        // should still be 1 notification
        $this->assertEquals(1, $bundle->count());
    }

    public function testAppendSingleNotification()
    {
        $bundle = new \Alaress\Async\Notification\NotificationBundle();

        $n = new \Alaress\Async\Notification\Notification([
            new \Alaress\Async\Notification\Instance\Instance(
                'foobar', 'mock', '#', '#', [], 0, [], 0, [], []
            ),
        ]);

        $bundle->appendNotification($n);

        $this->assertEquals(1, $bundle->count());
    }

    public function testAppendMultipleNotifications()
    {
        $bundle = new \Alaress\Async\Notification\NotificationBundle();

        $n[] = new \Alaress\Async\Notification\Notification([
            new \Alaress\Async\Notification\Instance\Instance(
                'foobar', 'mock', '#', '#', [], 0, [], 0, [], []
            ),
        ]);

        $n[] = new \Alaress\Async\Notification\Notification([
            new \Alaress\Async\Notification\Instance\Instance(
                'globber', 'mock', '#', '#', [], 0, [], 0, [], []
            ),
        ]);

        $bundle->appendNotifications($n);

        $this->assertEquals(2, $bundle->count());
    }

    public function testAppendNotificationMultipleTimes()
    {
        $bundle = new \Alaress\Async\Notification\NotificationBundle();

        $n = [];

        for ($i = 0; $i < 3; ++$i) {
            $n[] = new \Alaress\Async\Notification\Notification([
                new \Alaress\Async\Notification\Instance\Instance(
                    'foobar', 'mock', '#', '#', [], 0, [], 0, [], []
                ),
            ]);
        }

        $bundle->appendNotifications($n);

        $this->assertEquals(1, $bundle->count());
    }


}

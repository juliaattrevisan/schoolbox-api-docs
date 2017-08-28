<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Notification\Test;

use PHPUnit_Framework_TestCase;

class NotificationBundleTest extends PHPUnit_Framework_TestCase {

	public function testAppendSingleInstance() {
		$bundle = new \Alaress\Async\Notification\NotificationBundle();

		$bundle->appendInstance(new \Alaress\Async\Notification\Instance\Instance(
			'foobar', 'mock', '#', '#', array(), 0, array(), 0, array(), array()
		));

		$this->assertEquals(1, $bundle->count());
	}

	public function testAppendMultipleInstances() {
		$bundle = new \Alaress\Async\Notification\NotificationBundle();

		$bundle->appendInstances(array(
			new \Alaress\Async\Notification\Instance\Instance(
				'foobar', 'mock', '#', '#', array(), 0, array(), 0, array(), array()
			),
			new \Alaress\Async\Notification\Instance\Instance(
				'globber', 'mock', '#', '#', array(), 0, array(), 0, array(), array()
			)
		));

		$this->assertEquals(2, $bundle->count());
	}

	public function testAppendInstanceMultipleTimes() {
		$bundle = new \Alaress\Async\Notification\NotificationBundle();

		$inst = new \Alaress\Async\Notification\Instance\Instance(
			'foobar', 'mock', '#', '#', array(), 0, array(), 0, array(), array()
		);

		$bundle->appendInstance($inst);

		// add it again
		$bundle->appendInstance($inst);

		// and add a fresh-but-identical instance
		$bundle->appendInstance(new \Alaress\Async\Notification\Instance\Instance(
			'foobar', 'mock', '#', '#', array(), 0, array(), 0, array(), array()
		));

		// should still be 1 notification
		$this->assertEquals(1, $bundle->count());
	}

	public function testAppendSingleNotification() {
		$bundle = new \Alaress\Async\Notification\NotificationBundle();

		$n = new \Alaress\Async\Notification\Notification(array(
			new \Alaress\Async\Notification\Instance\Instance(
				'foobar', 'mock', '#', '#', array(), 0, array(), 0, array(), array()
			)
		));

		$bundle->appendNotification($n);

		$this->assertEquals(1, $bundle->count());
	}

	public function testAppendMultipleNotifications() {
		$bundle = new \Alaress\Async\Notification\NotificationBundle();

		$n[] = new \Alaress\Async\Notification\Notification(array(
			new \Alaress\Async\Notification\Instance\Instance(
				'foobar', 'mock', '#', '#', array(), 0, array(), 0, array(), array()
			)
		));

		$n[] = new \Alaress\Async\Notification\Notification(array(
			new \Alaress\Async\Notification\Instance\Instance(
				'globber', 'mock', '#', '#', array(), 0, array(), 0, array(), array()
			)
		));

		$bundle->appendNotifications($n);

		$this->assertEquals(2, $bundle->count());
	}

	public function testAppendNotificationMultipleTimes() {
		$bundle = new \Alaress\Async\Notification\NotificationBundle();

		$n = array();

		for($i = 0; $i < 3; ++$i) {
			$n[] = new \Alaress\Async\Notification\Notification(array(
				new \Alaress\Async\Notification\Instance\Instance(
					'foobar', 'mock', '#', '#', array(), 0, array(), 0, array(), array()
				)
			));
		}

		$bundle->appendNotifications($n);

		$this->assertEquals(1, $bundle->count());
	}


}

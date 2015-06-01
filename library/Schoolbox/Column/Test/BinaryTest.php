<?php
/**
 * Tests for binary column type - its hydrator and dehydrator don't change
 * anything.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Column\Test;
use Schoolbox\Column\Base;
use Schoolbox\Column\Binary;

class BinaryTest extends TestCase {

	/**
	 * @var Base
	 */
	protected $object;

	public function setupObject() {
		$this->object = new Binary();
	}

	public function getTestHydrate() {
		return array(
			array(true, true),
			array(false, false),
			array(null, null),
			array('Dumb', 'Dumb'),
		);
	}

	public function getTestDehydrate() {
		return array(
			array(true, true),
			array(false, false),
			array(null, null),
			array('Dumb', 'Dumb'),
			array('12'.chr(128).'34', '12'.chr(128).'34'),
		);
	}
}

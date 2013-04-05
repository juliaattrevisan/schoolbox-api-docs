<?php
/**
 * Tests for base column type - its hydrator and dehydrator don't change
 * anything.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Column\Test;
use Schoolbox\Column\Base;

class BaseTest extends TestCase {

	/**
	 * @var Base
	 */
	protected $object;

	public function setupObject() {
		$this->object = new Base();
	}

	public function getTestHydrate() {
		return array(
			array(true, true),
			array(false, false),
			array('Dumb', 'Dumb'),
		);
	}

	public function getTestDehydrate() {
		return array(
			array(true, true),
			array(false, false),
			array('Dumb', 'Dumb'),
		);
	}
}

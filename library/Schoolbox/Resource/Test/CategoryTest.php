<?php
/**
 * Unit tests for Schoolbox\Resource\Category class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Test;
use Schoolbox\Resource\Category;
use Schoolbox\Test\TestCase;

class CategoryTest extends TestCase {

	/**
	 * The file that's being tested.
	 *
	 * @var File
	 */
	protected $object;

	protected $config_vars = array(
		array('file_dir', '/tmp')
	);

	public function setUp() {
		parent::setUp();
		$this->object = new Category();
	}

	public function testCodes() {
		$this->markTestIncomplete();
	}
}

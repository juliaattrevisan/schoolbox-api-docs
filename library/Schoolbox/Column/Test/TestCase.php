<?php
/**
 * Base test case for column types.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Column\Test;
use Schoolbox\Column\IColumnType;
use Schoolbox\Test\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase {

	/**
	 * @var IColumnType
	 */
	protected $object;

	/**
	 * Creates the column type object that is being tested..
	 *
	 * @return array
	 */
	abstract public function setupObject();

	/**
	 * Sets up mock config object, in case it's needed.
	 */
	public function setUp() {
		parent::setUp();
		$this->setupObject();
	}

	/**
	 * Data provider for $this->testHydrate().
	 *
	 * @return array
	 */
	abstract public function getTestHydrate();

	/**
	 * @dataProvider getTestHydrate
	 */
	public function testHydrate($input, $expected_output) {
		$this->assertSame($expected_output, $this->object->hydrate($input));
	}

	/**
	 * Data provider for $this->testDehydrate().
	 *
	 * @return array
	 */
	abstract public function getTestDehydrate();

	/**
	 * @dataProvider getTestDehydrate
	 */
	public function testDehydrate($input, $expected_output) {
		$this->assertSame($expected_output, $this->object->dehydrate($input));
	}
}

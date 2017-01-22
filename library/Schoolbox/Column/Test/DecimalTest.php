<?php
/**
 * Tests for decimal column type.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Column\Test;
use Schoolbox\Column\Decimal;

class DecimalTest extends TestCase {

	/**
	 * @var Decimal
	 */
	protected $object;

	public function setupObject() {
		$this->object = new Decimal();
	}

	public function getTestHydrate() {
		return array(
            array(null,        null),
			array('0',         0.0),
			array('0.00',      0.0),
			array('0.376',     0.376),
            array('1990',      1990.0),
			array('1990.000',  1990.0),
			array('-13.3',     -13.30),
		);
	}

	public function getTestDehydrate() {
		return array(
            array(null,   null),
            array('',     null),
            array(0,      0),
			array(0.00,   0.00),
			array(0.375,  0.375),
			array(1990,   1990),
			array(-13.3,  -13.30),
		);
	}

	/**
	 * @dataProvider getTestHydrate
	 */
	public function testHydrate($input, $expected_output) {
		$this->assertSame($expected_output, $this->object->hydrate($input));
	}

	/**
	 * @dataProvider getTestDehydrate
	 */
	public function testDehydrate($input, $expected_output) {
		$this->assertSame($expected_output, $this->object->dehydrate($input));
	}
}

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
			array('0',     '0',        0),
			array('0',     '0.00',     2),
			array('0.375', '0',        0),
			array('0.374', '0.37',     2),
			array('0.375', '0.38',     2),
			array('0.376', '0.38',     2),
			array('1990',  '1990.000', 3),
			array(-13.3,  '-13.30',    2),
		);
	}

	public function getTestDehydrate() {
		return array(
			array('0',     '0',        0),
			array('0',     '0.00',     2),
			array('0.375', '0',        0),
			array('0.374', '0.37',     2),
			array('0.375', '0.38',     2),
			array('0.376', '0.38',     2),
			array('1990',  '1990.000', 3),
			array(-13.3,  '-13.30',    2),
		);
	}

	/**
	 * @dataProvider getTestHydrate
	 */
	public function testHydrate($input, $expected_output, $places) {
		$this->object->setPlaces($places);
		$this->assertSame($expected_output, $this->object->hydrate($input));
	}

	/**
	 * @dataProvider getTestDehydrate
	 */
	public function testDehydrate($input, $expected_output, $places) {
		$this->object->setPlaces($places);
		$this->assertSame($expected_output, $this->object->dehydrate($input));
	}
}

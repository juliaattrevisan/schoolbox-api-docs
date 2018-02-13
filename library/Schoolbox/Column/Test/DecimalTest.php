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

    /**
     * @inheritdoc
     */
    public function setupObject() {
		$this->object = new Decimal();
	}

    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
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
     * @param null|string $input
     * @param null|float $expected_output
     */
    public function testHydrate($input = null, $expected_output = null) {
		$this->assertSame($expected_output, $this->object->hydrate($input));
	}

    /**
     * @dataProvider getTestDehydrate
     * @param null|float $input
     * @param null|float $expected_output
     */
    public function testDehydrate($input = null, $expected_output = null) {
		$this->assertSame($expected_output, $this->object->dehydrate($input));
	}
}

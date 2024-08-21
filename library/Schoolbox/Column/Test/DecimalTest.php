<?php

/**
 * Tests for decimal column type.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use Schoolbox\Column\Decimal;

class DecimalTest extends TestCase
{

    /**
     * @var Decimal
     */
    protected $object;

    /**
     * @inheritdoc
     */
    public function setupObject()
    {
        $this->object = new Decimal();
    }

    /**
     * @inheritdoc
     */
    public static function getTestHydrate()
    {
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
    public static function getTestDehydrate()
    {
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
     * @param null|string $input
     * @param null|float $expected_output
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestHydrate')]
    public function testHydrate($input = null, $expected_output = null)
    {
        $this->assertSame($expected_output, $this->object->hydrate($input));
    }

    /**
     * @param null|float $input
     * @param null|float $expected_output
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestDehydrate')]
    public function testDehydrate($input = null, $expected_output = null)
    {
        $this->assertSame($expected_output, $this->object->dehydrate($input));
    }
}

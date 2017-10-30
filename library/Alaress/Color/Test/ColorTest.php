<?php
/**
 * Tests for the color class
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Color\Test;

use Alaress\Color\Color;
use PHPUnit\Framework\TestCase;

class ColorTest extends TestCase
{
    /**
     * @var Color
     */
    protected $color;

    public function setUp()
    {
        $this->color = new Color(0, 0, 0);
    }

    public function getAssertColorSuccessData()
    {
        return array(
            array(0, 0),
            array(1, 1),
            array(0Xff, 255),
            array('16', 16),
        );
    }

    /**
     * @dataProvider getAssertColorSuccessData
     */
    public function testAssertColorSuccess($value, $expectedValue)
    {
        $this->color->setGreen($value);
        $this->assertEquals($expectedValue, $this->color->getGreen());
    }

    public function getAssertColorFailureData()
    {
        return array(
            array(-1),
            array(0.1),
            array(0xfff),
            array('cheese'),
            array(''),
        );
    }

    /**
     * @dataProvider getAssertColorFailureData
     * @expectedException \InvalidArgumentException
     */
    public function testAssertColorFailure($value)
    {
        $this->color->setGreen($value);
    }

    public function getAssertAlphaSuccessData()
    {
        return array(
            array(0, 0.0),
            array(1, 1.0),
            array('0.1', 0.1),
        );
    }

    /**
     * @dataProvider getAssertAlphaSuccessData
     */
    public function testAssertAlphaSuccess($value, $expectedValue)
    {
        $this->color->setAlpha($value);
        $this->assertEquals($expectedValue, $this->color->getAlpha(), '', 0.01);
    }

    public function getAssertAlphaFailureData()
    {
        return array(
            array(-1),
            array(1.1),
            array(99999),
            array('cheese'),
        );
    }

    /**
     * @dataProvider getAssertAlphaFailureData
     * @expectedException \InvalidArgumentException
     */
    public function testAssertAlphaFailure($value)
    {
        $this->color->setAlpha($value);
    }

    public function getGetAsHexData()
    {
        return array(
            array(new Color(0xFF, 0X77, 0X00), 'FF7700'),
            array(new Color(0, 14, 15), '000E0F'),
        );
    }

    /**
     * @dataProvider getGetAsHexData
     */
    public function testGetAsHex(Color $color, $hex)
    {
        $this->assertEquals($hex, $color->getAsHex());
    }

    public function getGetAsHexAlphaData()
    {
        return array(
            array(new Color(0xFF, 0X77, 0X00, 0.5), 'FF77007F'),
            array(new Color(0, 14, 15, 0.1), '000E0F19'),
        );
    }

    /**
     * @dataProvider getGetAsHexAlphaData
     */
    public function testGetAsHexAlpha(Color $color, $hex)
    {
        $this->assertEquals($hex, $color->getAsHexAlpha());
    }


    public function getGetAsRgbData()
    {
        return array(
            array(new Color(0xFF, 0X77, 0X00), 'rgb(255, 119, 0)'),
            array(new Color(0, 14, 15), 'rgb(0, 14, 15)'),
        );
    }

    /**
     * @dataProvider getGetAsRgbData
     */
    public function testGetAsRgb(Color $color, $rgb)
    {
        $this->assertEquals($rgb, $color->getAsRgb());
    }

    public function getGetAsRgbAlphaData()
    {
        return array(
            array(new Color(0xFF, 0X77, 0X00, 0.5), 'rgba(255, 119, 0, 0.500)'),
            array(new Color(0, 14, 15, 0.1), 'rgba(0, 14, 15, 0.100)'),
        );
    }

    /**
     * @dataProvider getGetAsRgbAlphaData
     */
    public function testGetAsRgbAlpha(Color $color, $rgb)
    {
        $this->assertEquals($rgb, $color->getAsRgba());
    }

    /**
     * @dataProvider getGetAsRgbAlphaData
     */
    public function testToString(Color $color, $rgb)
    {
        $this->assertEquals($rgb, "$color");
    }

    public function testSerialize()
    {
        // Test with ridiculous precision
        $color = new Color(33, 44, 55, 0.9231904809857124398123045891727293479873249887987);
        $serialized = serialize($color);
        /** @var Color $unserialized */
        $unserialized = unserialize($serialized);
        $this->assertInstanceOf(get_class($color), $unserialized);
        $this->assertNotSame($color, $unserialized);
        $this->assertEquals($color->getRed(), $unserialized->getRed());
        $this->assertEquals($color->getGreen(), $unserialized->getGreen());
        $this->assertEquals($color->getBlue(), $unserialized->getBlue());
        $this->assertEquals($color->getAlpha(), $unserialized->getAlpha());
    }
}

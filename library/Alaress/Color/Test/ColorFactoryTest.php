<?php

/**
 * Tests for the color factory
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Color\Test;

use Alaress\Color\Color;
use Alaress\Color\ColorFactory;
use PHPUnit\Framework\TestCase;

class ColorFactoryTest extends TestCase
{
    /**
     * @var ColorFactory
     */
    protected $colorFactory;

    public function setUp(): void
    {
        $this->colorFactory = new ColorFactory();
    }

    public static function getCreateHexData(): array
    {
        return array(
            array('#123456', new Color(0x12, 0x34, 0x56)),
            array('7890Ab',  new Color(0x78, 0x90, 0xAB)),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getCreateHexData')]
    public function testCreateHex($colorString, Color $expectedColor): void
    {
        $actualColor = $this->colorFactory->build($colorString);
        $this->assertEquals($expectedColor->getRed(), $actualColor->getRed());
        $this->assertEquals($expectedColor->getGreen(), $actualColor->getGreen());
        $this->assertEquals($expectedColor->getBlue(), $actualColor->getBlue());
    }

    public static function getCreateHexAlphaData()
    {
        return array(
            array('#12345600', new Color(0x12, 0x34, 0x56, 0)),
            array('7890Ab80',  new Color(0x78, 0x90, 0xAB, 0.5)),
            array('ffFFffE6',  new Color(0xFF, 0xFF, 0xFF, 0.9)),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getCreateHexAlphaData')]
    public function testCreateHexAlpha($colorString, Color $expectedColor)
    {
        $actualColor = $this->colorFactory->build($colorString);
        $this->assertEquals($expectedColor->getRed(), $actualColor->getRed());
        $this->assertEquals($expectedColor->getGreen(), $actualColor->getGreen());
        $this->assertEquals($expectedColor->getBlue(), $actualColor->getBlue());
        // Working with floats for alpha channel, expect precisional issues
        $this->assertEqualsWithDelta($expectedColor->getAlpha(), $actualColor->getAlpha(), 0.01);
    }

    public static function getCreateRgbData()
    {
        return array(
            array('rgb(12, 77, 99)', new Color(12, 77, 99)),
            array(' RGb( 12 , 77 , 99 )   ', new Color(12, 77, 99)),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getCreateRgbData')]
    public function testCreateRgbData($colorString, Color $expectedColor)
    {
        $actualColor = $this->colorFactory->build($colorString);
        $this->assertEquals($expectedColor->getRed(), $actualColor->getRed());
        $this->assertEquals($expectedColor->getGreen(), $actualColor->getGreen());
        $this->assertEquals($expectedColor->getBlue(), $actualColor->getBlue());
    }

    public static function getCreateRgbaData()
    {
        return array(
            array('rgba(12, 77, 255, 0.4)', new Color(12, 77, 255, 0.4)),
            array(' RGba( 12 , 77 , 255, 0.1)   ', new Color(12, 77, 255, 0.1)),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getCreateRgbaData')]
    public function testCreateRgbaData($colorString, Color $expectedColor)
    {
        $actualColor = $this->colorFactory->build($colorString);
        $this->assertEquals($expectedColor->getRed(), $actualColor->getRed());
        $this->assertEquals($expectedColor->getGreen(), $actualColor->getGreen());
        $this->assertEquals($expectedColor->getBlue(), $actualColor->getBlue());
        // Working with floats for alpha channel, expect precisional issues
        $this->assertEqualsWithDelta($expectedColor->getAlpha(), $actualColor->getAlpha(), 0.01);
    }

    public static function getInvalidFormatData()
    {
        return array(
            array(''),
            array('CrUsHeDnUtSaCk'),
            array('rgb(12, 77)'),
            array('FFFFFG'),
            array('FFFFFFFFF'),
        );
    }

    
    #[\PHPUnit\Framework\Attributes\DataProvider('getInvalidFormatData')]
    public function testInvalidFormat($colorString)
    {
        $this->expectExceptionMessageMatches("/does not match expected format/");
        $this->expectException(\InvalidArgumentException::class);
        $this->colorFactory->build($colorString);
    }
}

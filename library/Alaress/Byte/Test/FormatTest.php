<?php
/**
 * Created by JetBrains PhpStorm.
 * User: jleckie
 * Date: 9/19/11
 * Time: 8:50 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Alaress\Byte\Test;

use Alaress_Byte_Format;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function testBytesToB(): void
    {
        $input = '100';
        $expected = '100 B';

        $this->assertEquals($expected, Alaress_Byte_Format::bytesToString($input));
    }

    public function testBytesToBytes(): void
    {
        $input = '100 B';
        $expected = '100 B';

        $this->assertEquals($expected, Alaress_Byte_Format::bytesToString($input));
    }

    public function testBytesToBytesNoSpace(): void
    {
        $input = '100B';
        $expected = '100 B';

        $this->assertEquals($expected, Alaress_Byte_Format::bytesToString($input));
    }

    public function testBytesToMB()
    {
        $input = '1245873';
        $expected = '1.19 MB';

        $this->assertEquals($expected, Alaress_Byte_Format::bytesToString($input));
    }

    public function testBytesToMBPrecision()
    {
        $input = '1245873';
        $expected = '1 MB';

        $this->assertEquals($expected, Alaress_Byte_Format::bytesToString($input, 0));
    }

    public function testBytesToGB()
    {
        $input = '12458731246';
        $expected = '11.6 GB';

        $this->assertEquals($expected, Alaress_Byte_Format::bytesToString($input));
    }

    public function testBToBytes()
    {
        $input = '100 B';
        $expected = 100;

        $this->assertEquals($expected, Alaress_Byte_Format::stringToBytes($input));
    }

    public function testMToBytes()
    {
        $input = '11.19M';
        $expected = '11733565';

        $this->assertEquals($expected, Alaress_Byte_Format::stringToBytes($input));
    }

    public function testKToBytes()
    {
        $input = '10 k';
        $expected = '10240';

        $this->assertEquals($expected, Alaress_Byte_Format::stringToBytes($input));
    }

    public function testMBToBytes()
    {
        $input = '11.19 mb';
        $expected = '11733565';

        $this->assertEquals($expected, Alaress_Byte_Format::stringToBytes($input));
    }

    public function testGBToBytes()
    {
        $input = '11.6 gB';
        $expected = '12455405158';

        $this->assertEquals($expected, Alaress_Byte_Format::stringToBytes($input));
    }

    public function testDataTypeStringToBytes(): void
    {
        /** @noinspection UnnecessaryAssertionInspection */
        $this->assertInternalType('int', Alaress_Byte_Format::stringToBytes('10 MB'));
    }

    public function testDataTypeBytesToString(): void
    {
        /** @noinspection UnnecessaryAssertionInspection */
        $this->assertInternalType('string', Alaress_Byte_Format::bytesToString('10 MB'));
    }
}

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
    public function testBytesToMB()
    {
        $input = '1245873';
        $expected = '1.19 MB';

        $this->assertEquals(Alaress_Byte_Format::bytesToString($input), $expected);
    }

    public function testBytesToMBPrecision()
    {
        $input = '1245873';
        $expected = '1 MB';

        $this->assertEquals(Alaress_Byte_Format::bytesToString($input, 0), $expected);
    }

    public function testBytesToGB()
    {
        $input = '12458731246';
        $expected = '11.6 GB';

        $this->assertEquals(Alaress_Byte_Format::bytesToString($input), $expected);
    }

    public function testMToBytes()
    {
        $input = '11.19M';
        $expected = '11733565';

        $this->assertEquals(Alaress_Byte_Format::stringToBytes($input), $expected);
    }

    public function testKToBytes()
    {
        $input = '10 k';
        $expected = '10240';

        $this->assertEquals(Alaress_Byte_Format::stringToBytes($input), $expected);
    }

    public function testMBToBytes()
    {
        $input = '11.19 mb';
        $expected = '11733565';

        $this->assertEquals(Alaress_Byte_Format::stringToBytes($input), $expected);
    }

    public function testGBToBytes()
    {
        $input = '11.6 gB';
        $expected = '12455405158';

        $this->assertEquals(Alaress_Byte_Format::stringToBytes($input), $expected);
    }


}

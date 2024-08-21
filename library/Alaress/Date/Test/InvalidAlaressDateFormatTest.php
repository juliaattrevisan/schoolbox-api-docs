<?php

namespace Alaress\Date\Test;

use Alaress\Test\Date\MockDateObject;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class InvalidAlaressDateFormatTest extends TestCase
{
    use MockDateObject;

    /**
     * Empty string input
     * Returns empty string
     *
     *
     *
     * @return void
     */
    public function testEmptyString()
    {
        $this->expectException(InvalidArgumentException::class);
        $input = '';
        $date = self::buildDateObject($input, 'Y-m-d H:i:s');
        $this->assertFalse($date->isValid());
    }

    /**
     * Random text string input
     * Returns empty string
     *
     *
     *
     * @return void
     */
    public function testString()
    {
        $this->expectException(InvalidArgumentException::class);
        $input = 'gronk de la guber';
        $date = self::buildDateObject($input, 'Y-m-d H:i:s');
        $this->assertFalse($date->isValid());
    }

    /**
     * Random but illegal digits in a date format
     * Returns empty string
     *
     * @return void
     */
    public function testInvalidDate1()
    {
        $input = '43/67/2011';
        $date = @self::buildDateObject($input, 'd/m/Y');
        $this->assertFalse($date->isValid());
    }

    /**
     * Random but illegal digits in a date format
     * Returns empty string
     *
     * @return void
     */
    public function testInvalidDate2()
    {
        $input = '97/27/1011';
        $date = @self::buildDateObject($input, 'd/m/Y');
        $this->assertFalse($date->isValid());
    }

    /**
     * Random but illegal digits in a date format
     * Returns empty string
     *
     * This isn't a valid date format at all.
     *
     *
     *
     * @return void
     */
    public function testInvalidDate3()
    {
        $this->expectException(InvalidArgumentException::class);
        $input = '4333/6735/34508';
        $date = self::buildDateObject($input, 'd/m/Y');
        $this->assertFalse($date->isValid());
    }

    /**
     * Random but illegal digits in a date format
     * Returns empty string
     *
     *
     *
     * @return void
     */
    public function testInvalidDate4()
    {
        $this->expectException(InvalidArgumentException::class);
        $input = '234/82354';
        $date = self::buildDateObject($input, 'd/m/Y');
        $this->assertFalse($date->isValid());
    }

    /**
     * Random but illegal digits in a partial date format
     * Returns empty string
     *
     *
     *
     * @return void
     */
    public function testPartialDate1()
    {
        $this->expectException(InvalidArgumentException::class);
        $input = '12/02';
        $date = self::buildDateObject($input, 'd/m/Y');
        $this->assertFalse($date->isValid());
    }

    /**
     * Random but illegal digits in a partial date format
     * Returns empty string
     *
     *
     *
     * @return void
     */
    public function testPartialDate2()
    {
        $this->expectException(InvalidArgumentException::class);
        $input = '02-02';
        $date = self::buildDateObject($input, 'd/m/Y');
        $this->assertFalse($date->isValid());
    }

    /**
     * Random but illegal digits in a partial date format
     * Returns empty string
     *
     *
     *
     * @return void
     */
    public function testPartialDate3()
    {
        $this->expectException(InvalidArgumentException::class);
        $input = '02-2001';
        $date = self::buildDateObject($input, 'd/m/Y');
        $this->assertFalse($date->isValid());
    }
}

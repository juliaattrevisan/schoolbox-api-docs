<?php
/**
 * Test for verifying that null dates work as expected.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2015 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Alaress\Date\Test;

use Alaress\Test\Date\MockDateObject;
use Alaress_Date_Locale_Static;
use Alaress_Date_NullDate;
use BadMethodCallException;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class NullDateTest extends TestCase
{
    use MockDateObject;

    /**
     * Test construction of null datetimes
     */
    public function testConstruction()
    {
        $nullDate = Alaress_Date_NullDate::now();
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $nullDate);

        $nullDate = Alaress_Date_NullDate::newLocalShort(
            '03/03/2015',
            new DateTimeZone('UTC'),
            new Alaress_Date_Locale_Static()
        );
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $nullDate);

        $nullDate = Alaress_Date_NullDate::newLocal(
            '03/03/2015 8:16am',
            new DateTimeZone('UTC'),
            new Alaress_Date_Locale_Static()
        );
        $this->assertInstanceOf(Alaress_Date_NullDate::class, $nullDate);
    }

    /**
     * Test properties of non-null datetimes
     */
    public function testNonNullDateProperties()
    {
        $nonNullDate = $this->buildDateObject('2014-03-13', Alaress_Date_NullDate::DATABASE_DATE_FORMAT);
        $this->assertNotEquals('', "$nonNullDate");
        $this->assertFalse($nonNullDate->isNull());
        $this->assertTrue($nonNullDate->isValid());
    }

    /**
     * Test properties of null datetimes
     */
    public function testNullDateProperties()
    {
        $nullDate = new Alaress_Date_NullDate();
        $this->assertEquals('', "$nullDate");
        $this->assertTrue($nullDate->isNull());
        $this->assertTrue($nullDate->isValid());
    }

    /**
     * Test that operations on null datetimes returns empty string
     */
    public function testOperations()
    {
        $nullDate = new Alaress_Date_NullDate();
        $this->assertSame('', $nullDate->getUsingFormat($nullDate::DATABASE_FORMAT));
    }

    /**
     * Test that locale-based formatting on null datetimes returns empty string
     */
    public function testLocale()
    {
        $nullDate = new Alaress_Date_NullDate();
        $this->assertSame('', $nullDate->getLocal());
    }

    /**
     * Test that comparisons on null datetimes don't work
     *
     * @expectedException BadMethodCallException
     */
    public function testComparison()
    {
        $nullDate = new Alaress_Date_NullDate();
        $nullDate->eq($this->buildDateObject('2014-03-13', Alaress_Date_NullDate::DATABASE_DATE_FORMAT));
    }

    /**
     * Test that cloning of a null datetime works
     */
    public function testClone()
    {
        $nullDate = new Alaress_Date_NullDate();
        $clone = clone $nullDate;
        $this->assertEquals($nullDate, $clone);
    }
}

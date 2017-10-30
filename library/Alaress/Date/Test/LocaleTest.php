<?php
/**
 * Test for verifying that locale based tests work as expected.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2015 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Alaress\Date\Test;

use Alaress\Test\Date\MockDateObject;
use Alaress_Date_Format;
use Alaress_Date_Locale_Config;
use PHPUnit\Framework\TestCase;

class LocaleTest extends TestCase
{
    use MockDateObject;

    /**
     * @var Alaress_Date_Locale_Config
     */
    protected $dateLocale;

    /**
     * Set up a mock config object.
     */
    public function setup()
    {
        $mockConfig = $this->getMock('\\Schoolbox\\Config\\Loader');
        $mockConfig
            ->expects($this->any())
            ->method('get')
            ->will(
                $this->returnValueMap([
                    ['date_database', 'Y-m-d H:i:s',],
                    ['date_local', 'd/m/Y',],
                    ['date_long', 'l F jS Y',],
                    ['date_notime', 'jS M Y',],
                    ['date_only', 'jS M',],
                    ['date_short', 'jS M Y',],
                    ['date_time', 'g:ia',],
                    ['date_timezone', 'Australia/Victoria',],
                ])
            );
        $this->dateLocale = new Alaress_Date_Locale_Config();
        $this->dateLocale->setConfig($mockConfig);
    }

    protected function getTestDateObject()
    {
        return $this->buildDateObject(
            '2015-03-25 08:01:13',
            Alaress_Date_Format::DATABASE_FORMAT,
            null,
            $this->dateLocale
        );
    }

    public function testGetLocal()
    {
        $date = $this->getTestDateObject();
        $this->assertEquals('25/03/2015 8:01am', $date->getLocal());
    }

    public function testGetLocalShort()
    {
        $date = $this->getTestDateObject();
        $this->assertEquals('25/03/2015', $date->getLocalShort());
    }

    public function testGetLong()
    {
        $date = $this->getTestDateObject();
        $this->assertEquals('Wednesday March 25th 2015 8:01am', $date->getLong());
    }

    public function testGetLongDateOnly()
    {
        $date = $this->getTestDateObject();
        $this->assertEquals('Wednesday March 25th 2015', $date->getLongDateOnly());
    }

    public function testGetShort()
    {
        $date = $this->getTestDateObject();
        $this->assertEquals('25th Mar 2015 8:01am', $date->getShort());
    }

    public function testGetDateOnly()
    {
        $date = $this->getTestDateObject();
        $this->assertEquals('25th Mar', $date->getDateOnly());
    }

    public function testGetTimeOnly()
    {
        $date = $this->getTestDateObject();
        $this->assertEquals('8:01am', $date->getTimeOnly());
    }

    public function testGetCalendarDate()
    {
        $date = $this->getTestDateObject();
        $this->assertEquals('25th Mar', $date->getCalendarDate());
    }

    public function testGetCalendarDateTime()
    {
        $date = $this->getTestDateObject();
        $this->assertEquals('25th Mar 8:01am', $date->getCalendarDateTime());
    }
}

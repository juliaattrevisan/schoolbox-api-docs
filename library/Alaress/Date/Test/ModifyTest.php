<?php

/**
 * Tests for verifying modification of dates.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Date\Test;

use Alaress\Test\Date\MockDateObject;
use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;

class ModifyTest extends TestCase
{
    use MockDateObject;

    public function testModify()
    {
        $date = self::buildDateObject('2015-03-25 00:00:00', Alaress_Date_Format::DATABASE_FORMAT);
        $date->modify('+1 year');
        $this->assertEquals('2016-03-25 00:00:00', $date->getDatabase());
    }

    public function testPrevious()
    {
        $date = self::buildDateObject('2015-03-25 00:00:00', Alaress_Date_Format::DATABASE_FORMAT);
        $date->previous(Alaress_Date_Format::MONDAY);
        $this->assertEquals('2015-03-23 00:00:00', $date->getDatabase());
    }

    public function testNext()
    {
        $date = self::buildDateObject('2015-03-25 00:00:00', Alaress_Date_Format::DATABASE_FORMAT);
        $date->next(Alaress_Date_Format::MONDAY);
        $this->assertEquals('2015-03-30 00:00:00', $date->getDatabase());
    }
}

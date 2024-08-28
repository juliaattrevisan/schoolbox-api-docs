<?php

/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use Schoolbox\Column\Time;
use DateTime as DateObject;
use Alaress_Date_Format as DateWrapper;

class TimeTest extends DatetimeTest
{
    /**
    * @var Time
    */
    protected $object;

    /**
     * @inheritdoc
     */
    public function setupObject()
    {
        $this->object = new Time();
    }

    /**
     * @inheritdoc
     */
    public static function getTestHydrate()
    {
        return array(
            array(null, false),
            array('2012-01-01 01:40:23', false),
            array('2012-01-01',          false),
            array('0000-01-01 01:40:23', false),
            array('9999-01-01 01:40:23', false),
            array('01:40:23', true),
            array('00:00:00', true),
            array('23:59:59', true),
            //
            // Shouldn't parse, but does. (actually wraps over)
            array('20:50:60', true),
            array('20:60:50', true),
            array('24:30:50', true),
        );
    }

    /**
     * @inheritdoc
     */
    public static function getTestDehydrate()
    {
        return array(
            array(null, null),
            array(false, null),
            array(DateObject::createFromFormat('!Y-m-d H:i:s', '1970-01-01 00:00:00'), '00:00:00'),
            array(DateObject::createFromFormat('!Y-m-d H:i:s', '9999-08-11 23:59:58'), '23:59:58'),
            //
            // DST switch
            array(DateObject::createFromFormat('!Y-m-d H:i:s', '2012-10-07 01:30:00'), '01:30:00'),
            // 02:30 in local time is skipped over completely on switch to DST.
            // it's impossible to get a valid time without a time zone.
            array(DateObject::createFromFormat('!Y-m-d H:i:sT', '2012-10-07 02:30:00+10:00'), '02:30:00'),
            array(DateObject::createFromFormat('!Y-m-d H:i:sT', '2012-10-07 02:30:00+11:00'), '02:30:00'),
            array(DateObject::createFromFormat('!Y-m-d H:i:s', '2013-04-07 01:30:00'), '01:30:00'),
            array(DateObject::createFromFormat('!Y-m-d H:i:s', '2013-04-07 02:30:00'), '02:30:00'),

            array(new DateWrapper('1970-01-01 00:00:00', '!Y-m-d H:i:s'), '00:00:00'),
            // Again, 02:30 in local time doesn't exist
            array(new DateWrapper('2012-10-07 02:30:00+10:00', '!Y-m-d H:i:sT'), '02:30:00'),
            array(new DateWrapper('2012-10-07 02:30:00+11:00', '!Y-m-d H:i:sT'), '02:30:00'),

            array('1970-01-01 00:00:00', '00:00:00'),
            array('2012-10-07 01:30:00', '01:30:00'),
            // Again, 02:30 in local time doesn't exist
            array('2012-10-07 02:30:00+10:00', '02:30:00'),
            array('2012-10-07 02:30:00+11:00', '02:30:00'),
        );
    }
}

<?php

/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use Schoolbox\Column\Datetime;
use Datetime as DateObject;
use Alaress_Date_Format as DateWrapper;

class DatetimeTest extends TestCase
{
    /**
     * @var Datetime
     */
    protected $object;

    /**
     * @inheritdoc
     */
    public function setupObject()
    {
        $this->object = new Datetime();
    }

    /**
     * Data provider for $this->testHydrate().
     *
     * Only interested in whether a value hydrates into a DateTime object - not
     * interested in its value (which is obvious from the value passed to
     * hydrate method)
     *
     * @return array
     */
    public static function getTestHydrate()
    {
        return array(
            array(null, false),
            array('2012-01-01 01:40:23', true),
            array('2012-01-01',          false),
            array('0000-01-01 01:40:23', true),
            array('9999-01-01 01:40:23', true),
            array('0000-00-00 00:00:00', false),
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
            array(DateObject::createFromFormat('!Y-m-d', '2012-01-01'), '2012-01-01 00:00:00'),
            array(DateObject::createFromFormat('!Y-m-d\\TH:i:s', '2014-01-01T12:34:56'), '2014-01-01 12:34:56'),
            //
            // Timezone should be ignored for this class, whether or not it's supplied.
            array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT', '2014-01-01T12:34:56+00:00'), '2014-01-01 12:34:56'),
            array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT', '2014-01-01T12:34:56Z'),      '2014-01-01 12:34:56'),
            array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT', '2014-01-01T12:34:56+10:00'), '2014-01-01 12:34:56'),
            array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT', '2014-01-01T12:34:56-10:00'), '2014-01-01 12:34:56'),
            array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT', '2014-01-01T12:34:56EST'), '2014-01-01 12:34:56'),
            array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT', '2014-01-01T12:34:56Australia/Melbourne'), '2014-01-01 12:34:56'),

            array(new DateWrapper('2012-01-01', '!Y-m-d'), '2012-01-01 00:00:00'),
            array(new DateWrapper('2014-01-01T12:34:56EST', '!Y-m-d\\TH:i:sT'), '2014-01-01 12:34:56'),

            array('2012-01-01', '2012-01-01 00:00:00'),
            array('2014-01-01T12:34:56EST', '2014-01-01 12:34:56'),
        );
    }

    /**
     * @param null|string $input
     * @param null|bool $expected_output
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestHydrate')]
    public function testHydrate($input = null, $expected_output = null)
    {
        // Silence errors: date creation warns on invalid but parseable dates
        $hydrated = @$this->object->hydrate($input);
        // Only interested in if this successfully hydrated into a non-null value
        $this->assertSame(
            $expected_output,
            $hydrated instanceof DateWrapper
        );
    }

    /**
     * @param mixed $input
     * @param null|string $expected_output
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestDehydrate')]
    public function testDehydrate($input = null, $expected_output = null)
    {
        //
        // Test these with Alaress_Date_Format objects too.
        $this->assertSame($expected_output, $this->object->dehydrate($input));
    }
}

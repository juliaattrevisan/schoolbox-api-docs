<?php

/**
 * Tests for verifying the isPastDate and isFutureDate methods.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Date\Test;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;

class IsPastAndFutureDateTest extends TestCase
{
    /**
     * Data provider for whether date is in the past or future.
     *
     * @return array
     */
    public static function getTestIsPastAndFutureDateData()
    {
        return [
            [
                // Start of today: neither a past nor future date
                Alaress_Date_Format::now()->startOfDay(),
                false,
                false,
            ],
            [
                // End of today: neither a past nor future date
                Alaress_Date_Format::now()->endOfDay(),
                false,
                false,
            ],
            [
                // Yesterday: a past date
                Alaress_Date_Format::now()->addDays(-1),
                true,
                false,
            ],
            [
                // Tomorrow: a future date
                Alaress_Date_Format::now()->addDays(1),
                false,
                true,
            ],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $date
     * @param bool                $isPast
     * @param bool                $isFuture
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsPastAndFutureDateData')]
    public function testIsPastAndFutureDate(Alaress_Date_Format $date, $isPast, $isFuture)
    {
        $this->assertEquals($isPast, $date->isPastDate());
        $this->assertEquals($isFuture, $date->isFutureDate());
    }
}

<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable;

/**
 * This takes a set of tt_calendar records and figures out if the ttid
 * is still relevant to the day it was imported, or whether it occurred
 * some time in the past and is mostly irrelevant to the world. This
 * is intended to help you skip over creating dead data that nobody will
 * ever want to see again, because tass forces us to eat every timetable
 * that ever existed
 *
 * tt_calendar sets are "described" here
 * https://github.com/TheAlphaSchoolSystemPTYLTD/lms-integration/blob/master/getTimetableCalendar.md
 */
class ValidActiveTtid
{

    /**
     * A set of tt_calendar array records
     *
     * @var array
     */
    private array $ttCalendar;


    /**
     * Constructor
     *
     * @param array $ttCalendar
     */
    public function __construct(array $ttCalendar)
    {
        $this->ttCalendar = $ttCalendar;
    }


    /**
     * Decide if $date is among ttCalendar set
     *
     * If it is, then the tt_calendar set - and therefore, the corresponding
     * ttid - is currently taking place. It's active.
     *
     * Otherwise, the dates are somewhere in the past or somewhere in the future
     * and isn't really useful to anybody at the moment
     *
     * If you provide a date, you could test a past ttid or a future ttid
     * but $date defaults to today's date which I expect to be the main use case
     *
     * @param string|null $date
     * @return bool
     */
    public function isActive(string $date = null): bool
    {

        /* If $ttCalendar is empty, there aren't any records to see
         * This timetable doesn't occur, and is meaningless
         * Exit early - it's not active
         */
        if (!\count($this->ttCalendar)) {
            return false;
        }

        /* Investigate each record in $ttCalendar
         * for something corresponding to $now
         */
        $now = $date ?? date('Y-m-d');

        /* Otherwise, there's records we need to examine
         */
        foreach ($this->ttCalendar as $record) {
            /* If there is a $ttCalendar record equal to $now,
             * then we can exit early - the thing's active and
             * we're in it
             */
            if ($record['VALUE'] === $now) {
                return true;
            }
        }

        /* If $ttCalendar has:
         *    - at least one record < $now, and
         *    - at least one record > $now
         *
         * ...then we can exit early - the thing's active and we don't need
         * to go iterate any further. There's nothing we'll see that can
         * change our minds now
         *
         * But, if there's a gap in $ttCalendar, and $now is in that gap,
         * exit early. I doubt this is going to happen, but I don't trust tass
         * and their scuffed sis
         *
         * Cheaply, you can probably presume $ttCalendar is sorted by date,
         * take the first and last element, compare their values to today
         * (This makes the assumption that ttCalendar sets are contiguous)
         */
        $firstElement = $this->ttCalendar[0];
        $lastElement = $this->ttCalendar[\count($this->ttCalendar) - 1];

        return ($firstElement['VALUE'] < $now && $lastElement['VALUE'] > $now);
    }
}

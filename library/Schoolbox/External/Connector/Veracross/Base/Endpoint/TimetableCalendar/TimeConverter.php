<?php

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar;

/**
 * Addresses a bug in veracross that they're struggling to fix
 *
 * There is an omega bug in Veracross where they send us UTC timestamps
 * but they aren't actually UTC values lol...for example, they send this:
 *
    [start_time] => 2025-09-02T14:30:00Z
    [end_time] => 2025-09-02T15:25:00Z

 * ...but that means it should display at 2:30pm-3:25pm in the timetable.
 * We can glitch around this by simply str_replace T and Z out (?!!!?)
 * until they figure out how to repair their api.
 *
    $startAt = (new \Alaress_Date_Format(
        $classInstance['start_time'],
        \Alaress_Date_Format::FORMAT_DATETIME_UTC_NO_MLS,
        new \DateTimeZone('UTC')
    ))->setTimezone($this->timezone);

    $finishAt = (new \Alaress_Date_Format(
        $classInstance['end_time'],
        \Alaress_Date_Format::FORMAT_DATETIME_UTC_NO_MLS,
        new \DateTimeZone('UTC')
    ))->setTimezone($this->timezone);
 *
 * Sometimes (sometimes...) veracross has data like this, so you have to butcher it some more:
 *
    [date] => 2025-10-13
    [start_time] => 1900-01-01T14:10:00Z
    [end_time] => 1900-01-01T14:50:00Z
 */
class TimeConverter
{
    /**
     * A timestamp containing this value means we just need to extract the time
     * and append it to the $date string
     */
    private const DATELESS = '1900';


    /**
     * Convert a broken Veracross timestamp into something sensible
     *
     * @param string|null $datetime
     * @param string $date
     * @return \Alaress_Date_Format
     */
    public function convert(?string $datetime, string $date): \Alaress_Date_Format
    {
        /* Note that class instances sometimes won't have a start_time or an end_time
         * I suppose this was meant to be an all-day event or broken data
         */
        $temp = $datetime ?? sprintf(
            '%s %s',
            $date,
            '00:00:00',
        );

        /* Sometimes (sometimes...) veracross has data like this, so you have to butcher it some more:
         *
            [date] => 2025-10-13
            [start_time] => 1900-01-01T14:10:00Z
            [end_time] => 1900-01-01T14:50:00Z
         */
        if (str_contains($temp, self::DATELESS)) {
            $bits = explode('T', $temp);

            $temp = sprintf(
                '%s %s',
                $date,
                $bits[1]
            );
        }

        return (new \Alaress_Date_Format(
            trim(str_replace(['T', 'Z'], ' ', $temp)),
            \Alaress_Date_Format::DATABASE_DATE_FORMAT,
        ));
    }
}

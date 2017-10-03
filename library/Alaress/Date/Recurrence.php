<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Date;

use DateTime;
use DateInterval;
use Schoolbox\Mapper\Exception as MapperException;

class Recurrence
{
    /**
     * Creates a DatePeriod or other traversable list of DateTime objects that
     * corresponds to a list of event recurrences.
     *
     * @param DateTime $startTime      The event that is to recur.
     * @param string   $recurrenceRule The recurrence rule:
     *                                 "daily", "weekly", "fortnightly",
     *                                 "monthly" or "timetable".
     *                                 Anything else is treated as a single
     *                                 event.
     * @param DateTime $repeatUntil    The day to expand this event to.
     * @param int      $campus         The campus to use when determining when a
     *                                 timetable day recurs.
     *
     * @return DateTime[]
     */
    public function getRecurrencePeriod(
        DateTime $startTime,
        $recurrenceRule,
        DateTime $repeatUntil,
        $campus
    ) {
        switch ($recurrenceRule) {
            case 'daily':
                $period = new \DatePeriod(
                    $startTime,
                    DateInterval::createFromDateString('+1 day'),
                    $repeatUntil->modify('+1 day')
                );
                break;
            case 'weekly':
                $period = new \DatePeriod(
                    $startTime,
                    DateInterval::createFromDateString('+1 week'),
                    $repeatUntil->modify('+1 day')
                );
                break;
            case 'fortnightly':
                $period = new \DatePeriod(
                    $startTime,
                    DateInterval::createFromDateString('+2 weeks'),
                    $repeatUntil->modify('+1 day')
                );
                break;
            case 'monthly':
                $period = new \DatePeriod(
                    $startTime,
                    DateInterval::createFromDateString('+1 month'),
                    $repeatUntil->modify('+1 day')
                );
                break;
            case 'timetable':
                try {
                    /** @var Campus $campus */
                    $campus = $this->getCampusMapper()->findById(
                        $this->getRequest()->post['timetable']['campus']
                    );
                    $period = new \External_TimetableDatePeriod(
                        $startTime,
                        $repeatUntil->modify('+1 day'),
                        $this->getExternalTimetableHandler(),
                        $campus
                    );
                } catch (MapperException $e) {
                    $period = [];
                }
                break;
            default:
                $period = [];
                break;
        }

        return $period;
    }

}
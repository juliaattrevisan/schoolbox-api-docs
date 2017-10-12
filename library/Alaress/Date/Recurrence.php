<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Date;

use DatePeriod;
use DateTime;
use DateInterval;
use External_TimetableDatePeriod;
use InvalidArgumentException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Campus;
use External_Timetable;

class Recurrence
{

    /**
     * @var External_Timetable
     */
    protected $externalTimetableHandler;


    /**
     * @param External_Timetable $externalTimetableHandler
     */
    public function __construct(External_Timetable $externalTimetableHandler)
    {
        $this->setExternalTimetableHandler($externalTimetableHandler);
    }

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
     * @param Campus      $campus      The campus to use when determining when a
     *                                 timetable day recurs.
     * @throws InvalidArgumentException
     * @return DateTime[]
     */
    public function getRecurrencePeriod(
        DateTime $startTime,
        $recurrenceRule,
        DateTime $repeatUntil,
        $campus = null
    ) {
        switch ($recurrenceRule) {
            case 'daily':
                $period = new DatePeriod(
                    $startTime,
                    DateInterval::createFromDateString('+1 day'),
                    $repeatUntil->modify('+1 day')
                );
                break;
            case 'weekly':
                $period = new DatePeriod(
                    $startTime,
                    DateInterval::createFromDateString('+1 week'),
                    $repeatUntil->modify('+1 day')
                );
                break;
            case 'fortnightly':
                $period = new DatePeriod(
                    $startTime,
                    DateInterval::createFromDateString('+2 weeks'),
                    $repeatUntil->modify('+1 day')
                );
                break;
            case 'monthly':
                $period = new DatePeriod(
                    $startTime,
                    DateInterval::createFromDateString('+1 month'),
                    $repeatUntil->modify('+1 day')
                );
                break;
            case 'timetable':
                try {
                    $period = new External_TimetableDatePeriod(
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

    /**
     * @param  External_Timetable $externalTimetableHandler
     *
     * @return $this
     */
    public function setExternalTimetableHandler($externalTimetableHandler)
    {
        $this->externalTimetableHandler = $externalTimetableHandler;

        return $this;
    }

    /**
     * @return External_Timetable
     */
    public function getExternalTimetableHandler()
    {
        return $this->externalTimetableHandler;
    }
}

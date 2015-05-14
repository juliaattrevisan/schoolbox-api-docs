<?php

/**
 * External Timetable Connection Interface.  Interface for timetable data to be fetched from external database systems
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Redis;

class External_Timetable implements External_TimetableInterface
{
    use External_CacheTrait;

    private $database;

    /**
     * Constructor
     *
     * @throws Exception
     * @param String $databaseName
     */
    public function __construct($databaseName = '')
    {
        $factory = new External_DatabaseFactory();
        $this->setDatabase($factory->buildTimetableConnector($databaseName));
        $this->setCache(Redis::getInstance());
        $this->setTimeout(Config::get('external_cache_timeout'));
    }

    /**
     * Returns the period definition for the schools timetable, can be optionally provided a day to fetch a specific
     * days definition.
     * @param string $externalUserId
     * @param \Schoolbox\Campus $campus
     * @param Integer $day
     * @return Array
     */
    function getTimetableDefinition($externalUserId, \Schoolbox\Campus $campus, $day = 0)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * Calculates the current day number in the cycle
     *
     * @param int|\Schoolbox\Campus $campus
     * @param String|Integer $date
     * @return Integer
     */
    function getDayNumber(\Schoolbox\Campus $campus, $date = 0)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * Returns the name of the current day
     *
     * @param $dayNumber
     * @return String
     *
     */
    function getDayName($dayNumber) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * Determines the period number
     *
     * @param int|\Schoolbox\Campus $campus
     * @param Integer $now
     * @return Integer
     */
    function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * Gets the next (including today) or previous (not including today) date
     * that a timetable day number occurs on.
     *
     * @todo this should be moved to a 5.4 trait, and used by External_*_Timetable
     *
     * @param \Schoolbox\Campus $campus The campus ID to get the date for.
     * @param int $day The day number to get the date for.
     * @param int $date A Unix timestamp representing the date to get, or
     *                       today if not set.
     * @param bool $previous True if previous date is to be found, instead of
     *                       next date.
     *
     * @throws InvalidArgumentException if $date or $campusId are invalid
     * @throws RangeException           if $day isn't found in ±365 days of $date
     *
     * @return DateTime
     */
    protected function getDateForDayNumber($campus, $day, $date = null, $previous = false)
    {
        if (!isset($date)) {
            $date = time();
        }
        $thisDate = new DateTime();
        if (!$thisDate->setTimestamp($date)) {
            throw new InvalidArgumentException('Invalid format for date');
        }

        // Checking previous occurence? Start one day before
        if ($previous) {
            $thisDate->modify('-1 day');
        }

        $isFound = false;
        if ($day > 0) {
            // Start at today, and get number of days
            // Try for a year, that's long enough.
            $i = 0;
            while ($i++ < 365) {
                $thisDayNumber = $this->getDayNumber($campus, $thisDate->getTimestamp());
                if ($thisDayNumber === false) {
                    throw new InvalidArgumentException('Invalid campus requested');
                }
                if ($thisDayNumber == $day) {
                    $isFound = true;
                    break;
                }
                $thisDate->modify($previous ? '-1 day' : '+1 day');
            }
        }
        if (!$isFound) {
            throw new RangeException('No date found for requested timetable day');
        }
        return $thisDate;
    }

    /**
     * @see $this->getDateForDayNumber
     */
    public function getPreviousDateForDayNumber($campus, $day, $date = null)
    {
        return $this->getDateForDayNumber($campus, $day, $date, true);
    }

    /**
     * @see $this->getDateForDayNumber
     */
    public function getNextDateForDayNumber($campus, $day, $date = null)
    {
        return $this->getDateForDayNumber($campus, $day, $date, false);
    }

    /**
     * Returns a user's complete timetable
     *
     * The structure of this data is as follows
     * Array[]['periodName']
     * Array[]['periodStart']
     * Array[]['periodEnd']
     * Array[]['periodNumber']
     * Array[]['periodSequence']
     * Array[]['day'][][]['description']
     * Array[]['day'][][]['code']
     * Array[]['day'][][]['campus']
     * Array[]['day'][][]['description']
     *
     * @param Integer $externalUserId
     * @param Integer $userId
     * @param int|\Schoolbox\Campus $campus
     * @return Array
     */
    function getFullTimetable($externalUserId, $userId, \Schoolbox\Campus $campus)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * Returns a user's timetable for a particular day,
     * this can probably be retired and superseeded by the date version
     * @deprecated
     *
     *
     * The structure of this data is as follows
     * Array[]['periodName']
     * Array[]['periodStart']
     * Array[]['periodEnd']
     * Array[]['periodNumber']
     * Array[]['periodSequence']
     * Array[]['class'][]['description']
     * Array[]['class'][]['code']
     * Array[]['class'][]['room']
     * Array[]['class'][]['campus']
     * Array[]['class'][]['staffID']
     *
     * Note that you can have multiple classes per period.
     *
     *
     * @param Integer $externalUserId
     * @param Integer $userId
     * @param int|\Schoolbox\Campus $campus
     * @param Integer $dayNumber
     * @return Array
     */
    function getDayTimetable($externalUserId, $userId, \Schoolbox\Campus $campus, $dayNumber)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    /**
     * Returns a user's timetable for a particular date
     *
     * The structure of this data is as follows
     * Array[]['periodName']
     * Array[]['periodStart']
     * Array[]['periodEnd']
     * Array[]['periodNumber']
     * Array[]['periodSequence']
     * Array[]['class'][]['description']
     * Array[]['class'][]['code']
     * Array[]['class'][]['room']
     * Array[]['class'][]['campus']
     * Array[]['class'][]['staffID']
     *
     * Note that you can have multiple classes per period.
     *
     *
     * @param Integer $externalUserId
     * @param Integer $userId
     * @param int|\Schoolbox\Campus $campus
     * @param Alaress_Date_Format $date
     * @return Array
     */
    function getTimetableForDate($externalUserId, $userId, \Schoolbox\Campus $campus, Alaress_Date_Format $date)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }


    /**
     * Gets the timetable for the given room.
     *
     * @param  string $roomCode
     * @return array
     */
    public function getRoomTimetable($roomCode)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    public function __call($methodName, $argumentList)
    {
        $database = $this->getDatabase();

        $methodReflection = new ReflectionMethod($database, $methodName);

        return $methodReflection->invokeArgs($database, $argumentList);
    }

    /**
     * @param External_TimetableInterface $database
     * @return void
     */
    private function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return External_TimetableInterface
     */
    private function getDatabase()
    {
        return $this->database;
    }

}

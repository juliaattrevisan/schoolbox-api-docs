<?php

/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Redis;
use External\CachingConnectorAbstract;
use Schoolbox\Campus;
use Schoolbox\Config\Loader;

/**
 * Class External_Timetable
 *
 * External Timetable Connection Interface.
 * Interface for timetable data to be fetched from external database systems
 */
class External_Timetable extends CachingConnectorAbstract implements External_TimetableInterface
{

    /**
     * Constructor
     *
     * @param String $databaseName
     * @throws InvalidArgumentException
     * @throws LogicException If unable to build connector
     */
    public function __construct()
    {
        // TODO inject dependencies
        parent::__construct(
            (new External_DatabaseFactory())->buildTimetableConnector(Loader::getInstance()),
            new External_Offline_Timetable(),
            Redis::getInstance(),
            Config::get('external_cache_timeout')
        );
    }


    /**
     * @inheritDoc
     */
    public function getTimetableDefinition(?string $externalUserId, Campus $campus, $day = 0, $date = null): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritDoc
     */
    public function getDayNumber(Campus $campus, $date = 0)
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritDoc
     */
    public function getDayName($dayNumber): string
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritDoc
     */
    public function getPeriodNumber(Campus $campus, $now = 0, \Schoolbox\User $user = null): array
    {
        // Period number is more subject to change than most information:
        // set max cache time to 1 minute.
        $timeout = $this->getTimeout();
        if (isset($timeout)) {
            $timeout = min(60, $timeout);
        }
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $timeout);
    }


    /**
     * Gets the next (including today) or previous (not including today) date
     * that a timetable day number occurs on.
     *
     * @todo this should be moved to a 5.4 trait, and used by External_*_Timetable
     *
     * @param Campus $campus The campus ID to get the date for.
     * @param int $day The day number to get the date for.
     * @param int $date A Unix timestamp representing the date to get, or today if not set.
     * @param bool $previous True if previous date is to be found, instead of next date.
     *
     * @throws InvalidArgumentException if $date or $campusId are invalid
     * @throws RangeException           if $day isn't found in ±365 days of $date
     *
     * @return DateTime
     */
    protected function getDateForDayNumber($campus, $day, $date = null, $previous = false): DateTime
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
     * @param Campus $campus
     * @param int $day
     * @param null $date
     * @return DateTime
     * @throws InvalidArgumentException
     * @throws RangeException
     */
    public function getPreviousDateForDayNumber($campus, $day, $date = null)
    {
        return $this->getDateForDayNumber($campus, $day, $date, true);
    }


    /**
     * @see $this->getDateForDayNumber
     * @param Campus $campus
     * @param int $day
     * @param null $date
     * @return DateTime
     * @throws InvalidArgumentException
     * @throws RangeException
     */
    public function getNextDateForDayNumber($campus, $day, $date = null)
    {
        return $this->getDateForDayNumber($campus, $day, $date, false);
    }


    /**
     * @inheritDoc
     */
    public function getFullTimetable($externalUserId, $userId, Campus $campus): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * {@inheritdoc}
     */
    public function getDayTimetable(string $externalUserId, $userId, Campus $campus, $dayNumber, ?Alaress_Date_Format $date = null): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritdoc
     */
    public function getTimetableForDate(string $externalUserId, $userId, Campus $campus, Alaress_Date_Format $date): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritDoc
     */
    public function getRoomTimetable(array $roomCodes): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }


    /**
     * @inheritDoc
     */
    public function findCycleLookup(): array
    {
        return $this->getCachedResult(__FUNCTION__, func_get_args());
    }
}

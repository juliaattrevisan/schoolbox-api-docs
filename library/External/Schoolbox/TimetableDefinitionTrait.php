<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Schoolbox\Campus;

trait External_Schoolbox_TimetableDefinitionTrait
{

    /**
     * @param IConnector $database
     * @param Campus     $campus
     * @param int        $date
     * @return bool|int
     * @throws InvalidArgumentException
     * @throws \Alaress\Database\Exception
     */
    public function getDayNumberFromDatabase(IConnector $database, Campus $campus, $date = 0)
    {
        if (!$definition = $this->getTimetableDefinitionFromDatabase($database, $campus)) {
            return false;
        }

        if (empty($date)) {
            $date = time();
        }

        $dateObject = new Alaress_Date_Format($date, Alaress_Date_Format::TIMESTAMP);
        $dateObject = $dateObject->startOfDay();

        if ($dateObject->isWeekend()) {
            return 0;
        }

        // Don't show day numbers for dates outside of the current timetable def
        // This means that no timetable days or classes will show outside that def
        $databaseDate = $dateObject->getDatabaseDate();
        if ($databaseDate < $definition['start']) {
            return 0;
        }
        if ($databaseDate > $definition['finish']) {
            return 0;
        }


        $startObject = new Alaress_Date_Format($definition['start'], Alaress_Date_Format::DATABASE_DATE_FORMAT);

        $startObject = $startObject->startOfDay();
        $weekdays    = $startObject->diffInWeekdays($dateObject);
        $currentDay  = (($definition['start_day'] + $weekdays) % $definition['cycle_length']);

        if ($currentDay === 0) {
            $currentDay = $definition['cycle_length'];
        }

        return $currentDay;
    }


    /**
     * Find the timetable cycle length and day length for a given campus
     *
     * @param IConnector $database
     * @param Campus     $campus
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function getTimetableDefinitionFromDatabase(IConnector $database, Campus $campus): array
    {
        $timetableQuery = $database->executeQuery(
            '
                SELECT
                    start,
                    finish,
                    start_day,
                    title,
                    cycle_length,
                    day_length
                FROM sis_timetable_cycle
                WHERE cycle_identifier = :campusId
            ',
            ['campusId' => $campus->getExtTimetableCode()]
        );

        if ($definition = $timetableQuery->fetch()) {
            return $definition;
        }

        return [];
    }
}

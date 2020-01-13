<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Schoolbox\Campus as Campus;

trait External_Schoolbox_TimetableDefinitionTrait
{
    /**
     * {@inheritDoc}
     * @param IConnector $database
     * @param Campus     $campus
     * @param int        $date
     *
     * @return bool|int
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

        if (empty($definition['reset']) || $definition['reset'] === '0000-00-00') {
            $startObject = new Alaress_Date_Format($definition['start'], Alaress_Date_Format::DATABASE_DATE_FORMAT);
        } else {
            $startObject = new Alaress_Date_Format($definition['reset'], Alaress_Date_Format::DATABASE_DATE_FORMAT);
        }

        $startObject = $startObject->startOfDay();
        $weekdays    = $startObject->diffInWeekdays($dateObject);
        $currentDay  = (($definition['start_day'] + $weekdays) % $definition['cycle_length']);

        if ($currentDay === 0) {
            $currentDay = $definition['cycle_length'];
        }

        return $currentDay;
    }

    /**
     * {@inheritDoc}
     * @param IConnector $database
     * @param Campus     $campus
     *
     * @return array
     */
    public function getTimetableDefinitionFromDatabase(IConnector $database, \Schoolbox\Campus $campus): array
    {
        $timetableQuery = $database->executeQuery(
            "
                SELECT    tc.start,
                          tc.reset,
                          tc.finish,
                          tc.start_day,
                          ty.title,
                          ty.cycle_length,
                          ty.day_length,
                          ty.regular
                FROM      sis_timetable_campus tc
                LEFT JOIN sis_timetable_cycle ty ON ty.id = tc.cycle_id
                WHERE     tc.campus_id = :campusId
                AND       tc.start <= DATE(NOW())
                AND       tc.finish >= DATE(NOW())
            ",
            ['campusId' => $campus->getId()]
        );

        if ($definition = $timetableQuery->fetch()) {
            $definition['dayCount']    = $definition['cycle_length'];
            $definition['periodCount'] = $definition['day_length'];

            return $definition;
        }

        return [];
    }
}

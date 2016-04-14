<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * Trait that covers retrieval of timetable definition and day number from
 * Schoolbox's internal database.
 */
trait External_Schoolbox_TimetableDefinitionTrait
{

    /**
     * @param Database         $database
     * @param Schoolbox\Campus $campus
     *
     * @return bool|mixed
     */
    public function getTimetableDefinitionFromDatabase(Database $database, \Schoolbox\Campus $campus)
    {
        $statement = $database->prepare("
            SELECT 
                tc.start, 
                tc.reset, 
                tc.finish, 
                tc.start_day,
                ty.title, 
                ty.cycle_length, 
                ty.day_length, 
                ty.regular
            FROM timetable_campus tc
            LEFT JOIN timetable_cycle ty ON ty.id = tc.cycle_id
            WHERE tc.campus_id = :campusId
            AND tc.start < NOW()
            AND tc.finish > NOW()
        ");

        $statement->execute(array(':campusId' => $campus->getId()));

        if ($definition = $statement->fetch(PDO::FETCH_ASSOC)) {
            /* Adapt to spec */
            $definition['dayCount']    = $definition['cycle_length'];
            $definition['periodCount'] = $definition['day_length'];

            return $definition;
        } else {
            return false;
        }
    }

    /**
     * @param Database         $database
     * @param Schoolbox\Campus $campus
     * @param int              $date
     *
     * @return bool|int
     */
    public function getDayNumberFromDatabase(Database $database, \Schoolbox\Campus $campus, $date = 0)
    {
        if (!$definition = $this->getTimetableDefinitionFromDatabase($database, $campus)) {
            return false;
        }

        if (empty($date)) {
            $date = time();
        }
        $dateObject = new Alaress_Date_Format($date, Alaress_Date_Format::TIMESTAMP);
        $dateObject = $dateObject->startOfDay();

        // Is this a Saturday or Sunday?
        // I assume because we don't count non business days, then this is
        // outside the timetable.
        if ($dateObject->isWeekend()) {
            return 0;
        }

        if (empty($definition['reset']) || $definition['reset'] == '0000-00-00') {
            $startObject = new Alaress_Date_Format($definition['start'], Alaress_Date_Format::DATABASE_DATE_FORMAT);
        } else {
            $startObject = new Alaress_Date_Format($definition['reset'], Alaress_Date_Format::DATABASE_DATE_FORMAT);
        }
        $startObject = $startObject->startOfDay();
        $weekdays    = $startObject->diffInWeekdays($dateObject);
        $currentDay  = (($definition['start_day'] + $weekdays) % $definition['cycle_length']);

        // Last day of cycle? Show last day, instead of 0
        if ($currentDay == 0) {
            $currentDay = $definition['cycle_length'];
        }

        return $currentDay;
    }
}

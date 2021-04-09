<?php

use External\IConnector;
use Schoolbox\Database\Factory;

class External_Doublefirst_Helper
{
    /**
     * An instance of MSSQL
     * @var IConnector
     */
    private $database;

    /**
     * Cache for current year
     * @var int
     */
    private $currentYear = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->database = Factory::getInstanceExternal();
    }

    /**
     * Returns array of student boarding statuses
     * @return array
     */
    public function getBoardingStatuses() {
        return ['B', 'FB', 'WB'];
    }

    /**
     * Returns the current academic year
     * @return int
     */
    public function getCurrentYear()
    {
        if ($this->currentYear == null) {
            $this->currentYear = $this->getDatabase()->getOne("SELECT CurrentAcademicYear FROM ClusterMaster");
        }

        return $this->currentYear;
    }


    /**
     * Return a time string given a value that is minutes from midnight
     * @param int $minutes Minutes from midnight
     * @return string
     */
    public function getTimeFromMinutes($minutes)
    {
        return (floor($minutes / 60)) . ":" . ($minutes % 60 < 10 ? '0' . $minutes % 60 : $minutes % 60);
    }

    /**
     * This is a function that works out the current school day number. DoubleFirst does not seem to provide any stored
     * procedure like Synergetic to work out the current day number so we need to calculate this ourselves. We do this
     * by working out what term we are in (using 'SchoolTerms' table), we then have to assume that the Monday of the
     * week in which term starts is Day 1 (because there is nothing in DoubleFirst that actually says which day the term
     * starts on). We then loop through all of the days from the start of term until the specified date incrementing a
     * day number counter.
     *
     * Every sunday we increment a week counter. If the week counter is greater than the number of schools timetable
     * weeks ('TimetableWeekLength' from the 'TimetableParameters' table) then we set the day number back to 0 and start
     * counting again.
     *
     * To make looping a bit easier we use julian dates, this is the calendar date as an integer
     * (eg. 28 July 2016 = 16210, 29 July 2016 = 16211, 30 July 2016 = 16212, 31 July 2016 = 16213, 1 August 2016 = 16214)
     *
     * @param \Alaress_Date_Format $date The date we want to get the school day number for
     * @return int
     */
    public function getDayNumber($date)
    {
        // Work out what day of the week the term started on
        if($date->isWeekend()) {
            return 0;
        }

        $query = "
            SELECT     st.StartDate,
                       st.EndDate,
                       ttc.StartTimeTableWeek
            FROM       SchoolTerms st
            INNER JOIN TimeTableTermCalendar ttc ON ttc.TermID = st.TermID and ttc.SchoolID = st.SchoolID
            WHERE      st.StartDate <= :startDate
            AND        st.EndDate   >= :endDate
        ";

        $term = $this->getDatabase()->getRow(
            $query,
            [
                'startDate' => $date->endOfDay()->getDatabase(),
                'endDate'   => $date->startOfDay()->getDatabase(),
            ]
        );

        // If we couldn't find a matching term in Doublefirst, just bomb out here.

        if (!$term) {

            // TODO: Remove hack, this just picks the last term that was open for testing purposes during the holidays.
            $query =
                "
                SELECT TOP 1 st.StartDate,
                             st.EndDate,
                             ttc.StartTimeTableWeek
                FROM         SchoolTerms st
                INNER JOIN   TimeTableTermCalendar ttc ON ttc.TermID = st.TermID and ttc.SchoolID = st.SchoolID
                WHERE        st.StartDate <= :startDate
			    ORDER BY     st.StartDate DESC
	        ";

            $term = $this->getDatabase()->getRow(
                $query,
                [
                    'startDate' => $date->endOfDay()->getDatabase(),
                ]
            );
        }

        if (!$term) {
            // if still failing to find a term, just return 0
            return 0;
        }

        $start = new \Alaress_Date_Format(strtotime($term['StartDate']), 'U');

        // Grab the year for use in later query

        $year = $start->getYear();
        $week = (int)$term['StartTimeTableWeek'];
        $day  = date("w", strtotime($term['StartDate']));

        // We convert the date fo julian so we can iterate days easily in a for loop.

        $current = gregoriantojd($date->getMonth(), $date->getDay(), $date->getYear());
        $start = gregoriantojd($start->getMonth(), $start->getDay(), $start->getYear());

        // Work out which day/week we ware in

        $query = "SELECT TimetableWeekLength FROM TimetableParameters WHERE AcademicYear = {$year}";
        $weekLength = $this->getDatabase()->getRow($query);


        for ($i = $start; $i <= $current; $i++) {
            $day++;

            // Roll over weeks each Sunday

            if ($day > 6) {
                $day = 0;

                // Roll over weeks based on information from the database

                $week++;

                if ($week > $weekLength["TimetableWeekLength"]) {
                    $week = 1;
                }
            }
        }

        return (($week - 1) * 5) + $day - 1;
    }

    /* Getters/Setters
     */

    /**
     * @param IConnector $database
     */
    private function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return IConnector
     */
    private function getDatabase()
    {
        return $this->database;
    }
}

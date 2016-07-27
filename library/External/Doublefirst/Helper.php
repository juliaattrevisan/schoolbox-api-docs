<?php

class External_Doublefirst_Helper
{
    /**
     * An instance of MSSQL
     * @var MSSQL
     */
    private $database = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
    }

    /**
     * Returns the current academic year
     * @return int
     */
    public function getCurrentYear()
    {
        return date("Y");
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
     * @param $date
     * @return int
     */
    public function getDayNumber($date)
    {
        // Work out what day of the week the term started on

        $query =
            "
            SELECT      StartDate, 
                        EndDate 
            FROM        SchoolTerms 
            WHERE       StartDate <= '" . $date->getDatabaseDate() . " 23:59:59' AND 
                        EndDate >= '" . $date->getDatabaseDate() . " 00:00:00'
        ";

        $term = $this->getDatabase()->getRow($query);

        // If we couldn't find a matching term in Doublefirst, just bomb out here.

        if (!$term) {

            // TODO: Remove hack, this just picks the last term that was open for testing purposes during the holidays.

            $query =
                "
	            SELECT      TOP 1 StartDate, 
	                        EndDate 
	            FROM        SchoolTerms 
	            WHERE       StartDate <= '" . $date->getDatabaseDate() . " 23:59:59'  
			    ORDER BY 	StartDate DESC
	        ";

            $term = $this->getDatabase()->getRow($query);
        }

        $start = new \Alaress_Date_Format();
        $start->setFromFormat(strtotime($term['StartDate']), 'U');

        // Grab the year for use in later query

        $year = $start->getYear();

        /**
         * TODO: We are going to assume all terms start on week 1 as there doesn't appear to be any field that says it could
         * be otherwise. Need to look into this.
         */

        $week = 1;
        $day = date("w", strtotime($term['StartDate']));

        // We convert the date fo julian so we can iterate days easily in a for loop

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

                if ($week > $weekLength["TimeTableWeekLength"]) {
                    $week = 1;
                }
            }
        }

        return (($week - 1) * 5) + $day;
    }

    /* Getters/Setters
     */

    /**
     * @param MSSQL $database
     */
    private function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return MSSQL
     */
    private function getDatabase()
    {
        return $this->database;
    }
}
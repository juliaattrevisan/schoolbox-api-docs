<?php

/**
 * Helper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class External_Maze_Helper
{

    /**
     * An instance of MSSQL
     *
     * @var null
     */
    private $db = null;

    /**
     * Current semester
     *
     * @var string
     */
    private $currentSemester = '';

    /**
     * Current file year. (Always this year but included for consistency.)
     *
     * @var string
     */
    private $currentYear = '';

    /**
     * Decide whether you're in between semesters
     *
     * @var bool
     */
    private $betweenSemesters = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDb(MSSQL::getInstance());
        $this->setCurrentYear($this->determineCurrentYear());
        $this->setCurrentSemester($this->determineCurrentSemester());
    }

    /**
     * Get the current timetable period eg. 2013S2
     */
    public function getCurrentTHKEY()
    {
        return $this->getCurrentSemester();
    }

    /**
     * As designed by Samuel himself
     *
     * @return bool|string
     */
    public function currentDate()
    {
        /* Samuel wanted it like this.
         * My life, where did it go?
         */
        if (date('d') < 10) {
            return date('M  d Y');
        }

        return date('M d Y');
    }

    /**
     * Take a Maze datetime and returns the time portion of it
     *
     * @param $date
     * @return string
     */
    public function mazeTime($date)
    {
        return ltrim(strtolower(substr($date, 12, 5) . substr($date, -2)), 0);
    }

    /**
     * Get the current year
     *
     * @param null $externalUserId
     * @return string
     */
    public function determineCurrentYear($externalUserId = null)
    {
        return date('Y');
    }

    /**
     * Get the current semester
     *
     * This should take the form below:
     * $row['CURRENT_SEMESTER'] = '1998S4';
     *
     * @param null $externalUserId
     * @return mixed
     */
    public function determineCurrentSemester($externalUserId = null)
    {
        $currentYear = date('Y');

        $query = "
			SELECT THKEY,
			 	CALENDAR_START01,
			 	CALENDAR_START02,
			 	CALENDAR_START03,
			 	CALENDAR_START04,
			 	CALENDAR_END01,
			 	CALENDAR_END02,
			 	CALENDAR_END03,
			 	CALENDAR_END04
			FROM TH
			WHERE THKEY LIKE '{$currentYear}%'
			ORDER BY THKEY DESC
		";

        $allRows = $this->getDb()->getAll($query);

        /* For every semester in the current year, find one which fits
         * the current datetime
         */
        foreach ($allRows as $currentRow) {

            /* Find the earliest start
             */
            $afterSemesterStart = false;

            for ($i = 1; $i < 5; $i++) {
                $key = "CALENDAR_START0{$i}";

                if ($currentRow[$key] != '') {
                    $currentTime = strtotime($currentRow[$key]);

                    if ($currentTime < $_SERVER['REQUEST_TIME']) {
                        $afterSemesterStart = true;
                        break;
                    }
                }
            }

            /* Find the latest end
             */
            $beforeSemesterEnd = false;

            for ($i = 1; $i < 5; $i++) {
                $key = "CALENDAR_END0{$i}";

                if ($currentRow[$key] != '') {
                    $currentTime = strtotime($currentRow[$key]);

                    if ($currentTime >= $_SERVER['REQUEST_TIME']) {
                        $beforeSemesterEnd = true;
                        break;
                    }
                }
            }

            /* You are currently in the most recently added semester definition
             */
            if ($afterSemesterStart && $beforeSemesterEnd) {
                return $currentRow['THKEY'];
            }

            /* Not in this semester yet? Maybe try an older one from the same year
             * Continue with loop
             */
        }

        /* In between semesters
         * Make a guess as to what data to display. At the end of semester 1,
         * start trying to display semester 2 data. At the end of semester 2,
         * start trying to display semester 1 data from the next year.
         */
        $this->setBetweenSemesters(true);

        /* If January and between semesters 2 and 1, assume it's semester 1
         * Otherwise assume between semester 1 and 2, and assume it's semester 2
         */
        if (date('n') < 6) {
            return date('Y') . 'S1';
        }
        else {
            return date('Y') . 'S2';
        }
    }

    /**
     * Glue a class code and class number together into a unique
     * Schoolbox ClassCode
     *
     * @param $classCode
     * @param $classNumber
     * @return string
     */
    public function generateClassCode($classCode, $classNumber)
    {
        return $classCode . '-' . $classNumber;
    }

    /**
     * Deglue a unique Schoolbox ClassCode into its original constituents
     *
     * @param $schoolboxClassCode
     * @return array
     */
    public function degenerateClassCode($schoolboxClassCode)
    {
        list($classCode, $classNumber) = explode('-', $schoolboxClassCode);

        return array(
            'ClassCode'   => $classCode,
            'ClassNumber' => $classNumber,
        );
    }

    /**
     * Alias
     * 
     * @return bool
     */
    public function isBetweenSemesters() {
        return $this->getBetweenSemesters();
    }

    /* Getters/Setters
     */

    /**
     * Set db
     *
     * @param null $db
     */
    private function setDb($db)
    {
        $this->db = $db;
    }

    /**
     * Get db
     *
     * @return null
     */
    private function getDb()
    {
        return $this->db;
    }

    /**
     * Set current semester
     *
     * @param string $currentSemester
     */
    private function setCurrentSemester($currentSemester)
    {
        $this->currentSemester = $currentSemester;
    }

    /**
     * Get current semester
     *
     * @return string
     */
    public function getCurrentSemester()
    {
        return $this->currentSemester;
    }

    /**
     * @return string
     */
    public function getCurrentYear()
    {
        return $this->currentYear;
    }

    /**
     * @param string $currentYear
     */
    private function setCurrentYear($currentYear)
    {
        $this->currentYear = $currentYear;
    }

    /**
     * Set between semesters
     *
     * @param boolean $betweenSemesters
     */
    private function setBetweenSemesters($betweenSemesters)
    {
        $this->betweenSemesters = $betweenSemesters;
    }

    /**
     * Get between semesters
     *
     * @return boolean
     */
    private function getBetweenSemesters()
    {
        return $this->betweenSemesters;
    }
}
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
     * Get the current timetable key eg. 2013S2
     */
    public function getCurrentTHKEY()
    {
        return $this->getCurrentSemester();
    }

    /**
     * Get the current timetable period eg. 2013S2
     */
    public function getCurrentTTPERIOD()
    {
        $query = "SELECT TOP 1 TT.TTPERIOD
                  FROM TT
                      JOIN TH ON
                          TT.TTKEY = TH.TT01KEY OR
                          TT.TTKEY = TH.TT02KEY OR
                          TT.TTKEY = TH.TT03KEY OR
                          TT.TTKEY = TH.TT04KEY OR
                          TT.TTKEY = TH.TT05KEY OR
                          TT.TTKEY = TH.TT06KEY OR
                          TT.TTKEY = TH.TT07KEY OR
                          TT.TTKEY = TH.TT08KEY OR
                          TT.TTKEY = TH.TT09KEY OR
                          TT.TTKEY = TH.TT10KEY OR
                          TT.TTKEY = TH.TT11KEY OR
                          TT.TTKEY = TH.TT12KEY OR
                          TT.TTKEY = TH.TT13KEY
                  WHERE TH.THKEY = '{$this->getCurrentTHKEY()}'";
        return $this->getDb()->getOne($query);
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
        $query = "
			SELECT TOP 1 THKEY
			FROM TH
			WHERE CAST(TH.CALENDAR_END01 AS DATE) >= CAST(GETDATE() AS DATE)
				OR CAST(TH.CALENDAR_END02 AS DATE) >= CAST(GETDATE() AS DATE)
				OR CAST(TH.CALENDAR_END03 AS DATE) >= CAST(GETDATE() AS DATE)
				OR CAST(TH.CALENDAR_END04 AS DATE) >= CAST(GETDATE() AS DATE)
			ORDER BY THKEY DESC
		";

        return $this->getDb()->getOne($query);
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

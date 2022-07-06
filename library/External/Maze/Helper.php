<?php

use Alaress\Database\Connector\IConnector;
use Schoolbox\Database\Factory;

/**
 * Helper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/

 */
class External_Maze_Helper
{
    /**
     * An instance of MSSQL
     *
     * @var IConnector
     */
    private $db;

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
     * Constructor
     */
    public function __construct()
    {
        $this->db = Factory::getInstanceExternal();
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
        return $this->db->getOne($query);
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
			ORDER BY TH.CALENDAR_END01,TH.CALENDAR_END02,TH.CALENDAR_END03,TH.CALENDAR_END04 ASC
		";

        $value = $this->db->getOne($query);
        if ($value === false) {
            return '';
        }
        return $value;
    }

    /**
     * Glue a class code and class number together into a unique
     * Schoolbox ClassCode
     *
     * @param string $classCode
     * @param int $classNumber
     * @return string
     */
    public function generateClassCode($classCode, $classNumber)
    {
        return $classCode . '-' . $classNumber;
    }

    /**
     * Deglue a unique Schoolbox ClassCode into its original constituents
     *
     * @param string $schoolboxClassCode
     * @return array
     */
    public function degenerateClassCode($schoolboxClassCode)
    {
        $allClassCodes = [];
        $allClassNumbers = [];

        /* Also understand when many classcodes are specified in an array,
         * or just one as a string. Manufacture a unique list of classCodes
         * and classNumbers
         */
        if (strstr($schoolboxClassCode, ',')) {
            foreach (str_getcsv($schoolboxClassCode) as $currentClassCode) {
                $classCodeParts = explode('-', $currentClassCode);

                $allClassCodes[] = "'{$classCodeParts[0]}'";
                // if $currentClassCode does not have "-", just set 0
                // so that $allClassCodes count and $allClassNumbers count will be the same
                $allClassNumbers[] = (int)($classCodeParts[1] ?? 0);
            }
        } else {
            $classCodeParts = explode('-', $schoolboxClassCode);

            $allClassCodes[] = "'{$classCodeParts[0]}'";
            $allClassNumbers[] = (int)($classCodeParts[1] ?? 0);
        }

        return [
            'ClassCode'   => $allClassCodes,
            'ClassNumber' => $allClassNumbers,
        ];
    }

    /* Getters/Setters
     */

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
     * @return null|string
     */
    public function getCurrentSemester(): ?string
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
}

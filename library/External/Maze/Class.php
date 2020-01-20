<?php
/**
 * External_Maze_Class
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/

 */

class External_Maze_Class implements External_ClassInterface {

    /**
     * An instance of MSSQL
     *
     * @var MSSQL
     */
    protected $db;

    /**
     * @var External_Maze_Helper
     */
    protected $helper;

    /**
     * Constructor
     */
    public function __construct() {
        $this->setDb(MSSQL::getInstance());
        $this->setHelper(new External_Maze_Helper());
    }

    /**
     * @inheritdoc
     */
    public function getStudents(string $classCode): array
    {
        if (!$classCode) {
            /* Dont bother if you got no code */
            return [];
        }

        $ttPeriod = $this->getHelper()->getCurrentTTPERIOD();

        $ingredients = $this->getHelper()->degenerateClassCode($classCode);

        $query = "
            SELECT
                ST.STKEY AS ID,
                ST.FIRST_NAME AS Firstname,
                ST.SURNAME AS Surname,
                REPLACE(REPLACE(SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS YearLevel,
                KGH.DESCRIPTION AS House,
                MEDICAL AS MedicalAlert,
                '' AS SpecialNeedsFlag,
                '' AS EnglishSecondFlag,
                '' AS StudentTutorDescription
            FROM STMA
            LEFT JOIN SU ON STMA.MKEY = SU.SUKEY
            LEFT JOIN ST ON STMA.SKEY = ST.STKEY
            LEFT JOIN KGH ON ST.HOUSE = KGH.HOUSE
            WHERE ";

        /* Accommodate multiple classcodes specifically
         */
        $additional = [];

        for ($i = 0; $i < count($ingredients['ClassCode']); $i++) {
            $additional[] = "(SU.SUKEY = {$ingredients['ClassCode'][$i]} AND STMA.CLASS = {$ingredients['ClassNumber'][$i]})";
        }

        $query .= '(' . implode(' OR ', $additional) . ')';

        /* Continue as normal
         */
        $query .= "
            AND TTPERIOD = '{$ttPeriod}'
            AND ST.STATUS != 'LEFT'
        ";

        $part1 = $this->getDb()->getAll($query);
        $allClassCodes = implode(',', $ingredients['ClassCode']);

        if (!$part1) {
            $part1 = array();
        }

        $query = "
            SELECT
                ST.STKEY AS ID,
                ST.FIRST_NAME AS Firstname,
                ST.SURNAME AS Surname,
                REPLACE(REPLACE(SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS YearLevel,
                KGH.DESCRIPTION AS House,
                MEDICAL AS MedicalAlert,
                '' AS SpecialNeedsFlag,
                '' AS StudentTutorDescription,
                '' AS ClassCampus
            FROM ST
            LEFT JOIN KGC ON ST.ROLL_GROUP = KGC.ROLL_GROUP
            LEFT JOIN KGH ON ST.HOUSE = KGH.HOUSE
            WHERE KGC.ROLL_GROUP IN ({$allClassCodes})
            AND ST.STATUS != 'LEFT'
        ";

        $part2 = $this->getDb()->getAll($query);

        if (!$part2) {
            $part2 = array();
        }

        $allStudents = array_merge($part1, $part2);

        $studentList = array();

        if (is_array($allStudents) && count($allStudents)) {
            foreach($allStudents as $currentStudent) {
                $student = array(
                    'synergy_id' => $currentStudent['ID'],
                    'firstname' => $currentStudent['Firstname'],
                    'lastname' => $currentStudent['Surname'],
                    'yearlevel' => $currentStudent['YearLevel'],
                    'house' => $currentStudent['House'],
                    'StudentMedicalAlert' => $currentStudent['MedicalAlert'],
                    'StudentSpecialNeedsFlag' => $currentStudent['SpecialNeedsFlag'],
                    'StudentTutorDescription' => $currentStudent['StudentTutorDescription'],
                    'ClassCampus' => $currentStudent['ClassCampus']
                );

                $studentList[] = $student;
            }
        }

        return $studentList;
    }


    /**
     * @inheritdoc
     */
    public function getTeachers(string $classCode, Alaress_Date_Format $date = null): array
    {
        if (!$classCode) {
            /* Dont bother if you got no code */
            return [];
        }

        $ttPeriod = $this->getHelper()->getCurrentTTPERIOD();

        /* Assume Schoolbox $classCodes are in the format: '07SCI-1'
         * where the 1 represents the Class which could be
         * class 1, 2, 3 etc. of a particular subject
         */
        $ingredients = $this->getHelper()->degenerateClassCode($classCode);

        $query = "
            SELECT
                T1TEACH,
                T2TEACH,
                '' AS T3TEACH
            FROM TT
            LEFT JOIN TTTG ON TT.TTKEY = TTTG.GKEY
            WHERE TT.TTPERIOD = '{$ttPeriod}' AND ";

        /* Accommodate multiple classcodes specifically
         */
        $additional = [];

        for ($i = 0; $i < count($ingredients['ClassCode']); $i++) {
            $additional[] = "(TTTG.SUBJ = {$ingredients['ClassCode'][$i]} AND CLASS = {$ingredients['ClassNumber'][$i]})";
        }

        $query .= '(' . implode(' OR ', $additional) . ')';

        /* Continue as normal
         */
        $part1 = $this->getDb()->getAll($query);
        $allClassCodes = implode(',', $ingredients['ClassCode']);

        if (!$part1) {
            $part1 = array();
        }

        $query = "
            SELECT
                TEACHER AS T1TEACH,
                '' AS T2TEACH,
                '' AS T3TEACH
            FROM KGC
            WHERE KGC.ROLL_GROUP IN ({$allClassCodes})
            ";

        $part2 = $this->getDb()->getAll($query);

        if (!$part2) {
            $part2 = array();
        }

        $allTeachers = array_merge($part1, $part2);

        /* Uniqueify everything
         */
        $teacherKeys = array();

        if (is_array($allTeachers) && count($allTeachers)) {
            foreach($allTeachers as $currentTeacher) {
                $teacherKeys[$currentTeacher['T1TEACH']] = '';
                $teacherKeys[$currentTeacher['T2TEACH']] = '';
                $teacherKeys[$currentTeacher['T3TEACH']] = '';
            }
        }

        /* Filter out empty values
         */
        $teacherKeys = array_filter(array_keys($teacherKeys));

        /* A list of ids
         */
        return $teacherKeys;
    }

    /* Getters/Setters
     */

    /**
     * Set db
     *
     * @param MSSQL $db
     */
    protected function setDb($db) {
        $this->db = $db;
    }

    /**
     * Get db
     *
     * @return MSSQL
     */
    protected function getDb() {
        return $this->db;
    }

    /**
     * Set helper
     *
     * @param null $helper
     */
    protected function setHelper($helper) {
        $this->helper = $helper;
    }

    /**
     * Get helper
     *
     * @return External_Maze_Helper
     */
    protected function getHelper() {
        return $this->helper;
    }

    /**
     * @return array
     */
    public function findClassTypes(): array
    {
        return [];
    }

    /**
     * @param string $filetype
     * @param array  $timetableCode
     *
     * @return array
     */
    public function findClassesByType(string $filetype, array $timetableCode): array
    {
        return [];
    }
}

<?php
/**
 * PCSchools class connector
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Pcschools_Class implements External_ClassInterface
{
    /**
     * @var MSSQL
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
    }

    /**
     * Get a list of students in a particular $classCode
     * lastname,
     * firstname,
     * yearlevel,
     * StudentHouseDescription as house,
     * StudentMedicalAlert,
     * StudentSpecialNeedsFlag,
     * synergy_id,
     * ClassCampus
     *
     * @param $classCode
     *
     * @return array
     */
    public function getStudents($classCode)
    {
        // Class code format: S.YEAR + SU.ABBREV + S.CLASS
        return $this->getDatabase()->trimAll(
            $this->getDatabase()->getAll("
                SELECT DISTINCT
                    ST.LEGAL_SURNAME AS lastname,
                    ST.GIVEN_NAME AS firstname,
                    ST.CURRENT_YEAR AS yearlevel,
                    ST.HOUSE AS house,
                    ST.ALERT AS StudentMedicalAlert,
                    '' AS StudentSpecialNeedsFlag,
                    ST.STUDKEY AS synergy_id,
                    ST.CAMPUS AS campusCode
                FROM SUB S
                INNER JOIN STUDENT ST ON S.STUDENT# = ST.STUDENT# AND S.YEAR = ST.CURRENT_YEAR
                INNER JOIN [SUBJECT] SU ON S.SUBJECT_NO = SU.NO
                WHERE (S.YEAR + SU.ABBREV + S.CLASS) = {$this->getDatabase()->smartQuote($classCode)}
            ")
        );
    }

    /**
     * Returns a list of *THE IDs OF* teachers that are associated
     * with a class (method is misnamed)
     *
     * @param $classCode
     *
     * @return array
     */
    public function getTeacherName($classCode)
    {
        return array_map(
            function($v) { return $v['TEACHER']; },
            $this->getDatabase()->trimAll(
                $this->getDatabase()->getAll("
                    SELECT DISTINCT S.TEACHER
                    FROM SUB S
                    INNER JOIN [SUBJECT] SU ON S.SUBJECT_NO = SU.NO
                    WHERE (S.YEAR + SU.ABBREV + S.CLASS) = {$this->getDatabase()->smartQuote($classCode)}
                ")
            )
        );
    }

    /**
     * @return MSSQL
     */
    private function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param  MSSQL $database
     *
     * @return $this
     */
    private function setDatabase($database)
    {
        $this->database = $database;
        return $this;
    }
}

<?php

/**
 * DoubleFirst Class Object
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 *
 *         ,--.--._
 * ------" _, \___)
 *       2 / _/____)
 *       x \//(____)
 * ------\     (__)
 *        `-----"
 */
class External_Doublefirst_Class implements External_ClassInterface
{

    /**
     * @var MSSQL $database
     */
    private $database;

    /**
     * @var External_UserInterface
     */
    private $externalUser;

    /**
     * External_Doublefirst_Class constructor.
     */
    function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        $this->externalUser = new External_User('Doublefirst');
    }

    /**
     * Get all student in class
     * @param $subCode
     * @return array|bool
     */
    public function getStudents($subCode)
    {

        $year = date("Y");

        $arrayCodes = explode(",", $subCode);

        foreach ($arrayCodes as $code) {
            $query = "
                SELECT DISTINCT 
                    pbi.Surname as lastname, 
                    pbi.ForeName as firstname, 
                    pbi.YearGroup as yearlevel, 
                    pbi.House as house, 
                    CAST(pbi.UrgentNotes as NVARCHAR(max)) as StudentMedicalAlert, 
                    0 as StudentSpecialNeedsFlag,
                    null AS StudentTutorDescription,
                    pbi.PupilID as synergy_id, 
                    null AS ClassCampus 
                FROM PupilSubjects ps JOIN V_PupilBasicInformation pbi ON pbi.PupilID = ps.PupilID 
                LEFT JOIN YearGroupSubjectDivision ygsd ON ygsd.DivisionID = ps.DivisionID AND ygsd.SubjectID = ps.SubjectID AND ygsd.YearGroup = ps.YearGroup AND ygsd.AcademicYear = ps.SchoolYear 
                JOIN TimetableClassRoom tcr ON tcr.YearGroup = ps.yeargroup AND tcr.SubjectID = ps.SubjectID AND tcr.DivisionID = ps.DivisionID AND tcr.AcademicYear = ps.SchoolYear 
                JOIN TimetableMatrix tm ON tm.MatrixID = tcr.MatrixID
                JOIN TimetableMaster ttm ON ttm.TimetableName = tm.TimetableName AND ttm.AcademicYear = tcr.AcademicYear AND ttm.IsWorking = 1 AND ttm.YearGroup = ps.YearGroup
                WHERE ps.SchoolYear = {$year} AND ygsd.SubjectDivisionName = '{$code}'
            ";

            $rows = $this->getDatabase()->getAll($query);

            if ($rows) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * Gets the class code part of a query.
     *
     * @param  string $subCode
     * @return string
     */
    protected function getClassCodeQuery($subCode)
    {
        $subCodeArray = array_filter(explode(",", $subCode));

        foreach ($subCodeArray as &$code) {
            $code = "'" . $this->database->quote($code) . "'";
        }

        return "ygsd.SubjectDivisionName IN (" . implode($subCodeArray, ',') . ")";
    }

    /**
     * Retrieve teacher external IDs for a particular class (great function naming)
     * @param $subCode
     * @param null $user
     * @return array
     */
    public function getTeacherName($subCode, $user = null)
    {
        $year = date("Y");

        $classQuery = $this->getClassCodeQuery($subCode);

        $query =
            "
            SELECT DISTINCT tcs.StaffID
            FROM PupilSubjects ps 
            JOIN TimetableClassStaff tcs ON tcs.SchoolID = ps.SchoolID AND tcs.YearGroup = ps.YearGroup AND tcs.DivisionID = ps.DivisionID AND tcs.AcademicYear = ps.SchoolYear AND tcs.BatchID = ps.BatchID
            JOIN StaffPersonalDetails spd ON spd.StaffID = tcs.StaffID
            
            LEFT JOIN YearGroupSubjectDivision ygsd ON ygsd.DivisionID = tcs.DivisionID AND ygsd.SubjectID = tcs.SubjectID AND ygsd.YearGroup = tcs.YearGroup AND ygsd.AcademicYear = tcs.AcademicYear
            WHERE ps.SchoolYear = {$year} and $classQuery
        ";

        $rows = $this->getDatabase()->getAll($query);

        if (!$rows) {
            return [];
        }

        $teachers = array();

        foreach ($rows as $row) {
            $teachers[] = $row['StaffID'];
        }

        return $teachers;
    }

    /**
     * @param $database
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


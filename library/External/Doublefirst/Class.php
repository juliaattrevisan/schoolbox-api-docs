<?php

/**
 * DoubleFirst Class Object
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
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
     * @inheritdoc
     */
    public function getStudents(string $classCode): array
    {
        if (!$classCode) {
            /* Dont bother if you got no code */
            return [];
        }

        $year = date("Y");

        $arrayCodes = explode(",", $classCode);

        foreach ($arrayCodes as $code) {
            $query = "
                SELECT DISTINCT
                    pbi.Surname as lastname,
                    pbi.ForeName as firstname,
                    pbi.YearGroup as yearlevel,
                    pbi.House as house,
                    CAST(pbi.UrgentNotes as NVARCHAR(max)) as StudentMedicalAlert,
                    0 as StudentSpecialNeedsFlag,
                    '' as EnglishSecondFlag,
                    null AS StudentTutorDescription,
                    pbi.PupilID as synergy_id
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
     * @inheritdoc
     */
    public function getTeachers(string $classCode, Alaress_Date_Format $date = null): array
    {
        if (!$classCode) {
            /* Dont bother if you got no code */
            return [];
        }

        $year = date("Y");

        $classQuery = $this->getClassCodeQuery($classCode);

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


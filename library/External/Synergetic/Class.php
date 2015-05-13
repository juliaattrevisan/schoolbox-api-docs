<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 *    This file provides access to synergetic class info        *
 *                                                           *
 ************************************************************/

use Alaress\DebugBar\Instance as DebugBar;

class External_Synergetic_Class implements External_ClassInterface
{

    /**
     * @var MSSQL $database
     */
    private $database;

    /**
     * @var External_UserInterface
     */
    private $externalUser;

    function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        $this->externalUser = new External_User('synergetic');
        if (DebugBar::isEnabled()) {
            $this->getDatabase()->installCollector();
            /** @var \DebugBar\DataCollector\DataCollectorInterface $collector */
            $collector = $this->getDatabase()->getCollector();
            if (!DebugBar::getInstance()->hasCollector($collector->getName())) {
                DebugBar::getInstance()->addCollector($collector);
            }
        }
    }

    /**
     * Returns the details of a class based on its code
     * This function is Synergetic only, not included in the interface and used for attendances
     *
     * @param $classCode
     *
     * @return mixed
     */
    public function getClass($classCode)
    {
        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester());
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear());
        $classQuery = '';

        if (strpos($classCode, '/') !== false) {
            $codeArray = explode('/', $classCode);
            $classQuery .= "( ClassCode = '{$codeArray[1]}' AND ClassCampus = '{$codeArray[0]}' ) ";
        } else {
            $classQuery .= "ClassCode = '$classCode'";
        }

        $query = "
            SELECT *
            FROM SynergyOne.dbo.SubjectClasses
            WHERE $classQuery
            AND FileYear='" . $fileYear . "'
            AND FileSemester='" . $fileSemester . "'
        ";

        return $this->getDatabase()->getRow($query);
    }

    public function getStudents($subCode)
    {
        if (!$subCode) {
            /* Dont bother if you got no code */
            return false;
        }

        $classQuery = '(';

        $subCodeArray = explode(',', $subCode);
        foreach ($subCodeArray as $code) {
            if (strpos($code, '/') !== false) {
                $codeArray = explode('/', $code);
                $classQuery .= "( ClassCode = '{$codeArray[1]}' AND ClassCampus = '{$codeArray[0]}' ) OR ";
            } else {
                $classQuery .= "ClassCode = '$code' OR ";
            }
        }

        $classQuery = substr($classQuery, 0, -3) . ')';

        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester());
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear());
        $query = "
        SELECT DISTINCT StudentClasses.StudentSurname as lastname, StudentClasses.StudentPreferred as firstname,
                        StudentClasses.StudentYearLevelDescription as yearlevel,
                        StudentClasses.StudentHouseDescription as house,
                        vStudents.StudentMedicalAlert, vStudents.StudentSpecialNeedsFlag,
                        StudentClasses.StudentID as synergy_id, StudentClasses.ClassCampus
        FROM    SynergyOne.dbo.vStudentClasses StudentClasses
        JOIN SynergyOne.dbo.vStudents vStudents ON (
            vStudents.FileYear = StudentClasses.FileYear AND
            vStudents.FileSemester = StudentClasses.FileSemester AND
            vStudents.ID = StudentClasses.StudentID)
        WHERE $classQuery
            AND (StudentClasses.FileYear='" . $fileYear . "')
            AND (StudentClasses.FileSemester='" . $fileSemester . "')
            AND (StudentClasses.StartDate <= getdate() OR StudentClasses.StartDate IS NULL)
            AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)
        ORDER BY StudentClasses.StudentSurname, StudentClasses.StudentPreferred";

        return $this->getDatabase()->getAll($query);
    }

    public function getTeacherName($subCode)
    {
        $teachers = array();

        $classQuery   = '(';
        $subCodeArray = explode(',', $subCode);
        foreach ($subCodeArray as $code) {
            if (strpos($code, '/') !== false) {
                $codeArray = explode('/', $code);
                $classQuery .= "( ClassCode = '{$codeArray[1]}' AND ClassCampus = '{$codeArray[0]}' ) OR ";
            } else {
                $classQuery .= "ClassCode = '$code' OR ";
            }
        }

        $classQuery = substr($classQuery, 0, -3) . ')';

        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester());
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear());
        $msQuery = "SELECT DISTINCT StaffID, StaffSeq FROM vSubjectClassesStaff
                    WHERE $classQuery
                    AND FileYear='{$fileYear}'
                    AND FileSemester='{$fileSemester}'
                    ORDER BY StaffSeq";

        $this->getDatabase()->query($msQuery);

        while ($synrow = $this->getDatabase()->getNextArray()) {
            if (is_numeric($synrow['StaffID'])) {
                $teachers[] = $synrow['StaffID'];
            }
        }

        return array_unique($teachers);
    }

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
     * Get all the filetypes in Synergetic
     *
     * @return mixed
     */
    public function findFiletypes()
    {
        $query = "
            SELECT *
            FROM SynergyOne.dbo.luFileType
            WHERE Code != ''
        ";
        return $this->getDatabase()->getAll($query);
    }

    /**
     * Get all the subjects with a particular filetype
     *
     * @param string $filetype
     * @param array  $timetableCode
     *
     * @return mixed
     */
    public function findSubjectsWithFiletype($filetype, $timetableCode)
    {
        $database = $this->getDatabase();
        $params   = array(
            ':type'     => "'" . $database->quote($filetype) . "'",
            ':year'     => "'" . $database->quote($this->externalUser->getCurrentYear()) . "'",
            ':semester' => "'" . $database->quote($this->externalUser->getCurrentSemester()) . "'",
        );
        if (empty($timetableCode)) {
            $query = "
                SELECT ClassCode
                FROM SynergyOne.dbo.StudentClasses
                WHERE FileType = :type
                AND FileYear = :year
                AND FileSemester = :semester
                GROUP BY ClassCode
                ORDER BY ClassCode
            ";
        } else {
            $query                 = "
                SELECT sc.ClassCode
                FROM SynergyOne.dbo.StudentClasses sc
                INNER JOIN SynergyOne.dbo.Timetable t on sc.ClassCampus = t.ClassCampus
                WHERE sc.FileType = :type
                AND sc.FileYear = :year
                AND sc.FileSemester = :semester
                AND t.TimetableGroup IN (:campusList)
                GROUP BY sc.ClassCode
                ORDER BY sc.ClassCode
            ";
            $params[':campusList'] = implode(
                ',',
                array_map(
                    function ($code) use ($database) {
                        return "'" . $database->quote($code) . "'";
                    },
                    $timetableCode
                )
            );
        }
        $query = strtr($query, $params);
        return $this->getDatabase()->getAll($query);
    }
}

<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 *    This file provides access to synergetic class info        *
 *                                                           *
 ************************************************************/

class External_Synergetic_Class implements External_ClassInterface
{
    use External_Synergetic_ClearInvalidValuesTrait;

    /**
     * @var MSSQL $database
     */
    private $database;

    /**
     * @var External_UserInterface
     */
    private $externalUser;

    /**
     * Constructor
     */
    function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        $this->setExternalUser(new External_User('synergetic'));
    }

    /**
     * Gets the class code part of a query.
     *
     * @param  string $subCode
     * @return string
     */
    protected function getClassCodeQuery($subCode)
    {
        $classQuery = '(';
        $subCodeArray = explode(',', $subCode);
        foreach ($subCodeArray as $code) {
            if (strpos($code, '/') !== false) {
                $codeArray = explode('/', $code, 2);
                $classQuery .= "( ClassCode = '{$this->database->quote($codeArray[1])}' AND ClassCampus = '{$this->database->quote($codeArray[0])}' ) OR ";
            } else {
                $classQuery .= "ClassCode = '{$this->database->quote($code)}' OR ";
            }
        }
        return substr($classQuery, 0, -3) . ')';
    }

    /**
     * Returns the details of a class based on its code
     * This function is Synergetic only, not included in the interface and used for attendances
     *
     * @param string $classCode
     * @param \Schoolbox\User $user
     * @return mixed
     */
    public function getClass($classCode, \Schoolbox\User $user)
    {
        $classQuery   = $this->getClassCodeQuery($classCode);
        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester($user->getExternalId()));
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear($user->getExternalId()));
        $query = "
            SELECT *
            FROM dbo.SubjectClasses
            WHERE $classQuery
            AND FileYear='" . $fileYear . "'
            AND FileSemester='" . $fileSemester . "'
        ";

        return $this->getDatabase()->getRow($query);
    }

    /**
     * @inheritdoc
     */
    public function getStudents($subCode)
    {
        if (!$subCode) {
            /* Dont bother if you got no code */
            return false;
        }

        $classQuery   = $this->getClassCodeQuery($subCode);

        // TODO: Inject $_SESSION['user'], short term to get this working without arch changes
        /** @var Schoolbox\User $sessionUser */
        $sessionUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;
        $userExternalId = $sessionUser instanceof \Schoolbox\User ? $sessionUser->getExternalId() : null;
        $fileSemester = $this->getDatabase()->quote($this->getExternalUser()->getCurrentSemester($userExternalId));
        $fileYear = $this->getDatabase()->quote($this->getExternalUser()->getCurrentYear($userExternalId));

        $query = "
        SELECT DISTINCT StudentClasses.StudentSurname as lastname, StudentClasses.StudentPreferred as firstname,
                        StudentClasses.StudentYearLevelDescription as yearlevel,
                        StudentClasses.StudentHouseDescription as house,
                        vStudents.StudentMedicalAlert, 
                        vStudents.StudentSpecialNeedsFlag,
                        vStudents.StudentTutorDescription,
                        StudentClasses.StudentID as synergy_id, StudentClasses.ClassCampus
        FROM    dbo.vStudentClasses StudentClasses
        JOIN dbo.vStudents vStudents ON (
            vStudents.FileYear = StudentClasses.FileYear AND
            vStudents.FileSemester = StudentClasses.FileSemester AND
            vStudents.ID = StudentClasses.StudentID)
        WHERE $classQuery
            AND (StudentClasses.FileYear='" . $fileYear . "')
            AND (StudentClasses.FileSemester='" . $fileSemester . "')
            AND (StudentClasses.StartDate <= getdate() OR StudentClasses.StartDate IS NULL)
            AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)
        ORDER BY StudentClasses.StudentSurname, StudentClasses.StudentPreferred";

        return $this->clearInvalidValues(
            $this->getDatabase()->getAll($query)
        );
    }

    /**
     * @inheritdoc
     */
    public function getTeacherName($subCode)
    {
        $teachers = array();
        $classQuery   = $this->getClassCodeQuery($subCode);

        // TODO: Inject $_SESSION['user'], short term to get this working without arch changes
        /** @var Schoolbox\User $sessionUser */
        $sessionUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;
        $userExternalId = $sessionUser instanceof \Schoolbox\User ? $sessionUser->getExternalId() : null;
        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester($userExternalId));
        $fileYear = $this->database->quote($this->externalUser->getCurrentYear($userExternalId));

        $msQuery = "SELECT DISTINCT StaffID, StaffSeq FROM vSubjectClassesStaff
                    WHERE $classQuery
                    AND FileYear='{$fileYear}'
                    AND FileSemester='{$fileSemester}'
                    ORDER BY StaffSeq";

        $statement = $this->getDatabase()->query($msQuery);

        while ($synrow = $statement->fetch()) {
            if (is_numeric($synrow['StaffID'])) {
                $teachers[] = $synrow['StaffID'];
            }
        }

        return array_unique($teachers);
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
            FROM dbo.luFileType
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
                FROM dbo.StudentClasses
                WHERE FileType = :type
                AND FileYear = :year
                AND FileSemester = :semester
                GROUP BY ClassCode
                ORDER BY ClassCode
            ";
        } else {
            $query                 = "
                SELECT sc.ClassCode
                FROM dbo.StudentClasses sc
                INNER JOIN dbo.Timetable t on sc.ClassCampus = t.ClassCampus
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
    protected function getDatabase()
    {
        return $this->database;
    }

    /**
     * @return External_UserInterface
     */
    protected function getExternalUser(): External_UserInterface
    {
        return $this->externalUser;
    }

    /**
     * @param External_UserInterface $externalUser
     */
    public function setExternalUser(External_UserInterface $externalUser)
    {
        $this->externalUser = $externalUser;
    }
}

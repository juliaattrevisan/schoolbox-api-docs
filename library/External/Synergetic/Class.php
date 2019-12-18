<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Database\Predicate;
use Alaress\Database\Quoter\Sqlsrv\Quoter;

/**
 * This file provides access to synergetic class info
 */
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
     * @param string $subCode
     *
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
            }
            // SB class code may or may not have the SIS campus code prefixed, a '/' char is not conclusive as a class code can contain this.
            $classQuery .= "ClassCode = '{$this->database->quote($code)}' OR ";
        }

        return substr($classQuery, 0, -3) . ')';
    }


    /**
     * Returns the details of a class based on its code
     * This function is Synergetic only, not included in the interface and used for attendances
     *
     * @param string          $classCode
     * @param \Schoolbox\User $user
     * @param int|null        $fileYearOverride
     * @param int|null        $fileSemesterOverride
     *
     * @param string|null     $fileType
     * @return mixed
     */
    public function getClass($classCode, \Schoolbox\User $user, int $fileYearOverride = null, int $fileSemesterOverride = null, string $fileType = null)
    {
        $classQuery = $this->getClassCodeQuery($classCode);
        $fileSemester = $this->database->quote($fileSemesterOverride ?? $this->externalUser->getCurrentSemester($user->getExternalId()));
        $fileYear = $this->database->quote($fileYearOverride ?? $this->externalUser->getCurrentYear($user->getExternalId()));

        $query = "
            SELECT *
            FROM dbo.SubjectClasses
            WHERE {$classQuery}
            AND FileYear='{$fileYear}'
            AND FileSemester='{$fileSemester}'";

        if (null !== $fileType) {
            $query .= " AND FileType='{$this->database->quote($fileType)}'";
        }

        $query .= ' ORDER BY ClassCampus';

        return $this->getDatabase()->getRow($query);
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

        $classQuery = $this->getClassCodeQuery($classCode);

        // TODO: Inject $_SESSION['user'], short term to get this working without arch changes
        /** @var Schoolbox\User $sessionUser */
        $sessionUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;
        $userExternalId = $sessionUser instanceof \Schoolbox\User ? $sessionUser->getExternalId() : null;
        $fileSemester = $this->getDatabase()->quote($this->getExternalUser()->getCurrentSemester($userExternalId));
        $fileYear = $this->getDatabase()->quote($this->getExternalUser()->getCurrentYear($userExternalId));

        $query = "
        SELECT DISTINCT vStudentsAll.StudentSurname as lastname,
                        vStudentsAll.StudentPreferred as firstname,
                        vStudentsAll.StudentYearLevelDescription as yearlevel,
                        vStudentsAll.StudentHouseDescription as house,
                        CASE WHEN vStudentsAll.StudentMedicalAlertFlag= 1 THEN vStudentsAll.StudentMedicalAlert ELSE '' END as StudentMedicalAlert,
                        vStudentsAll.EnglishSecondFlag,
                        vStudentsAll.StudentSpecialNeedsFlag,
                        vStudentsAll.StudentTutorDescription,
                        vStudentsAll.StudentID as synergy_id,
                        vStudentsAll.StudentForm
        FROM            dbo.StudentClasses StudentClasses
        JOIN dbo.vStudentsAll vStudentsAll ON (
            vStudentsAll.FileYear = StudentClasses.FileYear AND
            vStudentsAll.FileSemester = StudentClasses.FileSemester AND
            vStudentsAll.ID = StudentClasses.ID)
        JOIN dbo.StudentYears sy ON (
          vStudentsAll.ID = sy.ID AND
          vStudentsAll.FileYear = sy.FileYear
        )
        JOIN dbo.Community cstu ON (vStudentsAll.ID = cstu.ID)
        WHERE $classQuery
            AND (StudentClasses.FileYear='" . $fileYear . "')
            AND (StudentClasses.FileSemester='" . $fileSemester . "')
            AND (StudentClasses.StartDate <= getdate() OR StudentClasses.StartDate IS NULL)
            AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)
            AND NOT (vStudentsAll.StudentStatusSynergyMeaning IN ('LEFT', 'LEAVING') AND DATEDIFF(DAY, sy.LeavingDate, GETDATE()) > 0)
        ORDER BY vStudentsAll.StudentSurname, vStudentsAll.StudentPreferred";

        return $this->clearInvalidValues(
            $this->getDatabase()->getAll($query)
        );
    }

    /**
     * @inheritdoc
     * @param Alaress_Date_Format|null $date
     * @throws Predicate\InvalidColumnException
     */
    public function getTeachers(string $classCode, Alaress_Date_Format $date = null): array
    {
        if (!$classCode) {
            /* Dont bother if you got no code */
            return [];
        }

        $teachers = [];
        $classQuery = $this->getClassCodeQuery($classCode);

        // TODO: Inject $_SESSION['user'], short term to get this working without arch changes
        /** @var Schoolbox\User $sessionUser */
        $sessionUser = $_SESSION['user'] ?? null;
        $userExternalId = $sessionUser instanceof \Schoolbox\User ? $sessionUser->getExternalId() : null;

        if ($date === null) {
            $join = '';
            $pb   = (new Predicate\Builder(new Quoter(), 'vSubjectClassesStaff'))
                ->equals('FileYear', $this->externalUser->getCurrentYear($userExternalId))
                ->equals('FileSemester', $this->externalUser->getCurrentSemester($userExternalId));
        } else {
            $join       = "
                JOIN FileSemesters ON
                    FileSemesters.FileYear = vSubjectClassesStaff.FileYear AND
                    FileSemesters.FileSemester = vSubjectClassesStaff.FileSemester
                ";
            $dateString = $date->getUsingFormat(Alaress_Date_Format::DATABASE_FORMAT);
            $pb         = (new Predicate\Builder(new Quoter(), 'FileSemesters'))
                ->lessThanOrEqualTo('StartDate', $dateString)
                ->greaterThanOrEqualTo('EndDate', $dateString);
        }

        $fileYearWhere = implode(' AND ', $pb->getWhere());

        $msQuery = "SELECT DISTINCT StaffID, StaffSeq FROM vSubjectClassesStaff
                    {$join}
                    WHERE $classQuery
                    AND $fileYearWhere
                    ORDER BY StaffSeq";

        $statement = $this->getDatabase()->query($msQuery, $pb->getParams());

        while ($synrow = $statement->fetch()) {
            if (is_numeric($synrow['StaffID'])) {
                $teachers[] = $synrow['StaffID'];
            }
        }

        return array_unique($teachers);
    }

    /**
     * Get all the filetypes in Synergetic
     * @return array
     */
    public function findClassTypes(): array
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
     * @return array
     */
    public function findClassesByType(string $filetype, array $timetableCode): array
    {
        $database = $this->getDatabase();

        $params = [
            ':type' => "'" . $database->quote($filetype) . "'",
            ':year' => "'" . $database->quote($this->externalUser->getCurrentYear()) . "'",
            ':semester' => "'" . $database->quote($this->externalUser->getCurrentSemester()) . "'",
        ];

        if (empty($timetableCode)) {
            $query = $this->queryStringWithoutTimeTableCode();
        } else {
            $query = $this->queryStringWithTimeTableCode();

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
     * @return string
     */
    private function queryStringWithoutTimeTableCode(): string
    {
        return "
            SELECT
                DISTINCT ClassCode,
                CASE WHEN ClassCampus != '' THEN CONCAT(ClassCampus, '/', ClassCode) ELSE ClassCode END AS ClassCodeValue
                FROM dbo.StudentClasses
                WHERE FileType = :type
                AND FileYear = :year
                AND FileSemester = :semester
                ORDER BY ClassCode
        ";
    }

    /**
     * @return string
     */
    private function queryStringWithTimeTableCode(): string
    {
        return "
            SELECT
                DISTINCT sc.ClassCode,
                CASE WHEN sc.ClassCampus != '' THEN CONCAT(sc.ClassCampus, '/', sc.ClassCode) ELSE sc.ClassCode END AS ClassCodeValue
                FROM dbo.StudentClasses sc
                INNER JOIN dbo.Timetable t on sc.ClassCampus = t.ClassCampus
                WHERE sc.FileType = :type
                AND sc.FileYear = :year
                AND sc.FileSemester = :semester
                AND t.TimetableGroup IN (:campusList)
                ORDER BY ClassCode
        ";
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

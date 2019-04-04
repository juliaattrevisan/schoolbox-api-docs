<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      https://www.alaress.com.au/
 */

class External_Synergetic_Stpatricks_Class extends External_Synergetic_Class
{
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
        $sessionUser    = isset($_SESSION['user']) ? $_SESSION['user'] : null;
        $userExternalId = $sessionUser instanceof \Schoolbox\User ? $sessionUser->getExternalId() : null;
        $fileSemester   = $this->getDatabase()->quote($this->getExternalUser()->getCurrentSemester($userExternalId));
        $fileYear       = $this->getDatabase()->quote($this->getExternalUser()->getCurrentYear($userExternalId));

        $query = "
        SELECT DISTINCT vStudentsAll.StudentSurname as lastname, 
                        vStudentsAll.StudentPreferred as firstname,
                        vStudentsAll.StudentYearLevelDescription as yearlevel,
                        vStudentsAll.StudentHouseDescription as house,
                        vStudentsAll.StudentMedicalAlert, 
                        vStudentsAll.EnglishSecondFlag,
                        vStudentsAll.StudentSpecialNeedsFlag,
                        vStudentsAll.StudentTutorDescription,
                        vStudentsAll.StudentID as synergy_id,
                        vStudentsAll.StudentForm,
                        StudentClasses.ClassCampus
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
}

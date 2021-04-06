<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class External_Synergetic_Scc_Class extends External_Synergetic_Class
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

        $classQuery   = $this->getClassCodeQuery($classCode);

        // TODO: Inject $_SESSION['user'], short term to get this working without arch changes
        /** @var Schoolbox\User $sessionUser */
        $sessionUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;
        $userExternalId = $sessionUser instanceof \Schoolbox\User ? $sessionUser->getExternalId() : null;
        $fileSemester = $this->getDatabase()->legacyQuote($this->getExternalUser()->getCurrentSemester($userExternalId));
        $fileYear = $this->getDatabase()->legacyQuote($this->getExternalUser()->getCurrentYear($userExternalId));

        $query = "
        SELECT DISTINCT StudentClasses.StudentSurname as lastname, StudentClasses.StudentPreferred as firstname,
                        StudentClasses.StudentYearLevelDescription as yearlevel,
                        StudentClasses.StudentHouseDescription as house,
                        vStudents.StudentMedicalAlert,
                        vStudents.StudentSpecialNeedsFlag,
                        vStudents.StudentNonResidentFlag,
                        vStudents.StudentIBFlag,
                        vStudents.EnglishSecondFlag,
                        vStudents.StudentTSIFlag,
                        vStudents.IntegrationFlag,
                        vStudents.EMAFlag,
                        vStudents.StudentTutorDescription,
                        StudentClasses.StudentID as synergy_id
        FROM    dbo.vStudentClasses StudentClasses
        JOIN dbo.vStudents vStudents ON (
            vStudents.FileYear = StudentClasses.FileYear AND
            vStudents.FileSemester = StudentClasses.FileSemester AND
            vStudents.ID = StudentClasses.StudentID)
        WHERE $classQuery
            AND (StudentClasses.FileYear={$fileYear})
            AND (StudentClasses.FileSemester={$fileSemester})
            AND (StudentClasses.StartDate <= getdate() OR StudentClasses.StartDate IS NULL)
            AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)
        ORDER BY StudentClasses.StudentSurname, StudentClasses.StudentPreferred";

        return $this->clearInvalidValues(
            $this->getDatabase()->getAll($query)
        );
    }
}

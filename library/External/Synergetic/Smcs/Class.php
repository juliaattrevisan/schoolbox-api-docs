<?php
/**
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

class External_Synergetic_Smcs_Class extends External_Synergetic_Class
{
    /**
     * @inheritdoc
     */
    public function getStudents($subCode)
    {
        if (!$subCode) {
            /* Dont bother if you got no code */
            return false;
        }

        $classQuery = $this->getClassCodeQuery($subCode);

        // TODO: Inject $_SESSION['user'], short term to get this working without arch changes
        /** @var Schoolbox\User $sessionUser */
        $sessionUser    = isset($_SESSION['user']) ? $_SESSION['user'] : null;
        $userExternalId = $sessionUser instanceof \Schoolbox\User ? $sessionUser->getExternalId() : null;
        $fileSemester   = $this->getDatabase()->quote($this->getExternalUser()->getCurrentSemester($userExternalId));
        $fileYear       = $this->getDatabase()->quote($this->getExternalUser()->getCurrentYear($userExternalId));

        $query = "
        SELECT DISTINCT StudentClasses.StudentSurname as lastname, 
                        StudentClasses.StudentPreferred as firstname,
                        StudentClasses.StudentYearLevelDescription as yearlevel,
                        StudentClasses.StudentHouseDescription as house,
                        uvStudents.StudentMedicalAlert, 
                        uvStudents.StudentSpecialNeedsFlag,
                        uvStudents.StudentTutorDescription,
                        StudentClasses.StudentID as synergy_id,
                        StudentClasses.ClassCampus
        FROM            dbo.vStudentClasses StudentClasses
        JOIN dbo.uvStudents uvStudents ON (
            uvStudents.FileYear = StudentClasses.FileYear AND
            uvStudents.FileSemester = StudentClasses.FileSemester AND
            uvStudents.ID = StudentClasses.StudentID)
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
}
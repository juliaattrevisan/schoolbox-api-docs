<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 *    This file provides access to synergetic user info      *
 *                                                           *
 ************************************************************/
class External_Synergetic_Stritas_User extends External_Synergetic_User
{
    /**
     * Get a guy's info from Synergetic
     *
     * @param  $synID
     *
     * @return array|bool
     */
    public function getStudent($synID)
    {
        if (!$this->isExternalIdValid($synID)) {
            return [];
        }
        $currentYear     = $this->getCurrentYear();
        $currentSemester = $this->getCurrentSemester();
        $query   = "SELECT
                    vStudents.StudentID,
                    vStudents.StudentGiven1,
                    vStudents.StudentSurname,
                    vStudents.StudentBirthDate,
                    vStudents.StudentTutorName,
                    vStudents.StudentHeadOfHouse,
                    House.HeadOfHouseID,
                    vStudents.StudentFormStaffName,
                    vStudents.StudentTutorDescription,
                    luForm.ID as TeacherFormID,
                    vStudents.StudentForm,
                    luTutor.ID as TeacherTutorID,
                    vStudents.StudentTutor,
                    luForm.Description as FormDescription,
                    vStudents.StudentHouseDescription,
                    vStudents.StudentHouse,
                    vStudents.StudentYearLevel,
                    vStudents.StudentYearLevelCoordinator,
                    luYearLevel.YearLevelCoordID,
                    vStudents.StudentLockerBook,
                    vStudents.StudentEmergencyName,
                    vStudents.StudentEmergencyPhone,
                    vStudents.StudentEmergencyPhoneAlt,
                    vStudents.StudentEmergency2Name,
                    vStudents.StudentEmergency2Phone,
                    vStudents.StudentEmergency2PhoneAlt,
                    vStudents.StudentEmergency3Name,
                    vStudents.StudentEmergency3Phone,
                    vStudents.StudentEmergency3PhoneAlt,
                    vStudents.StudentMedicalAlert,
                    vStudents.StudentSpecialNeedsFlag,
                    vStudents.StudentStudiesCode,
                    AlertMessage,
                    vStudents.StudentBoarder
                FROM SynergyOne.dbo.vStudents vStudents
                    LEFT JOIN CommunityAlerts
                        ON CommunityAlerts.ID = StudentID
                        AND (CommunityAlerts.AlertDateFrom IS NULL OR CommunityAlerts.AlertDateFrom <= CAST(GETDATE() AS date))
                        AND (CommunityAlerts.AlertDateTo IS NULL OR CommunityAlerts.AlertDateTo >= CAST(GETDATE() AS date))
                    LEFT JOIN luForm ON vStudents.StudentForm = luForm.Code
                    LEFT JOIN luTutor ON vStudents.StudentTutor = luTutor.Code
                    LEFT JOIN luYearLevel ON vStudents.StudentCampus = luYearLevel.Campus
                        AND vStudents.StudentYearLevel = luYearLevel.Code
                    LEFT JOIN luHouse House ON StudentCampus = House.Campus AND StudentHouse = House.Code
                WHERE
                    vStudents.StudentID = '" . $this->getDatabase()->quote($synID) . "'
                    AND vStudents.FileSemester='" . $this->getDatabase()->quote($currentSemester) . "'
                    AND vStudents.FileYear='" . $this->getDatabase()->quote($currentYear) . "'
        ";

		$student = [];

        foreach ($this->getDatabase()->getAll($query) ?: array() as $row) {
            if (empty($student)) {
                // fill in student record
                $student = $row;

                if ($student['StudentBirthDate']
                    && preg_match('/([\w]+)\s+([\d]+)\s+([\d]+)/', $student['StudentBirthDate'], $matches)
                ) {
                    $student['StudentBirthDate'] = strtotime($matches[2] . ' ' . $matches[1] . ' ' . $matches[3]);
                } else {
                    $student['StudentBirthDate'] = false;
                }
                $student['AlertMessage'] = array($student['AlertMessage']);
            } else {
                // join subsequent alerts
                $student['AlertMessage'][] = $row['AlertMessage'];
            }
        }

        /* Implode with newlines if you were returned a list of
         * AlertMessages to join - otherwise do nothing to it
         */
		if (!empty($student) && is_array($student['AlertMessage'])) {
			$student['AlertMessage'] = implode(
				"\n",
				array_unique(array_filter($student['AlertMessage']))
			);
		}

        $student = array_map(function($value) {
            return trim(str_replace('Not Selected', '', $value));
        }, $student);

        return $student;
    }
}

<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

/**
 * Gets the international student data in sk
 */
class External_Synergetic_Sk_User extends External_Synergetic_User
{
    /**
     * @inheritdoc
     */
    public function getStudent(string $externalUserId): array
    {
        if (!$this->isExternalIdValid($externalUserId)) {
            return [];
        }

        if ($this->config->get('external_learning_plans')) {
            $learningPlanQuery = "(
                            SELECT    COUNT(*)
                            FROM      dbo.IndividualPlans IEP
                            WHERE     IEP.ID = vStudentsAll.ID AND
                            StartDate <= CURRENT_TIMESTAMP AND
                            (EndDate >= CURRENT_TIMESTAMP OR EndDate IS NULL)
                        ) AS countIndividualEducationPlans";
        } else {
            $learningPlanQuery = "0 AS countIndividualEducationPlans";
        }

        $query =
            "
            SELECT      vStudentsAll.StudentID,
                        vStudentsAll.StudentGiven1,
                        vStudentsAll.StudentSurname,
                        vStudentsAll.StudentGender,
                        CONVERT(nvarchar(30), vStudentsAll.StudentBirthDate, " . self::FORMAT_ISO8601 . " ) as StudentBirthDate,
                        vStudentsAll.StudentTutorName,
                        vStudentsAll.StudentHeadOfHouse,
                        House.HeadOfHouseID,
                        vStudentsAll.StudentFormStaffName,
                        vStudentsAll.StudentTutorDescription,
                        luForm.ID AS TeacherFormID,
                        vStudentsAll.StudentForm,
                        luTutor.ID AS TeacherTutorID,
                        vStudentsAll.StudentHouseDescription,
                        vStudentsAll.StudentHouse,
                        vStudentsAll.StudentYearLevel,
                        vStudentsAll.StudentYearLevelCoordinator,
                        luYearLevel.YearLevelCoordID,
                        vStudentsAll.StudentEmergencyName,
                        vStudentsAll.StudentEmergencyPhone,
                        vStudentsAll.StudentEmergencyPhoneAlt,
                        vStudentsAll.StudentEmergency2Name,
                        vStudentsAll.StudentEmergency2Phone,
                        vStudentsAll.StudentEmergency2PhoneAlt,
                        vStudentsAll.StudentEmergency3Name,
                        vStudentsAll.StudentEmergency3Phone,
                        vStudentsAll.StudentEmergency3PhoneAlt,
                        CASE WHEN vStudentsAll.StudentMedicalAlertFlag= 1 THEN vStudentsAll.StudentMedicalAlert ELSE '' END as StudentMedicalAlert,
                        vStudentsAll.StudentMobilePhone,
                        vStudentsAll.StudentSpecialNeedsFlag,
                        vStudentsAll.EnglishSecondFlag,
                        vStudentsAll.StudentStudiesCode,
                        AlertMessage,
                        vStudentsAll.LegalCourtOrderType AS CourtOrderType,
                        vStudentsAll.LegalParentsSeparatedFlag AS ParentsSeparatedFlag,
                        vStudentsAll.StudentBoarder,
                        vStudentsAll.StudentsComment as Comment,
                        '' AS StudentEnrolmentDate,
                        '' AS StudentLeavingDate,
                        vStudentsAll.StudentIBFlag,
                        vStudentsAll.IndigenousFlag,
                        vStudentsAll.StudentTSIFlag,
                        vStudentsAll.StudentNonResidentFlag,
                        $learningPlanQuery
            FROM        dbo.vStudentsAll vStudentsAll
            LEFT JOIN   CommunityAlerts ON
                            CommunityAlerts.ID = StudentID AND
                            (
                                CommunityAlerts.AlertDateFrom IS NULL OR
                                CommunityAlerts.AlertDateFrom <= CAST(GETDATE() AS DATE)
                            ) AND
                            (
                                CommunityAlerts.AlertDateTo IS NULL OR
                                CommunityAlerts.AlertDateTo >= CAST(GETDATE() AS DATE)
                            )
            LEFT JOIN   luForm ON vStudentsAll.StudentForm = luForm.Code
            LEFT JOIN   luTutor ON vStudentsAll.StudentTutor = luTutor.Code
            LEFT JOIN   luYearLevel ON
                            vStudentsAll.StudentCampus = luYearLevel.Campus AND
                            vStudentsAll.StudentYearLevel = luYearLevel.Code
            LEFT JOIN   luHouse House ON
                            StudentCampus = House.Campus AND
                            StudentHouse = House.Code
            JOIN        FileSemesters Semester ON
                            Semester.FileSemester = vStudentsAll.FileSemester AND
                            Semester.FileYear = vStudentsAll.FileYear AND
                            Semester.SystemCurrentFlag = 1
            WHERE       vStudentsAll.StudentID = :externalId
        ";

        $student = [];

        $iteratorStudents = $this->getConnector()->getQueryIterator($query, ['externalId' => $externalUserId]);

        foreach ($iteratorStudents as $row) {
            // There might be multiple records, if this is the first one populate the bulk of the details

            if (empty($student)) {
                $student = $row;

                if ($student['StudentBirthDate'] && $birthDate = strtotime($student['StudentBirthDate'])) {
                    $student['StudentBirthDate'] = $birthDate;
                } else {
                    $student['StudentBirthDate'] = false;
                }

                /* Initialise stacks
                 */
                $student['AlertMessage'] = [];
                $student['AlertLegal'] = [];
            }

            /* Legal Alerts are distinguished by their CourtOrderType node.
             * If that CourtOrderType node was meaningful, then it's a Legal Alert and gets special treatment.
             * Otherwise it's a generic alert.
             */
            $courtOrderType = trim((string)($row['CourtOrderType'] ?? null));

            if ('' !== $courtOrderType) {
                /* A specially-crafted AlertLegal message about legal matters.
                 */
                $student['AlertLegal'][] = sprintf(
                    '%s - %s',
                    \External_UserInterface::COURT_ORDER_PREFIX,
                    $courtOrderType
                );
            } else {
                /* Some kind of generic alert message
                 */
                $student['AlertMessage'][] = $row['AlertMessage'];
            }
        }

        /* Uniquify both AlertMessage and AlertLegal nodes since they might dupe
         * as a result of those left joins in the mega query
         */
        if (!empty($student) && is_array($student['AlertMessage'])) {
            $student['AlertMessage'] = array_unique(array_filter($student['AlertMessage']));
        }

        if (!empty($student) && is_array($student['AlertLegal'])) {
            $student['AlertLegal'] = array_unique(array_filter($student['AlertLegal']));
        }

        // Remove Synergetic's annoying 'Not Selected' default value for lookups

        return $this->clearInvalidValues($student);
    }
}

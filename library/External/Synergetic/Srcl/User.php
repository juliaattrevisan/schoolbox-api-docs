<?php

/**
 * This file provides access to synergetic user info
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

class External_Synergetic_Srcl_User extends External_Synergetic_User
{
    /**
     * @inheritDoc
     */
    public function getStudent(string $externalUserId): array
    {
        if (!$this->isExternalIdValid($externalUserId)) {
            return [];
        }

        $query   = "
            SELECT
                vStudents.StudentID,
                vStudents.StudentGiven1,
                vStudents.StudentSurname,
                '' AS StudentGender,
                CONVERT(nvarchar(30), vStudents.StudentBirthDate, " . self::FORMAT_ISO8601 . " ) as StudentBirthDate,
                vStudents.StudentTutorName,
                vStudents.StudentHeadOfHouse,
                House.HeadOfHouseID,
                vStudents.StudentFormStaffName,
                vStudents.StudentTutorDescription,
                luForm.ID as TeacherFormID,
                vStudents.StudentForm,
                luTutor.ID as TeacherTutorID,
                vStudents.StudentHouseDescription,
                vStudents.StudentHouse,
                vStudents.StudentYearLevel,
                vStudents.StudentYearLevelCoordinator,
                luYearLevel.YearLevelCoordID,
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
                vStudents.StudentMobilePhone,
                vStudents.StudentSpecialNeedsFlag,
                vStudents.StudentStudiesCode,
                vStudents.StudentGovernmentStudentNumber,
                AlertMessage,
                vStudents.LegalCourtOrderType AS CourtOrderType,
                vStudents.StudentBoarder,
                vStudents.StudentsComment as Comment,
                '' AS StudentEnrolmentDate,
                '' AS StudentLeavingDate,
                vStudents.StudentIBFlag,
                vStudents.LegalParentsSeparatedFlag AS ParentsSeparatedFlag,
                uStudentProgressiveAbsenceCount.AbsenceFullDaysForYear,
                uStudentProgressiveAbsenceCount.LateStartDaysForYear,
                uStudentProgressiveAbsenceCount.EarlyLeaveDaysForYear,
                vStudents.EnglishSecondFlag,
                (
                    SELECT    COUNT(*)
                    FROM      dbo.IndividualPlans IEP
                    WHERE     IEP.ID = vStudentsAll.ID AND
                    StartDate <= CURRENT_TIMESTAMP AND
                    (EndDate >= CURRENT_TIMESTAMP OR EndDate IS NULL)
                ) AS countIndividualEducationPlans
            FROM        dbo.vStudents vStudents
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
            LEFT JOIN   luForm ON vStudents.StudentForm = luForm.Code
            LEFT JOIN   luTutor ON vStudents.StudentTutor = luTutor.Code
            LEFT JOIN   luYearLevel ON
                            vStudents.StudentCampus = luYearLevel.Campus AND
                            vStudents.StudentYearLevel = luYearLevel.Code
            LEFT JOIN   luHouse House ON
                            StudentCampus = House.Campus AND
                            StudentHouse = House.Code

            JOIN        FileSemesters Semester ON
                            Semester.FileSemester = vStudents.FileSemester AND
                            Semester.FileYear = vStudents.FileYear AND
                            Semester.SystemCurrentFlag = 1
            LEFT JOIN   uStudentProgressiveAbsenceCount ON uStudentProgressiveAbsenceCount.ID = vStudents.StudentID
                            AND uStudentProgressiveAbsenceCount.FileYear = vStudents.FileYear
            WHERE       vStudents.StudentID = :externalId
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

                /* Forcibly re-evaluate boarderlyness using this connector's specific interpretation
                 */
                $student['StudentBoarder'] = $this->determineBoarderType((string)$student['StudentBoarder']);

                /* Initialise stacks
                 */
                $student['AlertMessage'] = [];
                $student['AlertLegal'] = [];
            }

            /* Legal Alerts are distinguished by their CourtOrderType, it is not related to AlertMessage.
             * If CourtOrderType is meaningful, then we add only it's type to the LegalAlert stack.
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
            }

            /* Some kind of generic alert message
             */
            $student['AlertMessage'][] = $row['AlertMessage'];
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

<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Pcschools\QueryHelper;

class NonFreedom implements IQueryHelper
{
    private const CONTACT_TYPE_FATHER    = 'F';
    private const CONTACT_TYPE_MOTHER    = 'M';
    private const CONTACT_TYPE_GUARDIAN  = 'G';
    private const CONTACT_TYPE_CAREGIVER = 'C';

    private const CONTACT_LEVEL_FULL           = 'F';
    private const CONTACT_LEVEL_NONE           = 'N';
    private const CONTACT_LEVEL_NO_DEMOGRAPHIC = 'D';


    /**
     * @return array
     */
    public function getFindAllParentsQuery(): array
    {
        $childSelect = "
            S.STUDKEY       AS student_id,
            S.CURRENT_YEAR  AS student_year_level,
            S.CAMPUS        AS student_campus,
            S.HOUSE         AS student_house,
            S.SEX           AS student_gender,
            S.GIVEN_NAME    AS student_preferred,
            S.SURNAME       AS student_surname
        ";

        $sql = "
            SELECT
                ''                    AS student_contact_network_login,
                CAST(F.IDENTITY_ID# AS VARCHAR(16)) + '-' + :suffixF
                                      AS student_contact_id,
                ''                    AS student_contact_title,
                F.DAD_FIRSTNAME       AS student_contact_given_name,
                F.DAD_FIRSTNAME       AS student_contact_preferred,
                F.DAD_SURNAME         AS student_contact_surname,
                ''                    AS student_contact_home_email,
                F.DAD_EMAIL           AS student_contact_default_email,
                ''                    AS student_contact_work_email,
                $childSelect
            FROM FAMILY F
            INNER JOIN STUDENT S ON F.FAMKEY = S.FAMILY_CODE
            WHERE (
                S.DATE_DEPART IS NULL
                OR
                DATEDIFF(YEAR, S.DATE_DEPART, '1901') >= 0
                OR
                DATEDIFF(DAY, S.DATE_DEPART, getdate()) <= 0
            )
            AND   F.DAD_LMS = :contactLevel

            UNION ALL

            SELECT
                ''                    AS student_contact_network_login,
                CAST(F.IDENTITY_ID# AS VARCHAR(16)) + '-' + :suffixM
                                      AS student_contact_id,
                ''                    AS student_contact_title,
                F.MUM_FIRSTNAME       AS student_contact_given_name,
                F.MUM_FIRSTNAME       AS student_contact_preferred,
                F.MUM_SURNAME         AS student_contact_surname,
                ''                    AS student_contact_home_email,
                F.MUM_EMAIL           AS student_contact_default_email,
                ''                    AS student_contact_work_email,
                $childSelect
            FROM FAMILY F
            INNER JOIN STUDENT S ON F.FAMKEY = S.FAMILY_CODE
            WHERE (
                S.DATE_DEPART IS NULL
                OR
                DATEDIFF(YEAR, S.DATE_DEPART, '1901') >= 0
                OR
                DATEDIFF(DAY, S.DATE_DEPART, getdate()) <= 0
            )
            AND   F.MUM_LMS = :contactLevel

            UNION ALL

            SELECT
                ''                    AS student_contact_network_login,
                CAST(F.IDENTITY_ID# AS VARCHAR(16)) + '-' + :suffixC
                                      AS student_contact_id,
                ''                    AS student_contact_title,
                F.CGIVE_FIRSTNAME     AS student_contact_given_name,
                F.CGIVE_FIRSTNAME     AS student_contact_preferred,
                F.CGIVE_SURNAME       AS student_contact_surname,
                ''                    AS student_contact_home_email,
                F.CGIVE_EMAIL         AS student_contact_default_email,
                ''                    AS student_contact_work_email,
                $childSelect
            FROM FAMILY F
            INNER JOIN STUDENT S ON F.FAMKEY = S.FAMILY_CODE
            WHERE (
                S.DATE_DEPART IS NULL
                OR
                DATEDIFF(YEAR, S.DATE_DEPART, '1901') >= 0
                OR
                DATEDIFF(DAY, S.DATE_DEPART, getdate()) <= 0
            )
            AND   F.CGIVE_LMS = :contactLevel

            UNION ALL

            SELECT
                ''                    AS student_contact_network_login,
                CAST(F.IDENTITY_ID# AS VARCHAR(16)) + '-' + :suffixG
                                      AS student_contact_id,
                ''                    AS student_contact_title,
                F.GUARD_FIRSTNAME     AS student_contact_given_name,
                F.GUARD_FIRSTNAME     AS student_contact_preferred,
                F.GUARD_SURNAME       AS student_contact_surname,
                ''                    AS student_contact_home_email,
                F.GUARD_EMAIL         AS student_contact_default_email,
                ''                    AS student_contact_work_email,
                $childSelect
            FROM FAMILY F
            INNER JOIN STUDENT S ON F.FAMKEY = S.FAMILY_CODE
            WHERE (
                S.DATE_DEPART IS NULL
                OR
                DATEDIFF(YEAR, S.DATE_DEPART, '1901') >= 0
                OR
                DATEDIFF(DAY, S.DATE_DEPART, getdate()) <= 0
            )
            AND   F.GUARD_LMS = :contactLevel

            ORDER BY student_contact_id ASC
        ";


        $params = [
            'suffixF'      => self::CONTACT_TYPE_FATHER,
            'suffixM'      => self::CONTACT_TYPE_FATHER,
            'suffixC'      => self::CONTACT_TYPE_CAREGIVER,
            'suffixG'      => self::CONTACT_TYPE_GUARDIAN,
            'contactLevel' => self::CONTACT_LEVEL_FULL,
        ];

        return [ $sql, $params ];
    }

    /**
     * @param string $externalUserId
     * @return array
     */
    public function getGetEnrolledStudentsQuery($externalUserId): array
    {
        // parent external ID format: $famKey-{some code}
        $suffix     = '';
        $familyCode = $externalUserId;
        $parts = explode('-', $externalUserId);

        if (\count($parts) > 1) {
            $suffix     = array_pop($parts);
            $familyCode = implode('-', $parts);
        }

        $query = '
            SELECT S.STUDKEY AS RelatedID
            FROM STUDENT S
            INNER JOIN FAMILY AS S_FAMILY ON S.FAMILY_CODE = S_FAMILY.FAMKEY
            WHERE S_FAMILY.IDENTITY_ID# = :familyCode
        ';
        $params = [
            'familyCode' => $familyCode,
        ];

        switch ($suffix) {
            case 'F':
                $query .= ' AND S_FAMILY.DAD_LMS = :contactLevel';
                $params['contactLevel'] = self::CONTACT_LEVEL_FULL;
                break;
            case 'M':
                $query .= ' AND S_FAMILY.MUM_LMS = :contactLevel';
                $params['contactLevel'] = self::CONTACT_LEVEL_FULL;
                break;
            case 'C':
                $query .= ' AND S_FAMILY.CGIVE_LMS = :contactLevel';
                $params['contactLevel'] = self::CONTACT_LEVEL_FULL;
                break;
            case 'G':
                $query .= ' AND S_FAMILY.GUARD_LMS = :contactLevel';
                $params['contactLevel'] = self::CONTACT_LEVEL_FULL;
                break;
            default:
                break;
        }

        return [ $query, $params ];
    }


    /**
     * @param array $studentExternalIds
     * @return array
     */
    public function getGetParentEmailsQuery($studentExternalIds): array
    {
        $query = '
            SELECT
                F.IDENTITY_ID#,
                -- Father
                F.DAD_LMS,
                F.DAD_EMAIL,
                -- Mother
                F.MUM_LMS,
                F.MUM_EMAIL,
                -- Guardian
                F.GUARD_LMS,
                F.GUARD_EMAIL,
                -- Carer
                F.CGIVE_LMS,
                F.CGIVE_EMAIL
            FROM FAMILY AS F
            INNER JOIN STUDENT AS S ON F.FAMKEY = S.FAMILY_CODE
            WHERE 1=1
        ';

        return [ $query, [] ];
    }


    /**
     * @param array $studentExternalId
     * @return array
     */
    public function getGetStudentContactListQuery(array $studentExternalId): array
    {
        if (empty($studentExternalId)) {
            return [];
        }

        $ids = implode(',', $studentExternalId);

        $sql = '
            SELECT
                F.IDENTITY_ID#,
                -- Father
                F.DAD_LMS,
                F.FATHER_TITLE,
                F.DAD_FIRSTNAME,
                F.DAD_SURNAME,
                F.DAD_EMAIL,
                F.DAD_HOME_PHONE,
                F.DAD_MOBILE,
                F.DAD_BUSS_PHONE,
                F.DAD_STATUS,
                -- Mother
                F.MUM_LMS,
                F.MOTHER_TITLE,
                F.MUM_FIRSTNAME,
                F.MUM_SURNAME,
                F.MUM_EMAIL,
                F.MUM_HOME_PHONE,
                F.MUM_MOBILE,
                F.MUM_BUSS_PHONE,
                F.MUM_STATUS,
                -- Guardian
                F.GUARD_LMS,
                F.GUARDIAN_TITLE,
                F.GUARD_FIRSTNAME,
                F.GUARD_SURNAME,
                F.GUARD_EMAIL,
                F.GUARD_HOME_PH,
                F.GUARD_MOBILE,
                F.GUARD_BUSS_PH,
                F.GUARD_STATUS,
                -- Carer
                F.CGIVE_LMS,
                F.CGIVE_TITLE,
                F.CGIVE_FIRSTNAME,
                F.CGIVE_SURNAME,
                F.CGIVE_EMAIL,
                F.CGIVE_HOME_PH,
                F.CGIVE_MOBILE,
                F.CGIVE_BUSS_PH,
                F.CGIVE_STATUS
            FROM FAMILY AS F
            INNER JOIN STUDENT AS S ON F.FAMKEY = S.FAMILY_CODE
            WHERE S.STUDKEY IN ('. $ids .')
        ';

        return [ $sql, [] ];
    }


    /**
     * @param string $studentExternalId
     * @return array
     */
    public function getGetStudentQuery($studentExternalId): array
    {
        $sql = "
            SELECT
                -- student details
                S.STUDKEY                                                     AS StudentID,
                S.GIVEN_NAME                                                  AS StudentGiven1,
                S.LEGAL_SURNAME                                               AS StudentSurname,
                S.SEX                                                         AS StudentGender,
                S.BIRTH_DATE                                                  AS StudentBirthDate,
                S_HOUSE.DESCRIPTION                                           AS StudentHouseDescription,
                S.HOUSE                                                       AS StudentHouse,
                ''                                                            AS HeadOfHouseID,
                ''                                                            AS StudentHeadOfHouse,
                S.STATUS                                                      AS StudentBoarder,
                ''                                                            AS Comment,
                ''                                                            AS StudentEnrolmentDate,
                ''                                                            AS StudentLeavingDate,
                ''                                                            AS StudentIBFlag,
                S_CLASS.DESCRIPTION                                           AS StudentForm,
                S_HOME_TEACHER.NAME_FOR_REPORT                                AS StudentFormStaffName,
                S.HOME_TEACHER                                                AS TeacherFormID,
                S_DEAN.NAME_FOR_REPORT                                        AS StudentTutorName,
                ''                                                            AS StudentTutorDescription,
                S.DEAN                                                        AS TeacherTutorID,
                S.CURRENT_YEAR                                                AS StudentYearLevel,
                S.DEAN                                                        AS YearLevelCoordID,
                ''                                                            AS StudentYearLevelCoordinator,

                -- emerghergency contact details
                S.EMERG_CONTACT                                               AS StudentEmergencyName,
                S.EMERG_PHONE                                                 AS StudentEmergencyPhone,
                ''                                                            AS StudentEmergencyPhoneAlt,
                S_FAMILY.DAD_FIRSTNAME + ' ' + S_FAMILY.DAD_SURNAME           AS StudentEmergency2Name,
                S_FAMILY.DAD_EMERG                                            AS StudentEmergency2Phone,
                S_FAMILY.DAD_EMERG_PH                                         AS StudentEmergency2PhoneAlt,
                S_FAMILY.MUM_FIRSTNAME + ' ' + S_FAMILY.MUM_SURNAME           AS StudentEmergency3Name,
                S_FAMILY.MUM_EMERG                                            AS StudentEmergency3Phone,
                S_FAMILY.MUM_EMERG_PH                                         AS StudentEmergency3PhoneAlt,

                -- alerts
                S.ALERT                                                       AS StudentMedicalAlert,
                ''                                                            AS CourtOrderType,
                ''                                                            AS StudentMobilePhone,
                ''                                                            AS StudentSpecialNeedsFlag,
                ''                                                            AS EnglishSecondFlag,
                ''                                                            AS StudentStudiesCode,
                S.ALERT                                                       AS AlertMessage,
                0                                                             AS countIndividualEducationPlans
            FROM STUDENT S
            LEFT JOIN CODEMAST AS S_HOUSE ON S.HOUSE = S_HOUSE.CODE AND S_HOUSE.TYPE = 'HOUSE'
            LEFT JOIN CODEMAST AS S_CLASS ON S.HOME_CLASS = S_CLASS.CODE AND S_CLASS.TYPE = 'CLASS'
            LEFT JOIN TEACHER AS S_HOME_TEACHER ON S.HOME_TEACHER = S_HOME_TEACHER.TEACHER_CODE
            LEFT JOIN TEACHER AS S_DEAN ON S.DEAN = S_DEAN.TEACHER_CODE
            LEFT JOIN FAMILY AS S_FAMILY ON S.FAMILY_CODE = S_FAMILY.FAMKEY
            WHERE S.STUDKEY = :studentId
        ";

        $params = [
            'studentId' => $studentExternalId
        ];

        return [ $sql, $params ];
    }


    /**
     * @param array $rows
     * @return array
     */
    public function processGetStudentResult(array $rows): array
    {
        return $rows[0] ?? [];
    }


}

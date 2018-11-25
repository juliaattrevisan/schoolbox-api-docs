<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Pcschools\QueryHelper;

class Freedom implements IQueryHelper
{
    private const CONTACT_LEVEL_FULL = 'I';
    private const CONTACT_LEVEL_RESTRICTED = 'A';
    private const CONTACT_LEVEL_NONE = '';

    private const RELATION_CHILD = 'C';

    private const FAKE_RECORD_NAME = 'Second Email';


    /**
     * @return array
     */
    public function getFindAllParentsQuery(): array
    {
        $sql = "
            SELECT
            I.LOGIN                             AS student_contact_network_login,
            CONVERT(varchar(10), I.MEMBER#)     AS student_contact_id,
            ''                                  AS student_contact_title,
            CONVERT(varchar(20), I.GIVENNAME)   AS student_contact_given_name,
            CONVERT(varchar(20), I.GIVENNAME)   AS student_contact_preferred,
            CONVERT(varchar(20), I.SURNAME)     AS student_contact_surname,
            I.EMAIL                             AS student_contact_work_email,
            I.EMAIL_PERSONAL                    AS student_contact_home_email,
            S.STUDKEY                           AS student_id,
            S.CURRENT_YEAR                      AS student_year_level,
            S.CAMPUS                            AS student_campus,
            S.HOUSE                             AS student_house,
            S.SEX                               AS student_gender,
            S.GIVEN_NAME                        AS student_preferred,
            S.SURNAME                           AS student_surname
            FROM dbo.[IDENTITY] AS I
            INNER JOIN dbo.ALUMREL AS A ON A.PARENT# = I.MEMBER#
            INNER JOIN dbo.STUDENT AS S ON S.STUDENT# = A.CHILD#
            WHERE (A.RELATION_STAT = :relationStatus)
            AND (S.CURRENT_YEAR BETWEEN ' 5' AND '12')
            AND (A.PARENT# <> A.FAMILY_HASH) and (I.DECEASED = ' ')
            AND (A.STUDENT_PREF = :contactLevel)
            AND (givenname <> :fakeRecordName)
            ORDER BY I.MEMBER# ASC;
        ";

        $params = [
            'relationStatus' => self::RELATION_CHILD,
            'contactLevel'   => self::CONTACT_LEVEL_FULL,
            'fakeRecordName' => self::FAKE_RECORD_NAME
        ];

        return [ $sql, $params ];
    }


    /**
     * @param string $externalUserId
     * @return array
     */
    public function getGetEnrolledStudentsQuery($externalUserId): array
    {
        $sql = "
            SELECT S.STUDKEY AS RelatedID
            FROM dbo.[IDENTITY] AS I
            INNER JOIN dbo.ALUMREL AS A ON A.PARENT# = I.MEMBER#
            INNER JOIN dbo.STUDENT AS S ON S.STUDENT# = A.CHILD#
            WHERE (I.MEMBER# = :externalId)
            AND (A.RELATION_STAT = :relationStatus)
            AND (S.CURRENT_YEAR BETWEEN ' 5' AND '12')
            AND (A.PARENT# <> A.FAMILY_HASH) and (I.DECEASED = ' ')
            AND (A.STUDENT_PREF = :contactLevel)
            AND (givenname <> :fakeRecordName);
        ";

        $params = [
            'externalId'     => $externalUserId,
            'relationStatus' => self::RELATION_CHILD,
            'contactLevel'   => self::CONTACT_LEVEL_FULL,
            'fakeRecordName' => self::FAKE_RECORD_NAME
        ];

        return [ $sql, $params ];
    }


    /**
     * @param array $studentExternalIds
     * @return array
     */
    public function getGetParentEmailsQuery($studentExternalIds): array
    {
        $sql = "
            SELECT
            CONVERT(varchar(10), I.MEMBER#)     AS IDENTITY_ID#,
            I.EMAIL                             AS EMAIL
            FROM dbo.[IDENTITY] AS I
            INNER JOIN dbo.ALUMREL AS A ON A.PARENT# = I.MEMBER#
            INNER JOIN dbo.STUDENT AS S ON S.STUDENT# = A.CHILD#
            WHERE (A.RELATION_STAT = :relationStatus)
            AND (S.CURRENT_YEAR BETWEEN ' 5' AND '12')
            AND (A.PARENT# <> A.FAMILY_HASH) and (I.DECEASED = ' ')
            AND (A.STUDENT_PREF = :contactLevel)
            AND (givenname <> :fakeRecordName)
        ";

        $params = [
            'relationStatus' => self::RELATION_CHILD,
            'contactLevel'   => self::CONTACT_LEVEL_FULL,
            'fakeRecordName' => self::FAKE_RECORD_NAME
        ];

        return [ $sql, $params ];
    }


    /**
     * @param string $studentExternalId
     * @return array
     */
    public function getGetStudentContactListQuery($studentExternalId): array
    {
        $sql = "
            SELECT
            CONVERT(varchar(10), I.MEMBER#)     AS StudentContactID,
            ''                                  AS StudentContactTitle,
            CONVERT(varchar(20), I.GIVENNAME)   AS StudentContactPreferred,
            CONVERT(varchar(20), I.SURNAME)     AS StudentContactSurname,
            I.EMAIL                             AS StudentContactDefaultEmail,
            I.MOBILE                            AS StudentContactMobilePhone,
            I.BUS_PHONE                         AS StudentContactOccupPhone,
            I.HOME_PHONE                        AS StudentContactPhoneActual,
                                             '' AS StudentContactMobilePhone,
                                             '' AS StudentContactOccupPhone,
                                             '' AS StudentContactRelation,
                                             '' AS StudentContactSpouseID,
                                             '' AS StudentContactSpouseTitle,
                                             '' AS StudentContactSpousePreferred,
                                             '' AS StudentContactSpouseSurname,
                                             '' AS StudentContactSpouseMobilePhone,
                                             '' AS StudentContactSpouseOccupPhone,
                                             '' AS StudentContactSpouseDefaultEmail,
                                             '' AS StudentContactType,
                                             '' AS StudentContactSpouseRelation
            FROM dbo.[IDENTITY] AS I
            INNER JOIN dbo.ALUMREL AS A ON A.PARENT# = I.MEMBER#
            INNER JOIN dbo.STUDENT AS S ON S.STUDENT# = A.CHILD#
            WHERE (A.RELATION_STAT = :relationStatus)
            AND (S.CURRENT_YEAR BETWEEN ' 5' AND '12')
            AND (A.PARENT# <> A.FAMILY_HASH) and (I.DECEASED = ' ')
            AND (A.STUDENT_PREF = :contactLevel)
            AND (givenname <> :fakeRecordName)
            AND (S.STUDKEY = :studentId)
            ORDER BY I.MEMBER# ASC;
        ";

        $params = [
            'relationStatus' => self::RELATION_CHILD,
            'contactLevel'   => self::CONTACT_LEVEL_FULL,
            'fakeRecordName' => self::FAKE_RECORD_NAME,
            'studentId'      => $studentExternalId
        ];

        return [ $sql, $params ];
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
                S.STUDKEY                       AS StudentID,
                S.GIVEN_NAME                    AS StudentGiven1,
                S.LEGAL_SURNAME                 AS StudentSurname,
                S.BIRTH_DATE                    AS StudentBirthDate,
                S_HOUSE.DESCRIPTION             AS StudentHouseDescription,
                S.HOUSE                         AS StudentHouse,
                ''                              AS HeadOfHouseID,
                ''                              AS StudentHeadOfHouse,
                S.STATUS                        AS StudentBoarder,
                ''                              AS Comment,
                ''                              AS StudentEnrolmentDate,
                ''                              AS StudentLeavingDate,
                ''                              AS StudentIBFlag,
                S.HOME_CLASS                    AS StudentForm,
                S_CLASS.DESCRIPTION             AS FormDescription,
                S_HOME_TEACHER.NAME_FOR_REPORT  AS StudentFormStaffName,
                S.DEAN                          AS StudentTutor,
                S_HOME_TEACHER.NAME_FOR_REPORT  AS StudentTutorName,
                S_DEAN.NAME_FOR_REPORT          AS StudentTutorDescription,
                S.HOME_TEACHER                  AS TeacherFormID,
                S.DEAN                          AS TeacherTutorID,
                S.CURRENT_YEAR                  AS StudentYearLevel,
                S.DEAN                          AS YearLevelCoordID,
                ''                              AS StudentYearLevelCoordinator,
                S.LOCKER                        AS StudentLockerBook,
                S.EMERG_CONTACT                 AS StudentEmergencyName,
                S.EMERG_PHONE                   AS StudentEmergencyPhone,
                ''                              AS StudentEmergencyPhoneAlt,

                -- emerghergency contact details
                EC.GIVENNAME + ' ' + EC.SURNAME AS StudentEmergencyName,
                EC.HOME_PHONE                   AS StudentEmergencyPhone,
                EC.MOBILE                       AS StudentEmergencyPhoneAlt,

                -- alerts
                S.ALERT                         AS StudentMedicalAlert,
                ''                              AS StudentMobilePhone,
                ''                              AS StudentSpecialNeedsFlag,
                ''                              AS StudentStudiesCode,
                S.ALERT                         AS AlertMessage,
                0                               AS countIndividualEducationPlans
            FROM STUDENT S
            LEFT JOIN CODEMAST AS S_HOUSE ON S.HOUSE = S_HOUSE.CODE AND S_HOUSE.TYPE = 'HOUSE'
            LEFT JOIN CODEMAST AS S_CLASS ON S.HOME_CLASS = S_CLASS.CODE AND S_CLASS.TYPE = 'CLASS'
            LEFT JOIN TEACHER AS S_HOME_TEACHER ON S.HOME_TEACHER = S_HOME_TEACHER.TEACHER_CODE
            LEFT JOIN TEACHER AS S_DEAN ON S.DEAN = S_DEAN.TEACHER_CODE
            LEFT JOIN ALUMREL AS A ON A.CHILD# = S.STUDENT#
            LEFT JOIN [IDENTITY] EC
              ON A.PARENT# = EC.MEMBER#
              AND A.EMERGENCY_PREF = :contactLevel
              AND A.STUDENT_PREF = :contactLevel
              AND A.RELATION_STAT = :relationStatus
              AND givenname <> :fakeRecordName
              AND (A.PARENT# <> A.FAMILY_HASH)
              AND EC.DECEASED = ' '
            WHERE S.STUDKEY = :studentId
            AND S.CURRENT_YEAR BETWEEN ' 5' AND '12'
            ORDER BY A.CAREGIVER_NO ASC
        ";

        $params = [
            'relationStatus' => self::RELATION_CHILD,
            'contactLevel'   => self::CONTACT_LEVEL_FULL,
            'fakeRecordName' => self::FAKE_RECORD_NAME,
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
        $row = $rows[0] ?? [];

        if (!\count($row)) {
            return [];
        }

        $pfx    = 'StudentEmergency';
        $fields = [ 'Name', 'Phone', 'PhoneAlt' ];
        $index  = 0;

        // emergency contacts need to be squashed into a single row
        foreach ($rows as $i => $ec) {

            if ('' === $ec["{$pfx}Name"]) {
                // skip the first record because it's already populated
                // and doesn't get a number in the fieldname
                continue;
            }

            // turn the second instance of "StudentEmergencyName" into "StudentEmergency2Name"
            $index++;

            $num = $index === 1 ? '' : $index;

            // copy the emergency contact details to the expected location
            foreach ($fields as $field) {
                $row["{$pfx}{$num}{$field}"] = $ec["{$pfx}{$field}"];
            }
        }

        return $row;
    }

}

<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace External\Pcschools\QueryHelper;

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
            S.LEGAL_SURNAME AS student_surname
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
            WHERE (S.DATE_DEPART IS NULL OR DATEDIFF(DAY, S.DATE_DEPART, '1753-01-01') = 0)
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
            WHERE (S.DATE_DEPART IS NULL OR DATEDIFF(DAY, S.DATE_DEPART, '1753-01-01') = 0)
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
            WHERE (S.DATE_DEPART IS NULL OR DATEDIFF(DAY, S.DATE_DEPART, '1753-01-01') = 0)
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
            WHERE (S.DATE_DEPART IS NULL OR DATEDIFF(DAY, S.DATE_DEPART, '1753-01-01') = 0)
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
     * @param int $externalUserId
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

        switch ($suffix) {
            case 'F':
                $query .= ' AND S_FAMILY.DAD_LMS = :contactLevel';
                break;
            case 'M':
                $query .= ' AND S_FAMILY.MUM_LMS = :contactLevel';
                break;
            case 'C':
                $query .= ' AND S_FAMILY.CGIVE_LMS = :contactLevel';
                break;
            case 'G':
                $query .= ' AND S_FAMILY.GUARD_LMS = :contactLevel';
                break;
            default:
                break;
        }

        $params = [
            'familyCode'   => $familyCode,
            'contactLevel' => self::CONTACT_LEVEL_FULL
        ];

        return [ $query, $params ];
    }


    /**
     * @param array $studentExternalIds
     * @return array
     */
    public function getGetParentEmailsQuery($studentExternalIds): array
    {
        $query = "
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
        ";

        return [ $query, [] ];
    }


}
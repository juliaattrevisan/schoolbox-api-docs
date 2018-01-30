<?php
/**
 * Pcschools implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Pcschools\Base;

use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\ISync;
use Schoolbox\Sync\Iterator;

class Sync extends Base implements ISync
{
    const CONTACT_TYPE_FATHER    = 'F';
    const CONTACT_TYPE_MOTHER    = 'M';
    const CONTACT_TYPE_GUARDIAN  = 'G';
    const CONTACT_TYPE_CAREGIVER = 'C';

    const CONTACT_LEVEL_FULL           = 'F';
    const CONTACT_LEVEL_NONE           = 'N';
    const CONTACT_LEVEL_NO_DEMOGRAPHIC = 'D';

    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        $query = "
            SELECT
                S.STUDKEY       AS student_id,
                ID.LOGIN        AS network_login,
                ''              AS student_title,
                S.GIVEN_NAME    AS student_given_name,
                CASE
                WHEN
                    S.KNOWN_AS_NAME != ''
                THEN S.KNOWN_AS_NAME
                ELSE S.GIVEN_NAME
                END             AS student_preferred,
                S.LEGAL_SURNAME AS student_surname,
                S.SEX           AS student_gender,
                ''              AS student_home_email,
                S.EMAIL         AS student_default_email,
                ''              AS student_work_email,
                S.CURRENT_YEAR  AS student_year_level,
                S.HOUSE         AS student_house,
                ''              AS student_boarder,
                ''              AS student_boarding_house,
                S.CAMPUS        AS student_campus
            FROM STUDENT S
            LEFT JOIN [IDENTITY] AS ID ON S.STUDENT# = ID.MEMBER#
            WHERE (S.DATE_DEPART IS NULL OR DATEDIFF(DAY, S.DATE_DEPART, '1753-01-01') = 0)
            ORDER BY S.STUDKEY ASC
        ";

        return new Iterator\Student(
            $this->databaseExternal->getQueryIterator($query)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
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

        $query = "
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

        return new Iterator\Guardian(
            $this->databaseExternal->getQueryIterator(
                $query,
                [
                    'suffixF'      => self::CONTACT_TYPE_FATHER,
                    'suffixM'      => self::CONTACT_TYPE_FATHER,
                    'suffixC'      => self::CONTACT_TYPE_CAREGIVER,
                    'suffixG'      => self::CONTACT_TYPE_GUARDIAN,
                    'contactLevel' => self::CONTACT_LEVEL_FULL,
                ]
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $query = "
            SELECT
                T.TEACHER_CODE   AS staff_id,
                ID.LOGIN         AS network_login,
                ''               AS active_flag,
                T.SALUTATION     AS staff_title,
                T.GIVEN_NAMES    AS staff_given_name,
                CASE
                WHEN
                    T.KNOWNAS != ''
                THEN T.KNOWNAS
                ELSE T.GIVEN_NAMES
                END              AS staff_preferred_name,
                T.SURNAME        AS staff_surname,
                ''               AS default_email_code,
                ''               AS home_email,
                T.EMAIL          AS default_email,
                ''               AS work_email,
                T.CAMPUS         AS staff_campus,
                T.CLASSIFICATION AS staff_category_type,
                T.STATUS         AS staff_category,
                T.HOUSE_T        AS staff_house,
                ''               AS staff_department,
                ''               AS staff_year_level,
                ''               AS staff_year_level_description
            FROM TEACHER T
            LEFT JOIN [IDENTITY] AS ID ON T.TEACHER# = ID.MEMBER#
            WHERE (T.DATE_LEFT IS NULL OR DATEDIFF(DAY, T.DATE_LEFT, '1753-01-01') = 0)
            ORDER BY T.TEACHER_CODE ASC
        ";

        return new Iterator\Staff(
            $this->databaseExternal->getQueryIterator($query)
        );
    }
}

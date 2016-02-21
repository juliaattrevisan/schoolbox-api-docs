<?php
/**
 * Pcschools implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Pcschools\Base;

use MSSQL;
use Schoolbox\External\Connector\SyncInterface;
use Schoolbox\Sync\Iterator;

class Sync implements SyncInterface
{
    /**
     * @var MSSQL
     */
    private $database;

    /**
     * Sync constructor
     */
    public function __construct()
    {
        $this->database = MSSQL::getInstance();
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        $query = "
            SELECT
                S.STUDKEY       AS student_id,
                ''              AS network_login,
                ''              AS student_title,
                S.GIVEN_NAME    AS student_preferred,
                S.LEGAL_SURNAME AS student_surname,
                S.SEX           AS student_gender,
                S.EMAIL         AS student_email,
                ''              AS student_occup_email,
                S.CURRENT_YEAR  AS student_year_level,
                S.HOUSE         AS student_house,
                ''              AS student_boarder,
                ''              AS student_boarding_house,
                S.CAMPUS        AS student_campus
            FROM STUDENT S
            WHERE (S.DATE_DEPART IS NULL OR DATEDIFF(DAY, S.DATE_DEPART, '1753-01-01') = 0)
            ORDER BY S.STUDKEY ASC
        ";
        return new Iterator\Student($this->database->getIterator($query));
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
            S.SEX           AS student_gender,
            S.GIVEN_NAME    AS student_preferred,
            S.LEGAL_SURNAME AS student_surname
        ";
        $query = "
            SELECT
                ''              AS student_contact_network_login,
                F.FAMKEY + '-F' AS student_contact_id,
                ''              AS student_contact_title,
                F.DAD_FIRSTNAME AS student_contact_preferred,
                F.DAD_SURNAME   AS student_contact_surname,
                F.DAD_EMAIL     AS student_contact_default_email,
                $childSelect
            FROM FAMILY F
            INNER JOIN STUDENT S ON F.FAMKEY = S.FAMILY_CODE
            WHERE (S.DATE_DEPART IS NULL OR DATEDIFF(DAY, S.DATE_DEPART, '1753-01-01') = 0)
            AND   F.DAD_DOMICILE = 'Y'

            UNION ALL

            SELECT
                ''              AS student_contact_network_login,
                F.FAMKEY + '-M' AS student_contact_id,
                ''              AS student_contact_title,
                F.MUM_FIRSTNAME AS student_contact_preferred,
                F.MUM_SURNAME   AS student_contact_surname,
                F.MUM_EMAIL     AS student_contact_default_email,
                $childSelect
            FROM FAMILY F
            INNER JOIN STUDENT S ON F.FAMKEY = S.FAMILY_CODE
            WHERE (S.DATE_DEPART IS NULL OR DATEDIFF(DAY, S.DATE_DEPART, '1753-01-01') = 0)
            AND   F.MUM_DOMICILE = 'Y'

            UNION ALL

            SELECT
                ''                AS student_contact_network_login,
                F.FAMKEY + '-C'   AS student_contact_id,
                ''                AS student_contact_title,
                F.CGIVE_FIRSTNAME AS student_contact_preferred,
                F.CGIVE_SURNAME   AS student_contact_surname,
                F.CGIVE_EMAIL     AS student_contact_default_email,
                $childSelect
            FROM FAMILY F
            INNER JOIN STUDENT S ON F.FAMKEY = S.FAMILY_CODE
            WHERE (S.DATE_DEPART IS NULL OR DATEDIFF(DAY, S.DATE_DEPART, '1753-01-01') = 0)
            AND   F.CGIVE_DOMICILE = 'Y'

            UNION ALL

            SELECT
                ''                AS student_contact_network_login,
                F.FAMKEY + '-G'   AS student_contact_id,
                ''                AS student_contact_title,
                F.GUARD_FIRSTNAME AS student_contact_preferred,
                F.GUARD_SURNAME   AS student_contact_surname,
                F.GUARD_EMAIL     AS student_contact_default_email,
                $childSelect
            FROM FAMILY F
            INNER JOIN STUDENT S ON F.FAMKEY = S.FAMILY_CODE
            WHERE (S.DATE_DEPART IS NULL OR DATEDIFF(DAY, S.DATE_DEPART, '1753-01-01') = 0)
            AND   F.GUARD_DOMICILE = 'Y'

            ORDER BY student_contact_id ASC
        ";
        return new Iterator\Guardian($this->database->getIterator($query));
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $query = "
            SELECT
                T.TEACHER_CODE   AS staff_id,
                ''               AS network_login,
                ''               AS active_flag,
                T.SALUTATION     AS staff_title,
                T.GIVEN_NAMES    AS staff_preferred_name,
                T.SURNAME        AS staff_surname,
                ''               AS default_email_code,
                ''               AS occup_email,
                T.EMAIL          AS email,
                T.CAMPUS         AS staff_campus,
                T.CLASSIFICATION AS staff_category_type,
                ''               AS staff_category,
                ''               AS staff_department
            FROM TEACHER T
            WHERE (T.DATE_LEFT IS NULL OR DATEDIFF(DAY, T.DATE_LEFT, '1753-01-01') = 0)
            ORDER BY T.TEACHER_CODE ASC
        ";
        return new Iterator\Staff($this->database->getIterator($query));
    }
}
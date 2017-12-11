<?php
/**
 * Edumate implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Edumate\Base;

use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\ISync;
use Schoolbox\Sync\Iterator;

class Sync extends Base implements ISync
{
    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        $query = '
            SELECT
            STUDENT_NUMBER AS "student_id",
            USERNAME       AS "network_login",
            NULL           AS "student_title",
            FIRSTNAME      AS "student_given_name",
            FIRSTNAME      AS "student_preferred",
            SURNAME        AS "student_surname",
            NULL           AS "student_home_email",
            EMAIL_ADDRESS  AS "student_default_email",
            NULL           AS "student_work_email",
            YEAR_LEVEL     AS "student_year_level",
            HOUSE          AS "student_house",
            NULL           AS "student_boarder",
            NULL           AS "student_boarding_house",
            NULL           AS "student_campus"
            FROM db2inst1.schoolbox_students
        ';

        return new Iterator\Student(
            $this->databaseExternal->getQueryIterator($query)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        $query = '
            SELECT
                p.USERNAME               AS "student_contact_network_login",
                p.CARER_NUMBER           AS "student_contact_id",
                NULL                     AS "student_contact_title",
                p.FULL_NAME              AS "student_contact_given_name",
                p.FULL_NAME              AS "student_contact_preferred",
                NULL                     AS "student_contact_surname",
                NULL                     AS "student_contact_home_email",
                p.COMBINED_EMAIL_ADDRESS AS "student_contact_default_email",
                NULL                     AS "student_contact_work_email",
                s.STUDENT_NUMBER         AS "student_id",
                s.YEAR_LEVEL             AS "student_year_level",
                NULL                     AS "student_campus",
                s.HOUSE                  AS "student_house",
                NULL                     AS "student_gender",
                s.FIRSTNAME              AS "student_preferred",
                s.SURNAME                AS "student_surname"
            FROM db2inst1.schoolbox_parents AS p
            INNER JOIN db2inst1.schoolbox_parent_student
                AS ps ON p.CARER_NUMBER = ps.CARER_NUMBER
            INNER JOIN db2inst1.schoolbox_students
                AS s ON ps.STUDENT_NUMBER = s.STUDENT_NUMBER
            WHERE p.CARER_NUMBER IS NOT NULL

            UNION ALL

            SELECT
                p.SPOUSE_USERNAME        AS "student_contact_network_login",
                p.SPOUSE_CARER_NUMBER    AS "student_contact_id",
                NULL                     AS "student_contact_title",
                p.SPOUSE_FULL_NAME       AS "student_contact_given_name",
                p.SPOUSE_FULL_NAME       AS "student_contact_preferred",
                NULL                     AS "student_contact_surname",
                NULL                     AS "student_contact_home_email",
                p.SPOUSE_EMAIL           AS "student_contact_default_email",
                NULL                     AS "student_contact_work_email",
                s.STUDENT_NUMBER         AS "student_id",
                s.YEAR_LEVEL             AS "student_year_level",
                NULL                     AS "student_campus",
                s.HOUSE                  AS "student_house",
                NULL                     AS "student_gender",
                s.FIRSTNAME              AS "student_preferred",
                s.SURNAME                AS "student_surname"
            FROM db2inst1.schoolbox_parents AS p
            INNER JOIN db2inst1.schoolbox_parent_student
                AS ps ON p.CARER_NUMBER = ps.CARER_NUMBER
            INNER JOIN db2inst1.schoolbox_students
                AS s ON ps.STUDENT_NUMBER = s.STUDENT_NUMBER
            WHERE p.SPOUSE_CARER_NUMBER IS NOT NULL
        ';

        return new Iterator\Guardian(
            $this->databaseExternal->getQueryIterator($query)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $query = '
            SELECT
                STAFF_NUMBER  AS "staff_id",
                USERNAME      AS "network_login",
                1             AS "active_flag",
                SALUTATION    AS "staff_title",
                FIRSTNAME     AS "staff_given_name",
                FIRSTNAME     AS "staff_preferred_name",
                SURNAME       AS "staff_surname",
                NULL          AS "default_email_code",
                NULL          AS "home_email",
                EMAIL_ADDRESS AS "default_email",
                NULL          AS "work_email",
                CAMPUS        AS "staff_campus",
                NULL          AS "staff_category_type",
                NULL          AS "staff_category",
                NULL          AS "staff_house",
                NULL          AS "staff_department",
                NULL          AS "staff_year_level",
                NULL          AS "staff_year_level_description"                
                
            FROM db2inst1.schoolbox_staff
            WHERE USERNAME IS NOT NULL
        ';

        return new Iterator\Staff(
            $this->databaseExternal->getQueryIterator($query)
        );
    }
}

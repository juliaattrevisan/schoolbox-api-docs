<?php
/**
 * Edumate implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
            FROM SCHOOLBOX_STUDENTS
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
                P.USERNAME               AS "student_contact_network_login",
                P.CARER_NUMBER           AS "student_contact_id",
                NULL                     AS "student_contact_title",
                P.FULL_NAME              AS "student_contact_given_name",
                P.FULL_NAME              AS "student_contact_preferred",
                NULL                     AS "student_contact_surname",
                NULL                     AS "student_contact_home_email",
                P.EMAIL                  AS "student_contact_default_email",
                NULL                     AS "student_contact_work_email",
                S.STUDENT_NUMBER         AS "student_id",
                S.YEAR_LEVEL             AS "student_year_level",
                NULL                     AS "student_campus",
                S.HOUSE                  AS "student_house",
                NULL                     AS "student_gender",
                S.FIRSTNAME              AS "student_preferred",
                S.SURNAME                AS "student_surname"
            FROM SCHOOLBOX_PARENTS AS P
            INNER JOIN SCHOOLBOX_PARENT_STUDENT
                AS PS ON P.CARER_NUMBER = PS.CARER_NUMBER
            INNER JOIN SCHOOLBOX_STUDENTS
                AS S ON PS.STUDENT_NUMBER = S.STUDENT_NUMBER
            WHERE P.CARER_NUMBER IS NOT NULL
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

            FROM SCHOOLBOX_STAFF
            WHERE USERNAME IS NOT NULL
        ';

        return new Iterator\Staff(
            $this->databaseExternal->getQueryIterator($query)
        );
    }
}

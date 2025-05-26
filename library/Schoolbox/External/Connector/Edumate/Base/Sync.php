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
            STUDENT_NUMBER AS STUDENT_ID,
            USERNAME       AS NETWORK_LOGIN,
            NULL           AS STUDENT_TITLE,
            FIRSTNAME      AS STUDENT_GIVEN_NAME,
            FIRSTNAME      AS STUDENT_PREFERRED,
            SURNAME        AS STUDENT_SURNAME,
            NULL           AS STUDENT_HOME_EMAIL,
            EMAIL_ADDRESS  AS STUDENT_DEFAULT_EMAIL,
            NULL           AS STUDENT_WORK_EMAIL,
            YEAR_LEVEL     AS STUDENT_YEAR_LEVEL,
            FORM_NAME      AS STUDENT_FORM_DESCRIPTION,
            HOUSE          AS STUDENT_HOUSE,
            NULL           AS STUDENT_BOARDER,
            NULL           AS STUDENT_BOARDING_HOUSE,
            NULL           AS STUDENT_CAMPUS
            FROM SCHOOLBOX_STUDENTS
        ';

        return new Iterator\Student(
            new ResultIterator($this->databaseExternal->getQueryIterator($query)),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        $query = '
            SELECT
                P.USERNAME               AS STUDENT_CONTACT_NETWORK_LOGIN,
                P.CARER_NUMBER           AS STUDENT_CONTACT_ID,
                NULL                     AS STUDENT_CONTACT_TITLE,
                P.FIRST_NAME             AS STUDENT_CONTACT_GIVEN_NAME,
                P.PREFERRED_NAME         AS STUDENT_CONTACT_PREFERRED,
                P.SURNAME                AS STUDENT_CONTACT_SURNAME,
                NULL                     AS STUDENT_CONTACT_HOME_EMAIL,
                P.EMAIL                  AS STUDENT_CONTACT_DEFAULT_EMAIL,
                NULL                     AS STUDENT_CONTACT_WORK_EMAIL,
                S.STUDENT_NUMBER         AS STUDENT_ID,
                S.YEAR_LEVEL             AS STUDENT_YEAR_LEVEL,
                S.FORM_NAME              AS STUDENT_FORM_DESCRIPTION,
                NULL                     AS STUDENT_CAMPUS,
                S.HOUSE                  AS STUDENT_HOUSE,
                NULL                     AS STUDENT_GENDER,
                S.FIRSTNAME              AS STUDENT_PREFERRED,
                S.SURNAME                AS STUDENT_SURNAME
            FROM SCHOOLBOX_PARENTS AS P
            INNER JOIN SCHOOLBOX_PARENT_STUDENT
                AS PS ON P.CARER_NUMBER = PS.CARER_NUMBER
            INNER JOIN SCHOOLBOX_STUDENTS
                AS S ON PS.STUDENT_NUMBER = S.STUDENT_NUMBER
            WHERE P.CARER_NUMBER IS NOT NULL
            ORDER BY P.CARER_NUMBER
        ';

        return new Iterator\Guardian(
            new ResultIterator($this->databaseExternal->getQueryIterator($query)),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $query = '
            SELECT
                STAFF_NUMBER  AS STAFF_ID,
                USERNAME      AS NETWORK_LOGIN,
                1             AS ACTIVE_FLAG,
                SALUTATION    AS STAFF_TITLE,
                FIRSTNAME     AS STAFF_GIVEN_NAME,
                FIRSTNAME     AS STAFF_PREFERRED_NAME,
                SURNAME       AS STAFF_SURNAME,
                NULL          AS DEFAULT_EMAIL_CODE,
                NULL          AS HOME_EMAIL,
                EMAIL_ADDRESS AS DEFAULT_EMAIL,
                NULL          AS WORK_EMAIL,
                CAMPUS        AS STAFF_CAMPUS,
                NULL          AS STAFF_CATEGORY_TYPE,
                NULL          AS STAFF_CATEGORY,
                NULL          AS STAFF_HOUSE,
                NULL          AS STAFF_DEPARTMENT,
                NULL          AS STAFF_YEAR_LEVEL,
                NULL          AS STAFF_YEAR_LEVEL_DESCRIPTION,
                NULL          AS STAFF_POSITION_CODE,
                NULL          AS STAFF_POSITION
            FROM SCHOOLBOX_STAFF
            WHERE USERNAME IS NOT NULL
        ';

        return new Iterator\Staff(
            new ResultIterator($this->databaseExternal->getQueryIterator($query)),
        );
    }
}

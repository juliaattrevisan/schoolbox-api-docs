<?php
/**
 * Custom implementation of sync connector for CAS
 *
 * @author    Sam Bailey <sam.bailey@alaress.com.au>
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Maze\Cas;

use Schoolbox\External\Connector\Maze\Base\Sync as BaseSync;
use Schoolbox\Sync\Iterator;

class Sync extends BaseSync
{
    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        $query = "
            SELECT
                STKEY      AS student_id,
                USER_NAME  AS network_login,
                NULL       AS student_title,
                FIRST_NAME AS student_given_name,
                FIRST_NAME AS student_preferred,
                SURNAME    AS student_surname,
                NULL       AS student_home_email,
                NULL       AS student_default_email,
                NULL       AS student_work_email,
                REPLACE(REPLACE(ST.SCHOOL_YEAR, 'Y0', ''), 'Y', '')
                           AS student_year_level,
                HOUSE      AS student_house,
                BOARDER    AS student_boarder,
                NULL       AS student_boarding_house,
                CAMPUS     AS student_campus
            FROM ST
            WHERE STATUS != 'LEFT'
        ";
        return new Iterator\Student(
            $this->databaseExternal->executeQueryIterator($query)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $query = "
            SELECT
                SFKEY      AS staff_id,
                USER_NAME  AS network_login,
                STAFF_TYPE AS active_flag,
                NULL       AS staff_title,
                FIRST_NAME AS staff_given_name,
                FIRST_NAME AS staff_preferred_name,
                SURNAME    AS staff_surname,
                NULL       AS home_email,
                SF_EMAIL   AS default_email,
                NULL       AS work_email,
                CAMPUS     AS staff_campus,
                NULL       AS staff_category_type,
                NULL       AS staff_category,
                ''         AS staff_house,
                NULL       AS staff_department,
                ''         AS staff_year_level,
                ''         AS staff_year_level_description

            FROM SF
            WHERE FINISH IS NULL
            AND STAFF_TYPE != 'L'
        ";

        return new Iterator\Staff(
            $this->databaseExternal->executeQueryIterator($query)
        );
    }
}

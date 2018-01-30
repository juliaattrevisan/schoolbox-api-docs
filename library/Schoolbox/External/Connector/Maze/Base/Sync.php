<?php
/**
 * MAZE implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Maze\Base;

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
            $this->databaseExternal->getQueryIterator($query)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        // TODO this spouse student_contact_id won't work, as getEnrolledStudents doesn't support it
        $query = "
            SELECT
                NULL          AS student_contact_network_login,
                DF.DFKEY      AS student_contact_id,
                DF.FTITLE     AS student_contact_title,
                DF.FNAME      AS student_contact_given_name,
                DF.FNAME      AS student_contact_preferred,
                DF.FSURNAME   AS student_contact_surname,
                NULL          AS student_contact_home_email,
                DF.F_EMAIL    AS student_contact_default_email,
                NULL          AS student_contact_work_email,
                ST.STKEY      AS student_id,
                REPLACE(REPLACE(ST.SCHOOL_YEAR, 'Y0', ''), 'Y', '')
                              AS student_year_level,
                ST.CAMPUS     AS student_campus,
                ST.HOUSE      AS student_house,
                ST.FIRST_NAME AS student_preferred,
                ST.SURNAME    AS student_surname
            FROM ST
            LEFT JOIN DF ON (ST.FAMILY = DF.DFKEY OR ST.FAMB = DF.DFKEY)

            UNION ALL

            -- Speese
            SELECT
                NULL           AS student_contact_network_login,
                (DF.DFKEY+'1') AS student_contact_id,
                DF.MTITLE      AS student_contact_title,
                DF.MNAME       AS student_contact_given_name,
                DF.MNAME       AS student_contact_preferred,
                DF.MSURNAME    AS student_contact_surname,
                NULL           AS student_contact_home_email,
                DF.M_EMAIL     AS student_contact_default_email,
                NULL           AS student_contact_work_email,
                ST.STKEY       AS student_id,
                REPLACE(REPLACE(ST.SCHOOL_YEAR, 'Y0', ''), 'Y', '')
                               AS student_year_level,
                ST.CAMPUS      AS student_campus,
                ST.HOUSE       AS student_house,
                ST.FIRST_NAME  AS student_preferred,
                ST.SURNAME     AS student_surname
            FROM ST
            LEFT JOIN DF ON (ST.FAMILY = DF.DFKEY OR ST.FAMB = DF.DFKEY)

            ORDER BY student_contact_id ASC
        ";

        return new Iterator\Guardian(
            $this->databaseExternal->getQueryIterator($query)
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
            $this->databaseExternal->getQueryIterator($query)
        );
    }
}

<?php
/**
 * MAZE implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Maze\Base;

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
                STKEY      AS student_id,
                USER_NAME  AS network_login,
                NULL       AS student_title,
                FIRST_NAME AS student_preferred,
                SURNAME    AS student_surname,
                NULL       AS student_occup_email,
                REPLACE(REPLACE(ST.SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS student_year_level,
                HOUSE      AS student_house,
                BOARDER    AS student_boarder,
                NULL       AS student_boarding_house,
                CAMPUS     AS student_campus
            FROM ST
            WHERE STATUS != 'LEFT'
        ";
        return new Iterator\Student($this->database->getIterator($query));
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        // TODO this spouse StudentContactID won't work, as getEnrolledStudents doesn't support it
        $query = "
            SELECT
                NULL          AS StudentContactNetworkLogin,
                DF.DFKEY      AS StudentContactID,
                DF.FTITLE     AS StudentContactTitle,
                DF.FNAME      AS StudentContactPreferred,
                DF.FSURNAME   AS StudentContactSurname,
                DF.F_EMAIL    AS StudentContactDefaultEmail,
                ST.STKEY      AS student_id,
                REPLACE(REPLACE(ST.SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS student_year_level,
                ST.CAMPUS     AS student_campus,
                ST.FIRST_NAME AS student_preferred,
                ST.SURNAME    AS student_surname
            FROM ST
            LEFT JOIN DF ON (ST.FAMILY = DF.DFKEY OR ST.FAMB = DF.DFKEY)

            UNION ALL

            -- Speese
            SELECT
                NULL           AS StudentContactNetworkLogin,
                (DF.DFKEY+'1') AS StudentContactID,
                DF.MTITLE      AS StudentContactTitle,
                DF.MNAME       AS StudentContactPreferred,
                DF.MSURNAME    AS StudentContactSurname,
                DF.M_EMAIL     AS StudentContactDefaultEmail,
                ST.STKEY       AS student_id,
                REPLACE(REPLACE(ST.SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS student_year_level,
                ST.CAMPUS      AS student_campus,
                ST.FIRST_NAME  AS student_preferred,
                ST.SURNAME     AS student_surname
            FROM ST
            LEFT JOIN DF ON (ST.FAMILY = DF.DFKEY OR ST.FAMB = DF.DFKEY)

            ORDER BY StudentContactID ASC
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
                SFKEY      AS StaffID,
                USER_NAME  AS network_login,
                STAFF_TYPE AS ActiveFlag,
                NULL       AS StaffTitle,
                FIRST_NAME AS StaffPreferredName,
                SURNAME    AS StaffSurname,
                NULL       AS DefaultEmailCode,
                NULL       AS OccupEmail,
                SF_EMAIL   AS Email,
                CAMPUS     AS StaffCampus,
                NULL       AS StaffCategoryType,
                NULL       AS StaffCategory,
                NULL       AS StaffDepartment
            FROM SF
            WHERE FINISH IS NULL
            AND STAFF_TYPE != 'L'
        ";
        return new Iterator\Staff($this->database->getIterator($query));
    }
}

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
                STKEY      AS StudentID,
                USER_NAME  AS NetworkLogin,
                NULL       AS StudentTitle,
                FIRST_NAME AS StudentPreferred,
                SURNAME    AS StudentSurname,
                NULL       AS StudentOccupEmail,
                REPLACE(REPLACE(ST.SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS StudentYearLevel,
                HOUSE      AS StudentHouse,
                BOARDER    AS StudentBoarder,
                NULL       AS StudentBoardingHouse,
                CAMPUS     AS StudentCampus
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
                ST.STKEY      AS StudentId,
                REPLACE(REPLACE(ST.SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS StudentYearLevel,
                ST.CAMPUS     AS StudentCampus,
                ST.FIRST_NAME AS StudentPreferred,
                ST.SURNAME    AS StudentSurname
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
                ST.STKEY       AS StudentId,
                REPLACE(REPLACE(ST.SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS StudentYearLevel,
                ST.CAMPUS      AS StudentCampus,
                ST.FIRST_NAME  AS StudentPreferred,
                ST.SURNAME     AS StudentSurname
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
                USER_NAME  AS NetworkLogin,
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

<?php
/**
 * Edumate implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Edumate\Base;

use DB2;
use External_Edumate_Connection;
use Schoolbox\External\Connector\SyncInterface;
use Schoolbox\Sync\Iterator;

class Sync implements SyncInterface
{
    /**
     * @var DB2
     */
    private $database;

    /**
     * Sync constructor
     */
    public function __construct()
    {
        $connection     = new External_Edumate_Connection();
        $this->database = $connection->getDatabase();
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        $stmt = $this->database->prepare('
            SELECT
            STUDENT_NUMBER AS "student_id",
            USERNAME       AS "NetworkLogin",
            NULL           AS "StudentTitle",
            FIRSTNAME      AS "StudentPreferred",
            SURNAME        AS "StudentSurname",
            EMAIL_ADDRESS  AS "StudentEmail",
            NULL           AS "StudentOccupEmail",
            YEAR_LEVEL     AS "StudentYearLevel",
            HOUSE          AS "StudentHouse",
            NULL           AS "StudentBoarder",
            NULL           AS "StudentBoardingHouse",
            NULL           AS "StudentCampus"
            FROM db2inst1.schoolbox_students
        ');
        $stmt->execute();
        return new Iterator\Student($stmt);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        $stmt = $this->database->prepare('
            SELECT
                NULL                     AS "StudentContactNetworkLogin",
                p.CARER_NUMBER           AS "StudentContactID",
                NULL                     AS "StudentContactTitle",
                p.FULL_NAME              AS "StudentContactPreferred",
                NULL                     AS "StudentContactSurname",
                p.COMBINED_EMAIL_ADDRESS AS "StudentContactDefaultEmail",
                s.STUDENT_NUMBER         AS "student_id",
                s.YEAR_LEVEL             AS "StudentYearLevel",
                NULL                     AS "StudentCampus",
                NULL                     AS "StudentGender",
                s.FIRSTNAME              AS "StudentPreferred",
                s.SURNAME                AS "StudentSurname"
            FROM db2inst1.schoolbox_parents AS p
            INNER JOIN db2inst1.schoolbox_parent_student
                AS ps ON p.CARER_NUMBER = ps.CARER_NUMBER
            INNER JOIN db2inst1.schoolbox_students
                AS s ON ps.STUDENT_NUMBER = s.STUDENT_NUMBER
            WHERE p.CARER_NUMBER IS NOT NULL

            UNION ALL

            SELECT
                NULL                     AS "StudentContactNetworkLogin",
                p.SPOUSE_CARER_NUMBER    AS "StudentContactID",
                NULL                     AS "StudentContactTitle",
                p.SPOUSE_FULL_NAME       AS "StudentContactPreferred",
                NULL                     AS "StudentContactSurname",
                p.SPOUSE_EMAIL           AS "StudentContactDefaultEmail",
                s.STUDENT_NUMBER         AS "student_id",
                s.YEAR_LEVEL             AS "StudentYearLevel",
                NULL                     AS "StudentCampus",
                NULL                     AS "StudentGender",
                s.FIRSTNAME              AS "StudentPreferred",
                s.SURNAME                AS "StudentSurname"
            FROM db2inst1.schoolbox_parents AS p
            INNER JOIN db2inst1.schoolbox_parent_student
                AS ps ON p.CARER_NUMBER = ps.CARER_NUMBER
            INNER JOIN db2inst1.schoolbox_students
                AS s ON ps.STUDENT_NUMBER = s.STUDENT_NUMBER
            WHERE p.SPOUSE_CARER_NUMBER IS NOT NULL

            ORDER BY INTEGER("StudentContactID") ASC, INTEGER("student_id") ASC
        ');
        $stmt->execute();
        return new Iterator\Guardian($stmt);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $stmt = $this->database->prepare('
            SELECT
                STAFF_NUMBER  AS "StaffID",
                USERNAME      AS "NetworkLogin",
                1             AS "ActiveFlag",
                SALUTATION    AS "StaffTitle",
                FIRSTNAME     AS "StaffPreferredName",
                SURNAME       AS "StaffSurname",
                NULL          AS "DefaultEmailCode",
                EMAIL_ADDRESS AS "OccupEmail",
                EMAIL_ADDRESS AS "Email",
                CAMPUS        AS "StaffCampus",
                NULL          AS "StaffCategoryType",
                NULL          AS "StaffCategory",
                NULL          AS "StaffDepartment"
            FROM db2inst1.schoolbox_staff
            WHERE USERNAME IS NOT NULL
        ');
        $stmt->execute();
        return new Iterator\Staff($stmt);
    }
}

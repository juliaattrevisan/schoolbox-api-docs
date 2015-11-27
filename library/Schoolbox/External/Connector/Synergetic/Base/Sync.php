<?php
/**
 * Synergetic implementation of sync connector.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Synergetic\Base;

use External_User;
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
     * @var string
     */
    private $year;

    /**
     * @var string
     */
    private $semester;

    /**
     * Sync constructor
     */
    public function __construct()
    {
        $this->database = MSSQL::getInstance();
        // Figure out the current year and semester
        $externalUser = new \External_User();
        $this->year     = $externalUser->getCurrentYear();
        $this->semester = $externalUser->getCurrentSemester();
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        $query = "
            SELECT
                vStudents.StudentID            AS student_id,
                NetworkLogin                   AS network_login,
                vStudents.StudentTitle         AS student_title,
                vStudents.StudentPreferred     AS student_preferred,
                vStudents.StudentSurname       AS student_surname,
                StudentGender                  AS student_gender,
                StudentEmail                   AS student_email,
                StudentOccupEmail              AS student_occup_email,
                vStudents.StudentYearLevel     AS student_year_level,
                vStudents.StudentHouse         AS student_house,
                vStudents.StudentBoarder       AS student_boarder,
                vStudents.StudentBoardingHouse AS student_boarding_house,
                StudentCampus                  AS student_campus
            FROM SynergyOne.dbo.vStudents vStudents
            LEFT JOIN Community ON Community.ID = StudentID
            WHERE
            FileSemester = '" . $this->database->quote($this->semester) . "'
            AND FileYear = '" . $this->database->quote($this->year) . "'
            ORDER BY vStudents.StudentID ASC
        ";
        return new Iterator\Student($this->database->getIterator($query));
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        $query = "
            SELECT DISTINCT
                cp.NetworkLogin              AS student_contact_network_login,
                s.StudentContactID           AS student_contact_id,
                s.StudentContactTitle        AS student_contact_title,
                s.StudentContactPreferred    AS student_contact_preferred,
                s.StudentContactSurname      AS student_contact_surname,
                s.StudentContactDefaultEmail AS student_contact_default_email,
                s.StudentId                  AS student_id,
                s.StudentYearLevel           AS student_year_level,
                s.StudentCampus              AS student_campus,
                s.StudentGender              AS student_gender,
                s.StudentPreferred           AS student_preferred,
                s.StudentSurname             AS student_surname
            FROM SynergyOne.dbo.vStudentContactAllAddress s
            INNER JOIN SynergyOne.dbo.Community cp ON s.StudentContactID = cp.ID
            WHERE (s.StudentContactType IN ('SC1', 'SC2'))
            AND   s.FileSemester = '" . $this->database->quote($this->semester) . "'
            AND   s.FileYear = '" . $this->database->quote($this->year) . "'

            UNION ALL

            SELECT DISTINCT
                cs.NetworkLogin                    AS student_contact_network_login,
                s.StudentContactSpouseID           AS student_contact_id,
                s.StudentContactSpouseTitle        AS student_contact_title,
                s.StudentContactSpousePreferred    AS student_contact_preferred,
                s.StudentContactSpouseSurname      AS student_contact_surname,
                s.StudentContactSpouseDefaultEmail AS student_contact_default_email,
                s.StudentId                        AS student_id,
                s.StudentYearLevel                 AS student_year_level,
                s.StudentCampus                    AS student_campus,
                s.StudentGender                    AS student_gender,
                s.StudentPreferred                 AS student_preferred,
                s.StudentSurname                   AS student_surname
            FROM SynergyOne.dbo.vStudentContactAllAddress s
            INNER JOIN SynergyOne.dbo.Community cs ON s.StudentContactSpouseID = cs.ID
            WHERE (s.StudentContactType IN ('SC1', 'SC2'))
            AND   s.FileSemester = '" . $this->database->quote($this->semester) . "'
            AND   s.FileYear = '" . $this->database->quote($this->year) . "'

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
                vStaff.StaffID,
                NetworkLogin,
                ActiveFlag,
                vStaff.StaffTitle,
                vStaff.StaffPreferredName,
                vStaff.StaffSurname,
                DefaultEmailCode,
                OccupEmail,
                Email,
                vStaff.StaffCampus,
                StaffCategoryType,
                StaffCategory,
                vStaff.StaffDepartment
            FROM SynergyOne.dbo.vStaff vStaff
            LEFT JOIN Community ON Community.ID = StaffID
            WHERE ActiveFlag = 1 AND vStaff.StaffRegistrationType != 'STDNT'
            ORDER BY vStaff.StaffID ASC
        ";
        return new Iterator\Staff($this->database->getIterator($query));
    }
}

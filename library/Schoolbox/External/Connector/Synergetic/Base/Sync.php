<?php
/**
 * Synergetic implementation of sync connector.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Synergetic\Base;

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
                vStudents.StudentID,
                NetworkLogin,
                vStudents.StudentTitle,
                vStudents.StudentPreferred,
                vStudents.StudentSurname,
                StudentGender,
                StudentEmail,
                StudentOccupEmail,
                vStudents.StudentYearLevel,
                vStudents.StudentHouse,
                vStudents.StudentBoarder,
                vStudents.StudentBoardingHouse,
                StudentCampus
            FROM SynergyOne.dbo.vStudents vStudents
            LEFT JOIN Community ON Community.ID = StudentID
            WHERE
            FileSemester = '" . $this->database->quote($this->getCurrentSemester()) . "'
            AND FileYear = '" . $this->database->quote($this->getCurrentYear()) . "'
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
                cp.NetworkLogin AS StudentContactNetworkLogin,
                s.StudentContactID,
                s.StudentContactTitle,
                s.StudentContactPreferred,
                s.StudentContactSurname,
                s.StudentContactDefaultEmail,
                s.StudentId,
                s.StudentYearLevel,
                s.StudentCampus,
                s.StudentGender,
                s.StudentPreferred,
                s.StudentSurname
            FROM SynergyOne.dbo.vStudentContactAllAddress s
            INNER JOIN SynergyOne.dbo.Community cp ON s.StudentContactID = cp.ID
            WHERE (s.StudentContactType IN ('SC1', 'SC2'))
            AND   s.FileSemester = '" . $this->database->quote($this->getCurrentSemester()) . "'
            AND   s.FileYear = '" . $this->database->quote($this->getCurrentYear()) . "'

            UNION ALL

            SELECT DISTINCT
                cs.NetworkLogin AS StudentContactNetworkLogin,
                s.StudentContactSpouseID AS StudentContactID,
                s.StudentContactSpouseTitle AS StudentContactTitle,
                s.StudentContactSpousePreferred AS StudentContactPreferred,
                s.StudentContactSpouseSurname AS StudentContactSurname,
                s.StudentContactSpouseDefaultEmail AS StudentContactDefaultEmail,
                s.StudentId,
                s.StudentYearLevel,
                s.StudentCampus,
                s.StudentGender,
                s.StudentPreferred,
                s.StudentSurname
            FROM SynergyOne.dbo.vStudentContactAllAddress s
            INNER JOIN SynergyOne.dbo.Community cs ON s.StudentContactSpouseID = cs.ID
            WHERE (s.StudentContactType IN ('SC1', 'SC2'))
            AND   s.FileSemester = '" . $this->database->quote($this->getCurrentSemester()) . "'
            AND   s.FileYear = '" . $this->database->quote($this->getCurrentYear()) . "'

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

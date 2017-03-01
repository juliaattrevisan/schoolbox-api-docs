<?php
/**
 * Synergetic implementation of sync connector for Donvale
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Synergetic\Donvale;

use Schoolbox\External\Connector\Synergetic\Base\Sync as BaseSync;
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
                vStudents.StudentID            AS student_id,
                CASE WHEN ISNULL(NetworkLogin,'') = '' THEN CONCAT('_', vStudents.StudentID) ELSE NetworkLogin END AS network_login,
                vStudents.StudentTitle         AS student_title,
                vStudents.StudentGiven1        AS student_given_name,
                vStudents.StudentPreferred     AS student_preferred,
                vStudents.StudentSurname       AS student_surname,
                StudentGender                  AS student_gender,
                StudentEmail                   AS student_email,
                StudentOccupEmail              AS student_work_email,
                CASE WHEN ISNULL(vStudents.StudentYearLevel,'') = '' THEN 0 ELSE vStudents.StudentYearLevel END AS student_year_level,
                vStudents.StudentHouse         AS student_house,
                vStudents.StudentBoarder       AS student_boarder,
                vStudents.StudentBoardingHouse AS student_boarding_house,
                StudentCampus                  AS student_campus
            FROM dbo.vStudents vStudents
            LEFT JOIN Community ON Community.ID = StudentID
            WHERE
            FileSemester = '" . $this->database->quote($this->semester) . "'
            AND FileYear = '" . $this->database->quote($this->year) . "'
            ORDER BY network_login, vStudents.StudentID ASC
        ";

        return new Iterator\Student($this->database->getIterator($query));
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        $query = "

            SELECT DISTINCT student_contact_network_login, 
            student_contact_id, 
            student_contact_title, 
            student_contact_title, 
            student_contact_given_name, 
            student_contact_preferred,
            student_contact_surname,
            student_contact_default_email,
            student_contact_work_email,
            student_id, 
            student_campus, 
            CASE WHEN ISNULL(student_year_level,'') = '' THEN 0 ELSE student_year_level END AS student_year_level,
            student_gender,
            student_preferred,
            student_surname
            FROM dbo.uvSchoolBox_Parents;
        ";

        return new Iterator\Guardian($this->database->getIterator($query));
    }
}

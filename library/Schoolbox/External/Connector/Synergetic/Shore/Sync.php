<?php

/**
 * Synergetic implementation of sync connector for Shore
 *
 * @author    Sam Bailey <sam.bailey@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Synergetic\Shore;

use Schoolbox\External\Connector\Synergetic\Base\Sync as BaseSync;
use Schoolbox\Sync\Iterator;

class Sync extends BaseSync
{
    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        $iterator = $this->databaseExternal->getQueryIterator("
            SELECT
                vStudents.StudentID            AS student_id,
                ca.AttributeValue              AS network_login,
                vStudents.StudentTitle         AS student_title,
                vStudents.StudentGiven1        AS student_given_name,
                vStudents.StudentPreferred     AS student_preferred,
                vStudents.StudentSurname       AS student_surname,
                StudentGender                  AS student_gender,
                StudentEmail                   AS student_home_email,
                CASE WHEN Community.DefaultEmailCode = 'O' 
                  THEN Community.OccupEmail 
                  ELSE Community.Email 
                END AS student_default_email,
                StudentOccupEmail              AS student_work_email,
                vStudents.StudentYearLevel     AS student_year_level,
                vStudents.StudentHouse         AS student_house,
                vStudents.StudentBoarder       AS student_boarder,
                vStudents.StudentBoardingHouse AS student_boarding_house,
                StudentCampus                  AS student_campus
            FROM dbo.vStudents vStudents
            LEFT JOIN Community ON Community.ID = vStudents.StudentID
            LEFT JOIN CommunityAttributes ca ON ca.ID = vStudents.StudentID
                AND ca.AttributeCode = '10'
            WHERE
            FileSemester = (SELECT FileSemester FROM FileSemesters WHERE SystemCurrentFlag = 1)
            AND FileYear = (SELECT FileYear FROM FileSemesters WHERE SystemCurrentFlag = 1)
            ORDER BY vStudents.StudentID ASC
        ");
        return new Iterator\Student($iterator);
    }
}

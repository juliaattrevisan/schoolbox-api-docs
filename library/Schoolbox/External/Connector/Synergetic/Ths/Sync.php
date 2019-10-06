<?php
/**
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Synergetic\Ths;

use Schoolbox\External\Connector\Synergetic\Base\Sync as BaseSync;
use Schoolbox\Sync\Iterator;

class Sync extends BaseSync
{
    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        $studentIterator = $this->databaseExternal->getQueryIterator("
            
            SELECT          uvStudents.StudentID                           AS student_id,
                            NetworkLogin                                  AS network_login,
                            uvStudents.StudentTitle                        AS student_title,
                            uvStudents.StudentGiven1                       AS student_given_name,
                            uvStudents.StudentPreferred                    AS student_preferred,
                            uvStudents.StudentSurname                      AS student_surname,
                            StudentGender                                 AS student_gender,
                            StudentEmail                                  AS student_home_email,
                            
                            CASE 
                                WHEN Community.DefaultEmailCode = 'O' THEN Community.OccupEmail 
                                ELSE Community.Email 
                            END AS student_default_email,
                            
                            StudentOccupEmail                             AS student_work_email,
                            uvStudents.StudentYearLevel                    AS student_year_level,
                            uvStudents.StudentHouse                        AS student_house,
                            uvStudents.StudentBoarder                      AS student_boarder,
                            uvStudents.StudentBoardingHouse                AS student_boarding_house,
                            StudentCampus                                 AS student_campus
            FROM            dbo.uvStudents uvStudents
            LEFT JOIN       Community ON Community.ID = StudentID
            WHERE           FileSemester = (SELECT FileSemester FROM FileSemesters WHERE SystemCurrentFlag = 1) AND 
                            FileYear = (SELECT FileYear FROM FileSemesters WHERE SystemCurrentFlag = 1)
            ORDER BY        uvStudents.StudentID ASC
        ");

        return new Iterator\Student($studentIterator);
    }
}

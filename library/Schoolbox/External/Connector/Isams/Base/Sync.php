<?php
/**
 * Isams Sync Implementation
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 *
 * Uncle iSam?
 *    _..._
 *   |||||||
 *   |||||||
 *  _|*****|_
 * ( '-...-' )
 * {`;-----;`}
 * {(| a  a|)}
 *  \|  _\ |/
 *   \  -  /
 *    '{_}`
 */

namespace Schoolbox\External\Connector\Isams\Base;

use External_User;
use MSSQL;
use Schoolbox\External\Connector\SyncInterface;
use Schoolbox\Sync\Iterator;

class Sync implements SyncInterface
{
    /**
     * @var \MSSQL
     */
    protected $database;

    /**
     * @var string
     */
    protected $year;

    /**
     * @var string
     */
    protected $semester;

    /**
     * Sync constructor
     */
    public function __construct()
    {
        $this->database = \MSSQL::getInstance();

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
        $query =
        "
            SELECT
                StudentID                AS student_id,
                ''                       AS network_login,
                ''                       AS student_title,
                StudentFirstname         AS student_given_name,
                StudentFirstname         AS student_preferred,
                StudentSurname           AS student_surname,
                Gender                   AS student_gender,
                ''                       AS student_home_email,
                StudentEmail             AS student_default_email,
                ''                       AS student_work_email,
                Grade                    AS student_year_level,
                ''                       AS student_house_description,
                ''                       AS student_house,
                0                        AS student_boarder,
                ''                       AS student_boarding_house,
                campus                   AS student_campus
            FROM V_Student
            ORDER BY StudentID
        ";

        return new Iterator\Student($this->database->getIterator($query));
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        $query =
        "
            SELECT DISTINCT 
                        ParentCode                      AS student_contact_network_login,
                        ParentID                        AS student_contact_id,
                        ''                              AS student_contact_title,
                        ParentFirstname                 AS student_contact_preferred,
                        ParentFirstname                 AS student_contact_given_name,
                        ParentSurname                   AS student_contact_surname,
                        ''                              AS student_contact_home_email,
                        CISEmail                        AS student_contact_default_email,
                        ''                              AS student_contact_work_email,
                        V_Student.campus                AS student_campus,                        
                        ''                              AS student_campus,
                        ''                              AS student_house,
                        ''                              AS student_gender,
                        V_Student.StudentFirstname      AS student_preferred,
                        V_Student.StudentSurname        AS student_surname,
                        V_Student.Grade                 AS student_year_level,
                        V_Student.Grade                 AS student_year_level_description            
            FROM        V_Parent
            JOIN        V_Student ON V_Student.StudentID = V_Parent.StudentID
            WHERE       V_Parent.Relationship IN ('Caregiver', 'Father', 'Guardian', 'Legal Guardian', 'Mother', 'Primary Contact')
            ORDER BY    ParentID
        ";

        return new Iterator\Guardian($this->database->getIterator($query));
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $query =
        "
            SELECT      V_Staff.StaffID                     AS staff_id,
                        ''                                  AS network_login,
                        1                                   AS active_flag,
                        V_Staff.title                       AS staff_title,
                        teacherfirstname                    AS staff_given_name,
                        PreferredName                       AS staff_preferred_name,
                        teacherlastname                     AS staff_surname,
                        V_StaffDirectory.SchoolEmailAddress AS work_email,
                        V_StaffDirectory.SchoolEmailAddress AS default_email,
                        ''                                  AS home_email,
                        Campus                              AS staff_campus,
                        DivisionID                          AS staff_category_type,
                        ''                                  AS staff_category,
			            ''                                  AS staff_position,
                        ''                                  AS staff_department,
                        ''                                  AS staff_year_level,
                        ''                                  AS staff_year_level_description                
            FROM        V_Staff
            LEFT JOIN   V_StaffDirectory ON V_StaffDirectory.StaffID = V_Staff.StaffID
            ORDER BY    V_Staff.StaffID
        ";

        return new Iterator\Staff($this->database->getIterator($query));
    }
}


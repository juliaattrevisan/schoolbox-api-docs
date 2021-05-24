<?php

/**
 * Isams Sync Implementation
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
        $query =
        "
            SELECT
                StudentID                AS student_id,
                Username                 AS network_login,
                Title                    AS student_title,
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

        return new Iterator\Student($this->databaseExternal->getQueryIterator($query));
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        $query =
        "
            SELECT DISTINCT 
                        V_Parent.Username               AS student_contact_network_login,
                        ParentID                        AS student_contact_id,
                        V_Parent.Title                  AS student_contact_title,
                        ParentFirstname                 AS student_contact_preferred,
                        ParentFirstname                 AS student_contact_given_name,
                        ParentSurname                   AS student_contact_surname,
                        PersonalEmail                   AS student_contact_home_email,
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
            WHERE       V_Parent.Relationship IN ('Caregiver', 'Father', 'Guardian', 'Legal Guardian', 'Mother', 'Primary Contact', 'Stepfather', 'Stepmother')
            ORDER BY    ParentID
        ";

        return new Iterator\Guardian($this->databaseExternal->getQueryIterator($query));
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $query =
        "
            SELECT      V_Staff.StaffID                     AS staff_id,
                        Username                            AS network_login,
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
                        ''                                  AS staff_position_code,
                        ''                                  AS staff_position,
                        ''                                  AS staff_department,
                        ''                                  AS staff_year_level,
                        ''                                  AS staff_year_level_description                
            FROM        V_Staff
            LEFT JOIN   V_StaffDirectory ON V_StaffDirectory.StaffID = V_Staff.StaffID
            ORDER BY    V_Staff.StaffID
        ";

        return new Iterator\Staff($this->databaseExternal->getQueryIterator($query));
    }
}

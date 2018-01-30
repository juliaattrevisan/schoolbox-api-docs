<?php
/**
 * Schoolbox table stub implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Schoolbox\Base;

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
        $query = "
            SELECT
                u.synergy_id         AS student_id,
                u.username           AS network_login,
                u.title              AS student_title,
                u.firstname          AS student_given_name,
                u.firstname          AS student_preferred,
                u.lastname           AS student_surname,
                ''                   AS student_gender,
                ''                   AS student_home_email,
                ''                   AS student_default_email,
                ''                   AS student_work_email,
                u.year               AS student_year_level,
                h.house              AS student_house,
                r.name               AS student_boarder,
                r.name               AS student_boarding_house,
                c.ext_timetable_code AS student_campus
            FROM `user` `u`
            LEFT  JOIN house h              ON u.house = h.id
            LEFT  JOIN luResidentialHouse r ON u.residential_house_id = r.id
            INNER JOIN userCampus uc        ON u.id = uc.uid
            INNER JOIN luCampus c           ON uc.campus = c.id
            INNER JOIN `groups` g           ON u.group_id = g.id
            WHERE u.enabled IS TRUE
            AND   g.group_type = 'student'
            ORDER BY u.synergy_id ASC
        ";

        return new Iterator\Student(
            $this->databaseExternal->getQueryIterator($query)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        $query = "
            SELECT
                p.username           AS student_contact_network_login,
                p.synergy_id         AS student_contact_id,
                p.title              AS student_contact_title,
                p.firstname          AS student_contact_given_name,
                p.firstname          AS student_contact_preferred,
                p.lastname           AS student_contact_surname,
                ''                   AS student_contact_home_email,
                ''                   AS student_contact_default_email,
                ''                   AS student_contact_work_email,
                s.synergy_id         AS student_id,
                s.year               AS student_year_level,
                c.ext_timetable_code AS student_campus,
                h.house              AS student_house,
                ''                   AS student_gender,
                s.firstname          AS student_preferred,
                s.lastname           AS student_surname
            FROM `user` `p`
            INNER JOIN `groups` pg        ON p.group_id = pg.id
            LEFT  JOIN user_relation `ur` ON p.id = ur.user_id
            LEFT  JOIN `user` `s`         ON ur.relation_id = s.id
            LEFT  JOIN userCampus uc      ON s.id = uc.uid
            LEFT  JOIN luCampus c         ON uc.campus = c.id
            LEFT  JOIN house h            ON s.house = h.id
            WHERE p.enabled IS TRUE
            AND   s.enabled IS TRUE
            AND   pg.group_type = 'parent'
            ORDER BY p.synergy_id ASC
        ";

        return new Iterator\Guardian(
            $this->databaseExternal->getQueryIterator($query)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $query = "
            SELECT
                u.synergy_id         AS staff_id,
                u.username           AS network_login,
                u.enabled            AS active_flag,
                u.title              AS staff_title,
                u.firstname          AS staff_given_name,
                u.firstname          AS staff_preferred_name,
                u.lastname           AS staff_surname,
                ''                   AS home_email,
                u.alt_email          AS default_email,
                ''                   AS work_email,
                c.ext_timetable_code AS staff_campus,
                ''                   AS staff_category_type,
                ''                   AS staff_category,
                ''                   AS staff_house,
                ''                   AS staff_department,
                u.year               AS staff_year_level,
                ''                   AS staff_year_level_description                
                
            FROM `user` `u`
            INNER JOIN userCampus uc        ON u.id = uc.uid
            INNER JOIN luCampus c           ON uc.campus = c.id
            INNER JOIN `groups` g           ON u.group_id = g.id
            WHERE g.group_type = 'staff'
            ORDER BY u.synergy_id ASC
        ";

        return new Iterator\Staff(
            $this->databaseExternal->getQueryIterator($query)
        );
    }
}

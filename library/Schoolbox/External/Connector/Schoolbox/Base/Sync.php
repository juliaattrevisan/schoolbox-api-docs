<?php
/**
 * Schoolbox table stub implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
                sup.external_id                             AS student_id,
                sups.username                               AS network_login,
                sup.title                                   AS student_title,
                sup.firstname                               AS student_given_name,
                sup.firstname                               AS student_preferred,
                sup.lastname                                AS student_surname,
                sups.gender                                 AS student_gender,
                sups.email                                  AS student_home_email,
                sups.email                                  AS student_default_email,
                sups.email                                  AS student_work_email,
                sups.year_level                             AS student_year_level,
                sups.house_name                             AS student_house,
                sups.boarding_status                        AS student_boarder,
                ''                                          AS student_boarding_house,
                sups.campus_name                            AS student_campus
            FROM        `sis_user_profile` `sup`
            JOIN        `sis_user_profile_student` sups ON sup.id = sups.sis_user_profile_id
            ORDER BY    sup.external_id ASC
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
        $query = '
            SELECT
                supp.username       AS student_contact_network_login,
                parent.external_id  AS student_contact_id,
                parent.title        AS student_contact_title,
                parent.firstname    AS student_contact_given_name,
                parent.firstname    AS student_contact_preferred,
                parent.lastname     AS student_contact_surname,
                supp.email          AS student_contact_home_email,
                supp.email          AS student_contact_default_email,
                supp.email          AS student_contact_work_email,
                child.external_id   AS student_id,
                sups.year_level     AS student_year_level,
                sups.campus_name    AS student_campus,
                sups.house_name     AS student_house,
                sups.gender         AS student_gender,
                child.firstname     AS student_preferred,
                child.lastname      AS student_surname
            FROM    `sis_user_profile` `child`
            JOIN    sis_user_profile_student sups on child.id = sups.sis_user_profile_id
            JOIN    sis_user_profile_relation supr ON child.id = supr.child_profile_id
            JOIN    sis_user_profile parent ON supr.user_profile_id = parent.id
            JOIN    sis_user_profile_parent supp on parent.id = supp.sis_user_profile_id
            WHERE child.deleted_at IS NULL
            ORDER BY parent.external_id ASC
        ';

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
            SELECT    sup.external_id        AS staff_id,
                      sups.username          AS network_login,
                      ''                     AS active_flag,
                      sup.title              AS staff_title,
                      sup.firstname          AS staff_given_name,
                      sup.firstname          AS staff_preferred_name,
                      sup.lastname           AS staff_surname,
                      sups.email             AS default_email_code,
                      sups.email             AS home_email,
                      sups.email             AS default_email,
                      sups.email             AS work_email,
                      sups.campus_name       AS staff_campus,
                      ''                     AS staff_category_type,
                      sups.category          AS staff_category,
                      sups.position_title    AS staff_position,
                      sups.department        AS staff_department,
                      ''                     AS staff_year_level,
                      ''                     AS staff_year_level_description,
                      ''                     AS staff_house
            FROM      `sis_user_profile` sup
            JOIN      `sis_user_profile_staff` sups ON sup.id = sups.sis_user_profile_id
            WHERE     sup.deleted_at IS NULL AND sups.deleted_at IS NULL
            ORDER BY  sup.external_id ASC
        ";

        return new Iterator\Staff(
            $this->databaseExternal->getQueryIterator($query)
        );
    }
}

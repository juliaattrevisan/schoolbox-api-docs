<?php

/**
 * Schoolbox table stub implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Schoolbox\Base;

use Alaress_Date_Format;
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
        $userProfileTable    = \Schoolbox\External\Profile\Mapper::getInstance()->getTable();
        $studentProfileTable = \Schoolbox\External\Profile\Student\Mapper::getInstance()->getTable();
        $query = "
            SELECT
                sup.external_id          AS student_id,
                sups.username            AS network_login,
                sup.title                AS student_title,
                sup.firstname            AS student_given_name,
                sup.preferred_firstname  AS student_preferred,
                sup.lastname             AS student_surname,
                sups.gender              AS student_gender,
                sups.email               AS student_home_email,
                sups.email               AS student_default_email,
                sups.email               AS student_work_email,
                sups.year_level          AS student_year_level,
                sups.form_name           AS student_form_description,
                sups.house_name          AS student_house,
                sups.boarding_status     AS student_boarder,
                sups.boarding_house_name AS student_boarding_house,
                sups.campus_name         AS student_campus
            FROM        {$userProfileTable} `sup`
            JOIN        {$studentProfileTable} sups ON sup.id = sups.sis_user_profile_id
            WHERE       (sups.leaving_date IS NULL OR sups.leaving_date >= :now)
            ORDER BY    sup.external_id ASC
        ";

        return new Iterator\Student(
            $this->databaseExternal->getQueryIterator($query, ['now' => Alaress_Date_Format::now()->getDatabaseDate()])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        $userProfileTable     = \Schoolbox\External\Profile\Mapper::getInstance()->getTable();
        $studentProfileTable  = \Schoolbox\External\Profile\Student\Mapper::getInstance()->getTable();
        $parentProfileTable   = \Schoolbox\External\Profile\Parent\Mapper::getInstance()->getTable();
        $relationProfileTable = \Schoolbox\External\Profile\Relationship\Mapper::getInstance()->getTable();
        $query = "
            SELECT
                supp.username              AS student_contact_network_login,
                parent.external_id         AS student_contact_id,
                parent.title               AS student_contact_title,
                parent.firstname           AS student_contact_given_name,
                parent.preferred_firstname AS student_contact_preferred,
                parent.lastname            AS student_contact_surname,
                supp.email                 AS student_contact_home_email,
                supp.email                 AS student_contact_default_email,
                supp.email                 AS student_contact_work_email,
                supr.type                  AS student_contact_type,
                child.external_id          AS student_id,
                sups.year_level            AS student_year_level,
                sups.form_name             AS student_form_description,
                sups.campus_name           AS student_campus,
                sups.house_name            AS student_house,
                sups.gender                AS student_gender,
                child.firstname            AS student_preferred,
                child.lastname             AS student_surname
            FROM {$parentProfileTable} supp
            INNER JOIN {$userProfileTable} parent ON supp.sis_user_profile_id = parent.id
            LEFT JOIN {$relationProfileTable} supr ON supr.user_profile_id = parent.id
            LEFT JOIN {$userProfileTable} `child` ON supr.child_profile_id = child.id
            LEFT JOIN {$studentProfileTable} sups ON sups.sis_user_profile_id = child.id
            WHERE (sups.leaving_date IS NULL OR sups.leaving_date >= :now)
            ORDER BY parent.external_id ASC
        ";

        return new Iterator\Guardian(
            $this->databaseExternal->getQueryIterator($query, ['now' => Alaress_Date_Format::now()->getDatabaseDate()])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $userProfileTable   = \Schoolbox\External\Profile\Mapper::getInstance()->getTable();
        $staffProfileTable  = \Schoolbox\External\Profile\Staff\Mapper::getInstance()->getTable();
        $query = "
            SELECT    sup.external_id          AS staff_id,
                      sups.username            AS network_login,
                      ''                       AS active_flag,
                      sup.title                AS staff_title,
                      sup.firstname            AS staff_given_name,
                      sup.preferred_firstname  AS staff_preferred_name,
                      sup.lastname             AS staff_surname,
                      sups.email               AS default_email_code,
                      sups.email               AS home_email,
                      sups.email               AS default_email,
                      sups.email               AS work_email,
                      sups.campus_name         AS staff_campus,
                      ''                       AS staff_category_type,
                      sups.category            AS staff_category,
                      ''                       AS staff_position_code,
                      sups.position_title      AS staff_position,
                      sups.department          AS staff_department,
                      ''                       AS staff_year_level,
                      ''                       AS staff_year_level_description,
                      sups.house_name          AS staff_house
            FROM      {$userProfileTable} sup
            JOIN      {$staffProfileTable} sups ON sup.id = sups.sis_user_profile_id
            ORDER BY  sup.external_id ASC
        ";

        return new Iterator\Staff(
            $this->databaseExternal->getQueryIterator($query)
        );
    }
}

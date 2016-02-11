<?php
/**
 * Schoolbox table stub implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Schoolbox\Base;

use Database;
use Schoolbox\External\Connector\SyncInterface;
use Schoolbox\Sync\Iterator;

class Sync implements SyncInterface
{
    /**
     * @var Database
     */
    private $database;

    /**
     * Sync constructor
     */
    public function __construct()
    {
        $this->database = Database::getInstance();
    }

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
                u.firstname          AS student_preferred,
                u.lastname           AS student_surname,
                ''                   AS student_gender,
                ''                   AS student_email,
                ''                   AS student_occup_email,
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
        $statement = $this->database->prepare($query);
        $statement->execute();
        return new Iterator\Student($statement);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        return [];
    }
}

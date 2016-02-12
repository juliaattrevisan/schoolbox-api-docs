<?php
/**
 * Humanedge implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Humanedge\Base;

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
                s.code        AS student_id,
                ''            AS network_login,
                s.Title       AS student_title,
                s.FirstName   AS student_preferred,
                s.LastName    AS student_surname,
                ''            AS student_gender,
                ''            AS student_email,
                ''            AS student_occup_email,
                s.Year        AS student_year_level,
                s.House       AS student_house,
                ''            AS student_boarder,
                s.BoarderType AS student_boarding_house,
                ''            AS student_campus
            FROM Student s
            ORDER BY s.Code ASC
        ";
        return new Iterator\Student($this->database->getIterator($query));
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

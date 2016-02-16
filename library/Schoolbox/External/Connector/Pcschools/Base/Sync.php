<?php
/**
 * Pcschools implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Pcschools\Base;

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
                S.STUDKEY       AS student_id,
                ''              AS network_login,
                ''              AS student_title,
                S.GIVEN_NAME    AS student_preferred,
                S.LEGAL_SURNAME AS student_surname,
                S.SEX           AS student_gender,
                S.EMAIL         AS student_email,
                ''              AS student_occup_email,
                S.CURRENT_YEAR  AS student_year_level,
                S.HOUSE         AS student_house,
                ''              AS student_boarder,
                ''              AS student_boarding_house,
                S.CAMPUS        AS student_campus
            FROM STUDENT S
            WHERE S.DATE_DEPART IS NULL
            ORDER BY S.STUDKEY ASC
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
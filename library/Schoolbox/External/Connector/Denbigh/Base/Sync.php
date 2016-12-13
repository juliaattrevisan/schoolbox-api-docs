<?php
/**
 * Denbigh implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Denbigh\Base;

use Config;
use Database;
use Schoolbox\External\Connector\SyncInterface;
use Schoolbox\Sync\Iterator;

class Sync implements SyncInterface
{
    /**
     * @var Database
     */
    protected $database;

    /**
     * Sync constructor
     */
    public function __construct()
    {
        $hostName     = Config::get('external_db_host');
        $databaseName = Config::get('external_db_db');
        $username     = Config::get('external_db_user');
        $password     = Config::get('external_db_passwd');
        $this->database = new Database();
        $this->database->connect(
            "mysql:host=$hostName;dbname=$databaseName",
            $username,
            $password,
            array('throw' => true)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        $statement = $this->database->prepare("
            SELECT
                denbighID      AS student_id,
                username       AS network_login,
                ''             AS student_title,
                firstname      AS student_given_name,
                firstname      AS student_preferred,
                surname        AS student_surname,
                ''             AS student_gender,
                email          AS student_email,
                ''             AS student_work_email,
                academicYear   AS student_year_level,
                house          AS student_house,
                boardingStatus AS student_boarder,
                cateringHouse  AS student_boarding_house,
                ''             AS student_campus
            FROM Schoolbox_Students
            ORDER BY denbighID ASC
        ");
        $statement->execute();
        return new Iterator\Student($statement);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        $statement = $this->database->prepare("
            SELECT
                par.loginID      AS student_contact_network_login,
                peo.denbighID    AS student_contact_id,
                peo.title        AS student_contact_title,
                peo.given_names  AS student_contact_given_name,
                peo.preferred_name AS student_contact_preferred,
                peo.surname      AS student_contact_surname,
                peo.email        AS student_contact_default_email,
                s.denbighID      AS student_id,
                s.academicYear   AS student_year_level,
                ''               AS student_campus,
                s.house          AS student_house,
                ''               AS student_gender,
                s.firstname      AS student_preferred,
                s.surname        AS student_surname
            FROM Addresses adr
            LEFT JOIN Parents par ON par.familyDenbighID = adr.familyDenbighID
            LEFT JOIN People peo ON peo.denbighID = par.peopleDenbighID
            LEFT JOIN Schoolbox_Students s ON s.familyDenbighID = par.familyDenbighID
            WHERE peo.id IS NOT NULL
            GROUP BY peo.denbighID
            ORDER BY adr.familyDenbighID ASC, peo.denbighID ASC
        ");
        $statement->execute();
        return new Iterator\Guardian($statement);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $statement = $this->database->prepare("
            SELECT
                p.denbighID      AS staff_id,
                s.username       AS network_login,
                1                AS active_flag,
                p.title          AS staff_title,
                p.given_names    AS staff_given_name,
                p.preferred_name AS staff_preferred_name,
                p.surname        AS staff_surname,
                ''               AS default_email_code,
                ''               AS work_email,
                p.email          AS email,
                s.campus         AS staff_campus,
                ''               AS staff_category_type,
                ''               AS staff_category,
                ''               AS staff_house,
                ''               AS staff_department,
                ''               AS staff_year_level,
                ''               AS staff_year_level_description                
                
            FROM Staff s
            LEFT JOIN People p ON s.peopleDenbighID = p.denbighID
        ");
        $statement->execute();
        return new Iterator\Staff($statement);
    }
}

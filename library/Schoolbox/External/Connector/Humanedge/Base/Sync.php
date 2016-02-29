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
                s.Code        AS student_id,
                ''            AS network_login,
                ''            AS student_title,
                ''            AS student_given_name,
                s.FirstName   AS student_preferred,
                s.LastName    AS student_surname,
                s.Sex         AS student_gender,
                s.Email       AS student_email,
                ''            AS student_occup_email,
                s.Year        AS student_year_level,
                s.House       AS student_house,
                ''            AS student_boarder,
                s.BoarderType AS student_boarding_house,
                s.CampusID    AS student_campus
            FROM Student s
            WHERE s.DateLeft IS NULL
            ORDER BY s.Code ASC
        ";
        return new Iterator\Student($this->database->getIterator($query));
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        $query = "
            SELECT
                ''          AS student_contact_network_login,
                c.Code      AS student_contact_id,
                c.Title     AS student_contact_title,
                ''          AS student_contact_given_name,
                c.FirstName AS student_contact_preferred,
                c.LastName  AS student_contact_surname,
                CASE WHEN c.EmailCorrespondence = 'Y'
                    THEN c.Email
                    ELSE ''
                END         AS student_contact_default_email,
                s.Code      AS student_id,
                s.Year      AS student_year_level,
                s.CampusID  AS student_campus,
                s.Sex       AS student_gender,
                s.FirstName AS student_preferred,
                s.LastName  AS student_surname
            FROM Contact c
            INNER JOIN StudentContact sc ON c.ID = sc.ContactID
            INNER JOIN Student         s ON sc.StudentID = s.ID
            WHERE sc.ResidentialGuardian = 'Y'
            AND   s.DateLeft IS NULL
            ORDER BY c.Code ASC
        ";
        return new Iterator\Guardian($this->database->getIterator($query));
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $query = "
            SELECT
                t.Code      AS staff_id,
                ''          AS network_login,
                t.Active    AS active_flag,
                t.Title     AS staff_title,
                ''          AS staff_given_name,
                t.FirstName AS staff_preferred_name,
                t.LastName  AS staff_surname,
                ''          AS default_email_code,
                t.Email     AS occup_email,
                t.CampusID  AS staff_campus,
                CASE WHEN t.IsTeachingStaff = 'Y'
                    THEN 'Teaching'
                    ELSE 'Non-Teaching'
                END         AS staff_category_type,
                t.Position  AS staff_category,
                ''          AS staff_department
            FROM Staff t
            WHERE t.DateLeft IS NULL
            AND   t.Active = 'Y'
            ORDER BY t.Code ASC
        ";
        return new Iterator\Staff($this->database->getIterator($query));
    }
}

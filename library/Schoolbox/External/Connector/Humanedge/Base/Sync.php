<?php
/**
 * Humanedge implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Humanedge\Base;

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
        /**
         * CustomOverrride
         */
        $customView = trim($this->config->get('external_custom_ups_student'));
        if(strlen($customView) > 0) {
            return new Iterator\Student(
                $this->databaseExternal->executeQueryIterator(
                    'SELECT DISTINCT * FROM '.$this->databaseExternal->quoteIdentifier($customView).';'
                )
            );
        }

        $query = "
            SELECT
                s.Code        AS student_id,
                ''            AS network_login,
                ''            AS student_title,
                s.FirstName   AS student_given_name,
                s.FirstName   AS student_preferred,
                s.LastName    AS student_surname,
                s.Sex         AS student_gender,
                ''            AS student_home_email,
                s.Email       AS student_default_email,
                ''            AS student_work_email,
                s.Year        AS student_year_level,
                s.House       AS student_house,
                ''            AS student_boarder,
                s.BoarderType AS student_boarding_house,
                s.CampusID    AS student_campus
            FROM Student s
            WHERE s.DateLeft IS NULL
            ORDER BY s.Code ASC
        ";

        return new Iterator\Student(
            $this->databaseExternal->executeQueryIterator($query)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        /**
         * CustomOverrride
         */
        $customView = trim($this->config->get('external_custom_ups_parent'));
        if(strlen($customView) > 0) {
            return new Iterator\Guardian(
                $this->databaseExternal->executeQueryIterator(
                    'SELECT DISTINCT * FROM '.$this->databaseExternal->quoteIdentifier($customView).';'
                )
            );
        }

        $query = "
            SELECT
                ''          AS student_contact_network_login,
                c.Code      AS student_contact_id,
                c.Title     AS student_contact_title,
                c.FirstName AS student_contact_given_name,
                c.FirstName AS student_contact_preferred,
                c.LastName  AS student_contact_surname,
                ''          AS student_contact_home_email,
                CASE WHEN c.EmailCorrespondence = 'Y'
                    THEN c.Email
                    ELSE ''
                END         AS student_contact_default_email,
                ''          AS student_contact_work_email,
                s.Code      AS student_id,
                s.Year      AS student_year_level,
                s.CampusID  AS student_campus,
                s.House     AS student_house,
                s.Sex       AS student_gender,
                s.FirstName AS student_preferred,
                s.LastName  AS student_surname
            FROM Contact c
            INNER JOIN StudentContact sc ON c.ID = sc.ContactID
            INNER JOIN Student         s ON sc.StudentID = s.ID
            WHERE (
                sc.ResidentialGuardian = 'Y' OR
                sc.Relationship = 'Father' OR
                sc.Relationship = 'Mother' OR
                sc.Relationship = 'Stepfather' OR
                sc.Relationship = 'Stepmother' OR
                sc.Relationship = 'Step Father' OR
                sc.Relationship = 'Step Mother' OR
                sc.Relationship = 'Houseparents' OR
                sc.Relationship LIKE '%Guardian%' OR
                sc.Relationship LIKE 'Foster%' OR
                sc.Relationship = 'Carer'
            )
            AND   s.DateLeft IS NULL
            ORDER BY c.Code ASC
        ";

        return new Iterator\Guardian(
            $this->databaseExternal->executeQueryIterator($query)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        /**
         * CustomOverrride
         */
        $customView = trim($this->config->get('external_custom_ups_staff'));
        if(strlen($customView) > 0) {
            return new Iterator\Staff(
                $this->databaseExternal->executeQueryIterator(
                    'SELECT DISTINCT * FROM '.$this->databaseExternal->quoteIdentifier($customView).';'
                )
            );
        }

        $query = "
            SELECT
                t.Code      AS staff_id,
                ''          AS network_login,
                t.Active    AS active_flag,
                t.Title     AS staff_title,
                t.FirstName AS staff_given_name,
                t.FirstName AS staff_preferred_name,
                t.LastName  AS staff_surname,
                ''          AS default_email_code,
                ''          AS home_email,
                t.Email     AS default_email,
                ''          AS work_email,
                t.CampusID  AS staff_campus,
                CASE WHEN t.IsTeachingStaff = 'Y'
                    THEN 'Teaching'
                    ELSE 'Non-Teaching'
                END         AS staff_category_type,
                t.Position  AS staff_category,
                ''          AS staff_house,
                ''          AS staff_department,
                ''          AS staff_year_level,
                ''          AS staff_year_level_description

            FROM Staff t
            WHERE t.DateLeft IS NULL
            AND   t.Active = 'Y'
            ORDER BY t.Code ASC
        ";

        return new Iterator\Staff(
            $this->databaseExternal->executeQueryIterator($query)
        );
    }
}

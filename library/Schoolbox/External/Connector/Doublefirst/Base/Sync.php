<?php
/**
 * DoubleFirst Sync Implementation
 *
 * @author	    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 *
 *         ,--.--._
 * ------" _, \___)
 *       2 / _/____)
 *       x \//(____)
 * ------\     (__)
 *        `-----"
 */

namespace Schoolbox\External\Connector\Doublefirst\Base;

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
                    'SELECT DISTINCT * FROM '.$customView.';'
                )
            );
        }

        $query =
        "
            SELECT
                p.PupilID                           AS student_id,
                p.AdmissionNo                       AS network_login,
                ''                                  AS student_title,
                ForeName                            AS student_given_name,
                PreferredForeName                   AS student_preferred,
                Surname                             AS student_surname,
                ''                                  AS student_gender,
                ''                                  AS student_home_email,
                ''                                  AS student_default_email,
                ''                                  AS student_work_email,
                YearGroup                           AS student_year_level,
                House                               AS student_house_description,
                HouseID                             AS student_house,
                0                                   AS student_boarder,
                ''                                  AS student_boarding_house,
                ''                                  AS student_campus
            FROM V_PupilBasicInformation            p
            LEFT JOIN PupilMedicalInformation       m ON m.PupilID = p.PupilID
            AND p.PupilID <> ''
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
                    'SELECT DISTINCT * FROM '.$customView.';'
                )
            );
        }

        $query =
        "
            SELECT DISTINCT
                        c.ContactID                 AS student_contact_network_login,
                        c.ContactID                 AS student_contact_id,
                        c.Title                     AS student_contact_title,
                        cm.Greeting                 AS student_contact_preferred,
                        cm.Greeting                 AS student_contact_given_name,
                        c.Surname                   AS student_contact_surname,
                        ''                          AS student_contact_home_email,
                        cei.EmailID                 AS student_contact_default_email,
                        ce.WorkEmailAddress         AS student_contact_work_email,
                        pc.PupilID                  AS student_id,
                        ''                          AS student_campus,
                        p.HouseID                   AS student_house,
                        ''                          AS student_gender,
                        p.ForeName                  AS student_preferred,
                        p.Surname                   AS student_surname,
                        p.YearGroupID               AS student_year_level,
                        p.YearGroup                 as student_year_level_description
            FROM        VLPupilContact              pc
            LEFT JOIN   VLContact                   c on c.ContactID = pc.ContactID
            LEFT JOIN   ContactMaster               cm on c.ContactID = cm.ContactID
            LEFT JOIN   VLContactEmail              ce on ce.ContactID = pc.ContactID
            LEFT JOIN   ContactEmailInformation     cei ON cei.ContactID = pc.ContactID AND cei.PrimaryEmail = 1
            LEFT JOIN   VLContactTelephone          ct on ct.ContactID = pc.ContactID
            LEFT JOIN   V_PupilBasicInformation     p ON p.PupilID = pc.PupilID
            WHERE       pc.IsContactParental = 1
            AND         pc.ContactID <> ''
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
                    'SELECT DISTINCT * FROM '.$customView.';'
                )
            );
        }

        $query =
        "
            SELECT      spd.StaffID           as staff_id,
                        spd.UserID            AS network_login,
                        1                     AS active_flag,
                        title.Description     AS staff_title,
                        spd.ForeName          AS staff_given_name,
                        CASE WHEN spd.PreferredForeName <> '' AND spd.PreferredForeName IS NOT NULL THEN spd.PreferredForeName ELSE spd.ForeName END AS staff_preferred_name,
                        spd.SurName           AS staff_surname,
                        ''                    AS home_email,
                        CASE WHEN seiw.EmailAddress IS NOT NULL THEN seiw.EmailAddress ELSE seiwb.EmailAddress END AS default_email,
                        CASE WHEN seiw.EmailAddress IS NOT NULL THEN seiw.EmailAddress ELSE seiwb.EmailAddress END AS work_email,
                        ''                    AS staff_campus,

                        CASE WHEN
                          IsStaffPartTime = 1 THEN 'Part Time'
                          ELSE 'Full Time'
                        END AS staff_category_type,

                        CASE WHEN
                          IsTeacher = 1 THEN 'Teaching'
                          ELSE 'Non Teaching'
                        END AS staff_category,

			            ''                    AS staff_house,
			            cat.Description	      AS staff_position,
                        ''                    AS staff_department,
                        ''                    AS staff_year_level,
                        ''                    AS staff_year_level_description


            FROM        StaffPersonalDetails  spd
            JOIN        CurrentStaff cs ON cs.StaffID = spd.StaffID
            LEFT JOIN   StaffTelephones stw ON stw.StaffID = spd.StaffID AND stw.TelephoneType = 'W'
            LEFT JOIN   StaffEmailInformation seiw ON seiw.StaffID = spd.StaffID and seiw.PrimaryEmail = 1 AND seiw.EmailType = 'D321'
            LEFT JOIN   StaffEmailInformation seiwb ON seiwb.StaffID = spd.StaffID and seiwb.PrimaryEmail = 1 AND seiwb.EmailType = '2'
            LEFT JOIN   LookupDetails cat ON cat.LookupID = '1203' AND cat.LookupDetailsID = spd.StaffCategory
            LEFT JOIN   LookupDetails title ON title.LookupID = '3012' AND title.LookupDetailsID = spd.Title
            ORDER BY    spd.SurName,
                        spd.ForeName
        ";

        return new Iterator\Staff(
            $this->databaseExternal->executeQueryIterator($query)
        );
    }
}


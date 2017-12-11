<?php
/**
 * Synergetic implementation of sync connector
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Synergetic\Base;

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

        $studentIterator = $this->databaseExternal->executeQueryIterator("

            SELECT          vStudents.StudentID                           AS student_id,
                            NetworkLogin                                  AS network_login,
                            vStudents.StudentTitle                        AS student_title,
                            vStudents.StudentGiven1                       AS student_given_name,
                            vStudents.StudentPreferred                    AS student_preferred,
                            vStudents.StudentSurname                      AS student_surname,
                            StudentGender                                 AS student_gender,
                            StudentEmail                                  AS student_home_email,

                            CASE
                                WHEN Community.DefaultEmailCode = 'O' THEN Community.OccupEmail
                                ELSE Community.Email
                            END AS student_default_email,

                            StudentOccupEmail                             AS student_work_email,
                            vStudents.StudentYearLevel                    AS student_year_level,
                            vStudents.StudentHouse                        AS student_house,
                            vStudents.StudentBoarder                      AS student_boarder,
                            vStudents.StudentBoardingHouse                AS student_boarding_house,
                            StudentCampus                                 AS student_campus
            FROM            dbo.vStudents vStudents
            LEFT JOIN       Community ON Community.ID = StudentID
            WHERE           FileSemester = (SELECT FileSemester FROM FileSemesters WHERE SystemCurrentFlag = 1) AND
                            FileYear = (SELECT FileYear FROM FileSemesters WHERE SystemCurrentFlag = 1)
            ORDER BY        vStudents.StudentID ASC
        ");

        return new Iterator\Student($studentIterator);
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

        $contactTypes = trim($this->config->get('external_synergetic_student_contact_types'));

        if (empty($contactTypes)) {
            $contactTypes = "SC1,SC2";
        }

        $contactTypes = array_map("trim", explode(",", $contactTypes));

        $contactTypeBindings1 = $this->databaseExternal->prepareInBindings($contactTypes);
        $contactTypeBindings2 = $this->databaseExternal->prepareInBindings($contactTypes);

        $guardianIterator = $this->databaseExternal->executeQueryIterator("

            SELECT DISTINCT cp.NetworkLogin                               AS student_contact_network_login,
                            s.StudentContactID                            AS student_contact_id,
                            s.StudentContactTitle                         AS student_contact_title,
                            s.StudentContactPreferredFormal               AS student_contact_given_name,
                            s.StudentContactPreferred                     AS student_contact_preferred,
                            s.StudentContactSurname                       AS student_contact_surname,
                            s.StudentContactEmail                         AS student_contact_home_email,
                            s.StudentContactDefaultEmail                  AS student_contact_default_email,
                            s.StudentContactOccupEmail                    AS student_contact_work_email,
                            s.StudentId                                   AS student_id,
                            s.StudentYearLevel                            AS student_year_level,
                            s.StudentCampus                               AS student_campus,
                            s.StudentHouse                                AS student_house,
                            s.StudentGender                               AS student_gender,
                            s.StudentPreferred                            AS student_preferred,
                            s.StudentSurname                              AS student_surname
            FROM            dbo.vStudentContactAllAddress s
            INNER JOIN      dbo.Community cp ON s.StudentContactID = cp.ID
            WHERE           s.StudentContactType IN ({$contactTypeBindings1['sql']}) AND
                            FileSemester = (SELECT FileSemester FROM FileSemesters WHERE SystemCurrentFlag = 1) AND
                            FileYear = (SELECT FileYear FROM FileSemesters WHERE SystemCurrentFlag = 1)

            UNION ALL

            SELECT DISTINCT cs.NetworkLogin                               AS student_contact_network_login,
                            s.StudentContactSpouseID                      AS student_contact_id,
                            s.StudentContactSpouseTitle                   AS student_contact_title,
                            s.StudentContactSpousePreferredFormal         AS student_contact_given_name,
                            s.StudentContactSpousePreferred               AS student_contact_preferred,
                            s.StudentContactSpouseSurname                 AS student_contact_surname,
                            s.StudentContactSpouseEmail                   AS student_contact_home_email,
                            s.StudentContactSpouseDefaultEmail            AS student_contact_default_email,
                            s.StudentContactSpouseOccupEmail              AS student_contact_work_email,
                            s.StudentId                                   AS student_id,
                            s.StudentYearLevel                            AS student_year_level,
                            s.StudentCampus                               AS student_campus,
                            s.StudentHouse                                AS student_house,
                            s.StudentGender                               AS student_gender,
                            s.StudentPreferred                            AS student_preferred,
                            s.StudentSurname                              AS student_surname
            FROM            dbo.vStudentContactAllAddress s
            INNER JOIN      dbo.Community cs ON s.StudentContactSpouseID = cs.ID
            WHERE           s.StudentContactType IN ({$contactTypeBindings2['sql']}) AND
                            FileSemester = (SELECT FileSemester FROM FileSemesters WHERE SystemCurrentFlag = 1) AND
                            FileYear = (SELECT FileYear FROM FileSemesters WHERE SystemCurrentFlag = 1)

            ORDER BY        student_contact_id ASC

        ", array_merge(
                $contactTypeBindings1['bindings'],
                $contactTypeBindings2['bindings']
            )
        );

        return new Iterator\Guardian($guardianIterator);
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

        $staffIterator = $this->databaseExternal->executeQueryIterator("

            SELECT          vStaff.StaffID                                AS staff_id,
                            Community.NetworkLogin                        AS network_login,
                            ActiveFlag                                    AS active_flag,
                            vStaff.StaffTitle                             AS staff_title,
                            vStaff.StaffGiven1                            AS staff_given_name,
                            vStaff.StaffPreferredName                     AS staff_preferred_name,
                            vStaff.StaffSurname                           AS staff_surname,
                            Community.DefaultEmailCode                    AS default_email_code,
                            vCommunity.Email                              AS home_email,
                            vCommunity.DefaultEmail                       AS default_email,
                            vCommunity.OccupEmail                         AS work_email,
                            vStaff.StaffCampus                            AS staff_campus,
                            vStaff.StaffCategoryType                      AS staff_category_type,
                            vStaff.StaffCategory                          AS staff_category,
                            JobPositions.StaffJobPositionCode             AS staff_position,
                            vStaff.StaffDepartment                        AS staff_department,
                            vStaff.StaffYearLevel                         AS staff_year_level,
                            luYearLevel.Description                       AS staff_year_level_description,
                            vStaff.StaffHouse                             AS staff_house
            FROM            dbo.vStaff vStaff
            LEFT JOIN       dbo.luYearLevel ON luYearLevel.Code = vStaff.StaffYearLevel AND luYearLevel.Campus = vStaff.StaffCampus
            LEFT JOIN       dbo.Community ON Community.ID = vStaff.StaffID
            LEFT JOIN       dbo.vStaffJobPositions JobPositions ON vStaff.StaffID = JobPositions.StaffID AND
                            (JobPositions.StaffJobPositionStartDate < GETDATE() OR JobPositions.StaffJobPositionStartDate IS NULL) AND
                            (JobPositions.StaffJobPositionEndDate > GETDATE() OR JobPositions.StaffJobPositionEndDate IS NULL)
            LEFT JOIN       dbo.vCommunityAddresses vCommunity ON vStaff.StaffID = vCommunity.ID
            WHERE           ActiveFlag = 1 AND
                            vStaff.StaffRegistrationType != 'STDNT'
            ORDER BY        vStaff.StaffID ASC
        ");

        return new Iterator\Staff($staffIterator);
    }
}

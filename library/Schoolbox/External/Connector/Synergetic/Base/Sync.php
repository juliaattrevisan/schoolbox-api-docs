<?php

/**
 * Synergetic implementation of sync connector
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Synergetic\Base;

use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\ISync;
use Schoolbox\Sync\Iterator;

class Sync extends Base implements ISync
{
    /**
     * Default allowed student contact types for a contact record to be
     * considered a student-guardian relation:
     * * Contact 1/2/3 (SC1, SC2, SC3)
     */
    private const DEFAULT_STUDENT_CONTACT_TYPES = ['SC1', 'SC2', 'SC3',];


    /**
     * Default allowed relationship types for a contact record to be considered
     * a student-guardian relation:
     * * Son/daughter (SN, DR)
     * * Stepson/stepdaughter (STSN, STDR)
     * * Ward (WD)
     * * Grandson/granddaughter (GRASN, GRADR)
     * * Foster child (FC)
     * * Host parent/child (HOPT, HOCD)
     * ** added in SB-10620: don't know why parent is a default relationship
     * * Homestay (HS, HOMES, HSS)
     * ** HOMES added in SB-11362
     * ** HSS added in SA-1955
     */
    private const DEFAULT_STUDENT_RELATIONSHIPS = [
        'SN',
        'STSN',
        'DR',
        'STDR',
        'WD',
        'GRASN',
        'GRADR',
        'FOCD',
        'HOPT',
        'HOCD',
        'HS',
        'HOMES',
        'HSS',
    ];

    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        $studentIterator = $this->databaseExternal->getQueryIterator("

            SELECT          vStudentsAll.StudentID                           AS student_id,
                            NetworkLogin                                  AS network_login,
                            vStudentsAll.StudentTitle                        AS student_title,
                            vStudentsAll.StudentGiven1                       AS student_given_name,
                            vStudentsAll.StudentPreferred                    AS student_preferred,
                            vStudentsAll.StudentSurname                      AS student_surname,
                            StudentGender                                 AS student_gender,
                            StudentEmail                                  AS student_home_email,

                            CASE
                                WHEN Community.DefaultEmailCode = 'O' THEN Community.OccupEmail
                                ELSE Community.Email
                            END AS student_default_email,

                            StudentOccupEmail                             AS student_work_email,
                            vStudentsAll.StudentYearLevel                    AS student_year_level,
                            vStudentsAll.StudentHouse                        AS student_house,
                            vStudentsAll.StudentBoarder                      AS student_boarder,
                            vStudentsAll.StudentBoardingHouse                AS student_boarding_house,
                            StudentCampus                                 AS student_campus
            FROM            dbo.vStudentsAll vStudentsAll
            LEFT JOIN       Community ON Community.ID = StudentID
            WHERE           FileSemester = (SELECT FileSemester FROM FileSemesters WHERE SystemCurrentFlag = 1) AND
                            FileYear = (SELECT FileYear FROM FileSemesters WHERE SystemCurrentFlag = 1) AND
                            NOT (vStudentsAll.StudentStatusSynergyMeaning IN ('LEFT', 'LEAVING')
			                AND DATEDIFF(DAY, vStudentsAll.StudentLeavingDate, GETDATE()) > 0)
            ORDER BY        vStudentsAll.StudentID ASC
        ");

        return new Iterator\Student($studentIterator);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        // Contact types
        $contactTypes = trim($this->config->get('external_synergetic_student_contact_types'));
        if ($contactTypes === '') {
            $contactTypes = self::DEFAULT_STUDENT_CONTACT_TYPES;
        } else {
            $contactTypes = array_map("trim", explode(",", $contactTypes));
        }
        $contactTypeBindings1 = $this->databaseExternal->prepareInBindings($contactTypes);
        $contactTypeBindings2 = $this->databaseExternal->prepareInBindings($contactTypes);

        // Relationships
        $relationships = trim($this->config->get('external_synergetic_student_relationships'));
        if ($relationships === '') {
            $relationships = self::DEFAULT_STUDENT_RELATIONSHIPS;
        } else {
            $relationships = array_map("trim", explode(",", $relationships));
        }
        $relationshipBindings1 = $this->databaseExternal->prepareInBindings($relationships);
        $relationshipBindings2 = $this->databaseExternal->prepareInBindings($relationships);

        $guardianIterator = $this->databaseExternal->getQueryIterator(
            "SELECT DISTINCT
                            cp.NetworkLogin                               AS student_contact_network_login,
                            s.StudentContactID                            AS student_contact_id,
                            s.StudentContactTitle                         AS student_contact_title,
                            s.StudentContactPreferredFormal               AS student_contact_given_name,
                            s.StudentContactPreferred                     AS student_contact_preferred,
                            s.StudentContactSurname                       AS student_contact_surname,
                            s.StudentContactEmail                         AS student_contact_home_email,
                            s.StudentContactDefaultEmail                  AS student_contact_default_email,
                            s.StudentContactOccupEmail                    AS student_contact_work_email,
                            s.StudentContactType                          AS student_contact_type,
                            s.StudentId                                   AS student_id,
                            s.StudentYearLevel                            AS student_year_level,
                            s.StudentCampus                               AS student_campus,
                            s.StudentHouse                                AS student_house,
                            s.StudentGender                               AS student_gender,
                            s.StudentPreferred                            AS student_preferred,
                            s.StudentSurname                              AS student_surname
            FROM            dbo.vStudentContactAllAddress s
            INNER JOIN      dbo.Community cp ON s.StudentContactID = cp.ID
            INNER JOIN      dbo.Relationships r ON s.StudentID = r.relatedID
            WHERE           s.StudentContactType IN ({$contactTypeBindings1['sql']}) AND
                            r.Relationship IN ({$relationshipBindings1['sql']}) AND
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
                            s.StudentContactType                          AS student_contact_type,
                            s.StudentId                                   AS student_id,
                            s.StudentYearLevel                            AS student_year_level,
                            s.StudentCampus                               AS student_campus,
                            s.StudentHouse                                AS student_house,
                            s.StudentGender                               AS student_gender,
                            s.StudentPreferred                            AS student_preferred,
                            s.StudentSurname                              AS student_surname
            FROM            dbo.vStudentContactAllAddress s
            INNER JOIN      dbo.Community cs ON s.StudentContactSpouseID = cs.ID
            INNER JOIN      dbo.Relationships r ON s.StudentID = r.relatedID
            WHERE           s.StudentContactType IN ({$contactTypeBindings2['sql']}) AND
                            r.Relationship IN ({$relationshipBindings2['sql']}) AND
                            FileSemester = (SELECT FileSemester FROM FileSemesters WHERE SystemCurrentFlag = 1) AND
                            FileYear = (SELECT FileYear FROM FileSemesters WHERE SystemCurrentFlag = 1)

            ORDER BY        student_contact_id ASC",
            array_merge(
                $contactTypeBindings1['bindings'],
                $contactTypeBindings2['bindings'],
                $relationshipBindings1['bindings'],
                $relationshipBindings2['bindings'],
            )
        );

        return new Iterator\Guardian($guardianIterator);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $staffIterator = $this->databaseExternal->getQueryIterator("

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
                            JobPositions.StaffJobPositionCode             AS staff_position_code,
                            JobPositions.JobPositionDescription           AS staff_position,
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

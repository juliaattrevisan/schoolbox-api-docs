<?php
/**
 * Meriden-specific external user class
 *
 * The previously Cathedral-specific sync script queries are now the default,
 * the previous default logic is used only for Meriden.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Tass_Meriden_User extends External_Tass_User implements External_UserInterface
{
    /**
     * Retrieves staff user account info for sync script.
     *
     * @return array
     */
    public function getStaffUserAccountInfo()
    {
        // Include Synergetic fields, and attempt to perform data normalisation
        // using extra fields.
        $query = "
            SELECT
                t.tch_code AS StaffID,
                '' AS NetworkLogin,
                t.curr_flg AS ActiveFlag,
                '' AS StaffTitle,
                '' AS StaffPreferredName,
                t.surname AS StaffSurname,
                t.tch_name AS StaffFullName,
                '' AS DefaultEmailCode,
                '' AS OccupEmail,
                t.e_mail AS Email,
                '' AS StaffCampus,
                '' AS StaffCategoryType,
                '' AS StaffCategory,
                td.tch_dept AS StaffDepartment,
                *
            FROM teacher AS t
            JOIN tchdept AS td ON t.tch_code = td.tch_code
            WHERE t.curr_flg = 'Y'
        ";
        $staffList = $this->getDatabase()->trimAll($this->getDatabase()->getAll($query));
        return $staffList;
    }

    /**
     * Retrieves student user account info for sync script.
     *
     * @return array
     */
    public function getStudentUserAccountInfo()
    {
        // Get all students who have a campus, and don't have a leaving date?
        // (Maybe students who do have a leaving date should be deactivated instead)
        $query = "
            SELECT
                s.stud_code AS StudentID,
                '' AS NetworkLogin,
                '' AS StudentTitle,
                s.preferred_name AS StudentPreferred,
                s.surname AS StudentSurname,
                s.e_mail AS StudentEmail,
                '' AS StudentOccupEmail,
                s.year_grp AS StudentYearLevel,
                s.house AS StudentHouse,
                s.campus_code AS StudentCampus, *
                -- s.dol AS StudentLeavingDate
            FROM student AS s
            WHERE s.campus_code IS NOT NULL
            AND (s.dol IS NULL OR s.dol > getdate())
        ";
        $result = $this->getDatabase()->trimAll($this->getDatabase()->getAll($query));
        return $result;
    }

    /**
     * Retrieves parent user account info for sync script.
     *
     * @return array
     */
    public function getParentUserAccountInfo()
    {
        // status of TASS support for single, gay, transgendered or polygamous
        // parents - unknown
        // par_code: most likely login
        // According to getEnrolledStudents(): parent codes are prefixed with 'P'
        // There seems to be no separate accounts for separate parents, maybe
        // I'll have to create one.
        $query = "
            SELECT
                p.par_code AS StudentContactID,
                '' AS StudentContactTitle,
                p.f_name AS StudentContactPreferred,
                p.surname AS StudentContactSurname,
                '' AS StudentContactDefaultEmail,

                '' AS StudentContactSpouseID,
                '' AS StudentContactSpouseTitle,
                p.m_name AS StudentContactSpousePreferred,
                '' AS StudentContactSpouseSurname,
                '' AS StudentContactSpouseDefaultEmail,

                s.year_grp AS StudentYearLevel,
                s.campus_code AS StudentCampus,
                s.sex AS StudentGender,

                p.*
            FROM parent AS p
            -- INNER JOIN pia_sfaccess AS sf
            --    ON p.par_code = sf.par_code AND p.cmpy_code = sf.cmpy_code
            INNER JOIN student as s
                ON p.par_code = s.par_code AND p.cmpy_code = s.cmpy_code
            ORDER BY p.par_code ASC
        ";
        $list = $this->getDatabase()->trimAll($this->getDatabase()->getAll($query));
        return $list;
    }
}

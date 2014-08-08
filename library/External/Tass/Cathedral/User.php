<?php
/**
 * Cathedral-specific external user class
 *
 * Extended because Cathedral has some logic for sync scripts that I don't
 * necessarily want to introduce to Meriden, or other TASS schools.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Tass_Cathedral_User extends External_Tass_User {

    /**
     * Retrieves staff user account info for sync script.
     * Main difference from Meriden sync: ActiveFlag
     *
     * @return array
     */
    public function getStaffUserAccountInfo() {

        // Based on Cathedral sync (SB-4132)
        // Main Differences with Meriden sync: ActiveFlag
        $query = "
            SELECT
                t.emp_code AS StaffID,
                '' AS NetworkLogin,
                CASE
                    WHEN (t.term_date) IS NULL THEN 0
                    ELSE 1
                END AS ActiveFlag,
                t.salutation_flag AS StaffTitle,
                t.prefer_name_text AS StaffPreferredName,
                t.surname_text AS StaffSurname,
                '' AS StaffFullName,
                '' AS DefaultEmailCode,
                '' AS OccupEmail,
                t.e_mail AS Email,
                '' AS StaffCampus,
                '' AS StaffCategoryType,
                '' AS StaffCategory,
                td.tch_dept AS StaffDepartment,
                *
            FROM tel_vw_telemf AS t
            LEFT JOIN teacher AS te ON t.emp_code = te.emp_code
            LEFT JOIN tchdept AS td ON te.tch_code = td.tch_code
        ";

//        $query = "
//            SELECT
//                t.tch_code AS StaffID,
//                '' AS NetworkLogin,
//                t.curr_flg AS ActiveFlag,
//                '' AS StaffTitle,
//                '' AS StaffPreferredName,
//                t.surname AS StaffSurname,
//                t.tch_name AS StaffFullName,
//                '' AS DefaultEmailCode,
//                '' AS OccupEmail,
//                t.e_mail AS Email,
//                '' AS StaffCampus,
//                '' AS StaffCategoryType,
//                '' AS StaffCategory,
//                td.tch_dept AS StaffDepartment,
//                *
//            FROM teacher AS t
//            JOIN tchdept AS td ON t.tch_code = td.tch_code
//            WHERE t.curr_flg = 'Y'
//        ";
        $staffList = $this->getDatabase()->trimAll($this->getDatabase()->getAll($query));
        return $staffList;
    }

    /**
     * Retrieves parent user account info for sync script.
     *
     * @return array
     */
    public function getParentUserAccountInfo() {
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
                pa.e_mail AS StudentContactDefaultEmail,

                '' AS StudentContactSpouseID,
                '' AS StudentContactSpouseTitle,
                p.m_name AS StudentContactSpousePreferred,
                '' AS StudentContactSpouseSurname,
                '' AS StudentContactSpouseDefaultEmail,

                s.year_grp AS StudentYearLevel,
                s.campus_code AS StudentCampus,
                s.sex AS StudentGender,

                p.*,
                pa.*
            FROM parent AS p
            INNER JOIN paraddress as pa
                ON p.par_code = pa.par_code AND p.cmpy_code = pa.cmpy_code
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

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
     *
     * Main difference from standard TASS sync: ActiveFlag uses term_date
     * instead of curr_flg.
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
        $staffList = $this->getDatabase()->trimAll($this->getDatabase()->getAll($query));
        return $staffList;
    }

    /**
     * Retrieves parent user account info for sync script.
     *
     * Main difference from standard TASS sync: students are fetched from and
     * associated with students based on Split Family Access table.
     *
     * @return array
     */
    public function getParentUserAccountInfo() {
        // pia_sfaccess:
        // * both add_nums and stud_codes are comma-separated lists, unfortunately
        // joining students to parents:
        // * if pia_sfaccess: join if student.stud_code is in pia_sfaccess.stud_codes
        // * otherwise: always join
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
            LEFT JOIN pia_sfaccess AS sf
                ON p.par_code = sf.par_code
                AND (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0)
                AND p.cmpy_code = sf.cmpy_code
            INNER JOIN student as s
                ON p.par_code = s.par_code
                AND p.cmpy_code = s.cmpy_code
                AND (
                    sf.stud_codes IS NULL
                    OR (CHARINDEX(','+RTRIM(s.stud_code)+',', ','+RTRIM(sf.stud_codes)+',') > 0)
                )
            ORDER BY p.par_code ASC
        ";
        $list = $this->getDatabase()->trimAll($this->getDatabase()->getAll($query));
        return $list;
    }

}

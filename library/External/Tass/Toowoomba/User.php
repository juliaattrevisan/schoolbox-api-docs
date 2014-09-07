<?php
/**
 * Toowoomba-specific external user class
 *
 * Currently a direct copy of the Cathedral user class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Tass_Toowoomba_User extends External_Tass_User
{

    /**
     * Retrieves staff user account info for sync script.
     *
     * Main difference from standard TASS sync: ActiveFlag uses term_date
     * instead of curr_flg.
     *
     * @return array
     */
    public function getStaffUserAccountInfo()
    {

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
    public function getParentUserAccountInfo()
    {
        // pia_sfaccess:
        // * both add_nums and stud_codes are comma-separated lists, unfortunately
        // joining students to parents:
        // * if pia_sfaccess: join if student.stud_code is in pia_sfaccess.stud_codes
        // * otherwise: always join

        // pia_sfaccess: only join the relevant paraddress
        // no pia_sfaccess: get all paraddress
        $database = $this->getDatabase();
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

                sf.sfa_num,
                sf.stud_codes AS SplitStudentIDList,

                p.*,
                pa.*
            FROM parent AS p
            INNER JOIN paraddress as pa
                ON p.par_code = pa.par_code AND p.cmpy_code = pa.cmpy_code
            LEFT JOIN pia_sfaccess AS sf
                ON p.par_code = sf.par_code
                AND (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0)
                AND p.cmpy_code = sf.cmpy_code
            LEFT JOIN student as s
                ON p.par_code = s.par_code
                AND p.cmpy_code = s.cmpy_code
            ORDER BY p.par_code ASC
        ";
        $list = $database->trimAll($database->getAll($query));

        // Inject pia_sfaccess address numbers separately?

        // Inject student campuses, year levels, etc.
        $newList = array();
        foreach ($list as $listItem) {
            if (!empty($listItem['StudentYearLevel'])) {
                // Base related student accounts are fetched in base query.
                $newList[] = $listItem;
            }
            if (!empty($listItem['SplitStudentIDList'])) {
                // Inject pia_sfaccess children separately, because they may
                // not belong to the same parent account
                // (but they are still allowed access)
                $codes = array_map(
                    function ($code) use ($database) {
                        return "'".$database->quote($code)."'";
                    },
                    explode(',', $listItem['SplitStudentIDList'])
                );
                $splitStudentQuery = sprintf(
                    "SELECT
                        s.year_grp AS StudentYearLevel,
                        s.campus_code AS StudentCampus,
                        s.sex AS StudentGender
                    FROM student as s
                    WHERE s.stud_code IN (%s)",
                    implode(',', $codes)
                );
                foreach ($database->trimAll($database->getAll($splitStudentQuery)) as $child) {
                    $newList[] = array_merge($listItem, $child);
                }
            }
        }

        return $newList;
    }
}

<?php
/**
 * TASS implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Tass\Base;

use Config;
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
     * The TASS company code to use, for queries which require it.
     *
     * @var string
     */
    private $companyCode;

    /**
     * Sync constructor
     */
    public function __construct()
    {
        $this->database    = MSSQL::getInstance();
        $this->companyCode = Config::get('external_tass_company_code');
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        $query = "
            SELECT
                s.stud_code AS student_id,
                '' AS network_login,
                '' AS student_title,
                s.preferred_name AS student_preferred,
                s.surname AS student_surname,
                s.sex AS student_gender,
                s.e_mail AS student_email,
                '' AS student_occup_email,
                s.year_grp AS student_year_level,
                s.house AS student_house,
                (IF ((sb.resid_code IS NOT NULL), 'Y', 'N')) AS student_boarder,
                sb.resid_code AS student_boarding_house,
                s.campus_code AS student_campus
            FROM student AS s
            LEFT JOIN studboard AS sb ON s.stud_code = sb.stud_code AND s.cmpy_code = sb.cmpy_code
            WHERE s.cmpy_code = {$this->database->smartQuote($this->companyCode)}
            AND   s.dol IS NULL
        ";
        return new Iterator\Student(
            $this->database->getIterator($query)->addMap([$this->database, 'trimRow'])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        // Attempt to create separate accounts for parent and spouse
        // Not yet supported: children from split families
        $companyCode = $this->database->smartQuote($this->companyCode);
        $query = "
            SELECT
                'P' + p.par_code AS student_contact_id,
                ''               AS student_contact_title,
                p.f_name         AS student_contact_preferred,
                ''               AS student_contact_surname,
                ''               AS student_contact_default_email,
                sf.sfa_num       AS student_contact_split_family_number,
                s.year_grp       AS student_year_level,
                s.campus_code    AS student_campus,
                s.sex            AS student_gender,
                s.preferred_name AS student_preferred,
                s.surname        AS student_surname
            FROM parent AS p
            INNER JOIN paraddress as pa
                ON p.par_code = pa.par_code AND p.cmpy_code = pa.cmpy_code
            LEFT JOIN pia_sfaccess AS sf
                ON p.par_code = sf.par_code
                AND (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0)
                AND p.cmpy_code = sf.cmpy_code
            LEFT JOIN student as s
                ON (p.par_code = s.par_code AND p.cmpy_code = s.cmpy_code)
            WHERE p.cmpy_code = $companyCode
            AND   p.f_name IS NOT NULL

            UNION ALL

            SELECT
                'S' + p.par_code AS student_contact_id,
                ''               AS student_contact_title,
                p.m_name         AS student_contact_preferred,
                ''               AS student_contact_surname,
                ''               AS student_contact_default_email,
                sf.sfa_num       AS student_contact_split_family_number,
                s.year_grp       AS student_year_level,
                s.campus_code    AS student_campus,
                s.sex            AS student_gender,
                s.preferred_name AS student_preferred,
                s.surname        AS student_surname
            FROM parent AS p
            INNER JOIN paraddress as pa
                ON p.par_code = pa.par_code AND p.cmpy_code = pa.cmpy_code
            LEFT JOIN pia_sfaccess AS sf
                ON p.par_code = sf.par_code
                AND (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0)
                AND p.cmpy_code = sf.cmpy_code
            LEFT JOIN student as s
                ON (p.par_code = s.par_code AND p.cmpy_code = s.cmpy_code)
            WHERE p.cmpy_code = $companyCode
            AND   p.f_name IS NOT NULL

            ORDER BY student_contact_id ASC
        ";

        return new Iterator\Guardian(
            $this->database->getIterator($query)
                ->addMap([$this->database, 'trimRow'])
                ->addMap(function ($row) {
                    list($firstName, $surname)        = preg_split("/\\s+/", $row['student_contact_preferred'], 2);
                    $row['student_contact_preferred'] = $firstName;
                    $row['student_contact_surname']   = $surname;
                    return $row;
                })
                ->addMap(function ($row) {
                    if (isset($row['student_contact_split_family_number'])) {
                        $row['student_contact_id'] .= $row['student_contact_split_family_number'];
                    }
                    return $row;
                })
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $query = "
            SELECT
                te.tch_code AS StaffID,
                '' AS network_login,
                CASE
                    WHEN (t.term_date) IS NULL THEN 0
                    ELSE 1
                END AS ActiveFlag,
                t.salutation_flag AS StaffTitle,
                t.prefer_name_text AS StaffPreferredName,
                t.surname_text AS StaffSurname,
                '' AS DefaultEmailCode,
                '' AS OccupEmail,
                t.e_mail AS Email,
                '' AS StaffCampus,
                '' AS StaffCategoryType,
                '' AS StaffCategory,
                td.tch_dept AS StaffDepartment
            FROM tel_vw_telemf AS t
            LEFT JOIN teacher AS te ON t.emp_code = te.emp_code
            LEFT JOIN tchdept AS td ON te.tch_code = td.tch_code
            WHERE t.cmpy_code = {$this->database->smartQuote($this->companyCode)}
        ";
        return new Iterator\Staff(
            $this->database->getIterator($query)->addMap([$this->database, 'trimRow'])
        );
    }
}

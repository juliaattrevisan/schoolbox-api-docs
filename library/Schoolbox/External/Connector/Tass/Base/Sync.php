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
use External_Tass_Helper;
use MSSQL;
use Schoolbox\External\Connector\SyncInterface;
use Schoolbox\Sync\Iterator;

class Sync implements SyncInterface
{
    /**
     * @var MSSQL
     */
    protected $database;

    /**
     * The TASS company code to use, for queries which require it.
     *
     * @var string
     */
    protected $companyCode;

    /** @var External_Tass_Helper */
    private $helper;

    /**
     * Sync constructor
     */
    public function __construct()
    {
        $this->database    = MSSQL::getInstance();
        $this->companyCode = Config::get('external_tass_company_code');
        $this->helper = new External_Tass_Helper();
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        $query = "
            SELECT
                s.stud_code      AS student_id,
                s.alt_id         AS network_login,
                ''               AS student_title,
                s.given_name     AS student_given_name,
                s.preferred_name AS student_preferred,
                s.surname        AS student_surname,
                s.sex            AS student_gender,
                s.e_mail         AS student_email,
                ''               AS student_work_email,
                s.year_grp       AS student_year_level,
                s.house          AS student_house,
                CASE
                    WHEN (sb.resid_code) IS NOT NULL THEN 'Y'
                    ELSE 'N'
                END              AS student_boarder,
                sb.resid_code    AS student_boarding_house,
                s.campus_code    AS student_campus
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
        $companyCode = $this->database->smartQuote($this->companyCode);

        /* If Tass Comm rules are enabled then locate the guardian relationship
         * from there as well
         */
        if (Config::get('external_tass_commrules')) {

            $addnum = $this->helper->getAddNum();
            $typecode = $this->helper->getTypeCode();

            $studentjoin = "LEFT JOIN studcommrules as scr
                    ON p.par_code = scr.par_code
                    AND scr.add_num IN ({$addnum})
            ";

            if (!empty($typecode)) {
                $studentjoin .= "AND scr.commtype_code IN ({$typecode}) ";
            }

            $studentjoin .= "
                LEFT JOIN student as s
                    ON (p.par_code = s.par_code AND p.cmpy_code = s.cmpy_code)
                    OR (scr.par_code = scr.par_code AND scr.cmpy_code = s.cmpy_code)";
        } else {
            $studentjoin = "LEFT JOIN student as s
                    ON (p.par_code = s.par_code AND p.cmpy_code = s.cmpy_code)";
        }

        // Single Tuple contains data for [P]arent and [S]pouse
        $query = "
            SELECT DISTINCT
                'P' + p.par_code AS student_contact_id,
                ''               AS student_contact_title,
                p.f_name         AS student_contact_given_name,
                p.f_name         AS student_contact_preferred,
                p.surname        AS student_contact_surname,
                pa.e_mail        AS student_contact_default_email,
                sf.sfa_num       AS student_contact_split_family_number,
                s.year_grp       AS student_year_level,
                s.campus_code    AS student_campus,
                s.house          AS student_house,
                s.sex            AS student_gender,
                s.preferred_name AS student_preferred,
                s.surname        AS student_surname
            FROM parent AS p
            INNER JOIN paraddress as pa
                ON p.par_code = pa.par_code AND p.cmpy_code = pa.cmpy_code AND pa.add_num = '1'
            LEFT JOIN pia_sfaccess AS sf
                ON p.par_code = sf.par_code
                AND (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0)
                AND p.cmpy_code = sf.cmpy_code
            $studentjoin
            LEFT JOIN family f
                ON f.entity_code = p.par_code AND f.entity_gender = 'M'
            LEFT JOIN persondefs pd
                ON pd.person_num = f.person_num
            WHERE p.cmpy_code = $companyCode
            AND   p.f_name IS NOT NULL
            AND   s.given_name IS NOT NULL
            AND   (s.dol IS NULL OR CAST(s.dol AS DATE) >= CAST(GETDATE() AS DATE))
            AND   (pd.deceased_flg != 'Y' OR pd.deceased_flg IS NULL)

            UNION ALL

            SELECT DISTINCT
                'S' + p.par_code AS student_contact_id,
                ''               AS student_contact_title,
                p.m_name         AS student_contact_given_name,
                p.m_name         AS student_contact_preferred,
                p.surname        AS student_contact_surname,
                pa.e_mail        AS student_contact_default_email,
                sf.sfa_num       AS student_contact_split_family_number,
                s.year_grp       AS student_year_level,
                s.campus_code    AS student_campus,
                s.house          AS student_house,
                s.sex            AS student_gender,
                s.preferred_name AS student_preferred,
                s.surname        AS student_surname
            FROM parent AS p
            INNER JOIN paraddress as pa
                ON p.par_code = pa.par_code AND p.cmpy_code = pa.cmpy_code AND pa.add_num = '1'
            LEFT JOIN pia_sfaccess AS sf
                ON p.par_code = sf.par_code
                AND (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0)
                AND p.cmpy_code = sf.cmpy_code
            $studentjoin
            LEFT JOIN family f
                ON f.entity_code = p.par_code AND f.entity_gender = 'F'
            LEFT JOIN persondefs pd
                ON pd.person_num = f.person_num
            WHERE p.cmpy_code = $companyCode
            AND   p.m_name IS NOT NULL
            AND   s.given_name IS NOT NULL
            AND   (s.dol IS NULL OR CAST(s.dol AS DATE) >= CAST(GETDATE() AS DATE))
            AND   (pd.deceased_flg != 'Y' OR pd.deceased_flg IS NULL)

            ORDER BY student_contact_id ASC
        ";

        return new Iterator\Guardian(
            $this->database->getIterator($query)
                ->addMap([$this->database, 'trimRow'])
                ->addMap(function ($row) {
                    list($firstName, $surname)        = preg_split("/\\s+/", $row['student_contact_preferred'], 2);
                    $row['student_contact_preferred'] = $firstName;
                    if (strlen(trim($surname)) > 0) {
                        $row['student_contact_surname'] = $surname;
                    }
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
            SELECT DISTINCT
                CASE
                    WHEN
                        (te.tch_code) IS NULL
                        OR (te.tch_code) = ''
                    THEN t.emp_code
                    ELSE te.tch_code
                END                 AS staff_id,
                p.username          AS network_login,
                CASE
                    WHEN
                        (t.term_date) IS NULL
                        OR CAST(t.term_date AS DATE) >= CAST(GETDATE() AS DATE)
                    THEN 0
                    ELSE 1
                END                 AS active_flag,
                t.salutation_flag   AS staff_title,
                t.given_names_text  AS staff_given_name,
                t.prefer_name_text  AS staff_preferred_name,
                t.surname_text      AS staff_surname,
                ''                  AS default_email_code,
                t.school_email      AS work_email,
                t.e_mail            AS email,
                ''                  AS staff_campus,
                ''                  AS staff_year_level,
                ''                  AS staff_year_level_description,
                CASE
                    WHEN
                        (te.tch_code) IS NULL
                        OR (te.tch_code) = ''
                    THEN 'STAFF'
                    ELSE 'TEACHER'
                END                 AS staff_category_type,
                ''                  AS staff_category,
                td.tch_dept         AS staff_department
            FROM tel_vw_telemf AS t
            LEFT JOIN teacher AS te ON t.emp_code = te.emp_code AND t.cust_code = te.cmpy_code
            LEFT JOIN tchdept AS td ON te.tch_code = td.tch_code
            LEFT JOIN portalusers AS p ON prodcode='TIA' AND t.cust_code = p.cmpy_code AND (t.emp_code = p.portal_code OR te.tch_code = p.portal_code) 
            WHERE t.cust_code = {$this->database->smartQuote($this->companyCode)}
        ";
        return new Iterator\Staff(
            $this->database->getIterator($query)->addMap([$this->database, 'trimRow'])
        );
    }
}

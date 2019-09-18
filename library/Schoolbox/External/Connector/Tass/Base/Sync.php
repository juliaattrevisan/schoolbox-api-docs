<?php
/**
 * TASS implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Tass\Base;

use Alaress\Database\Connector\IConnector;
use External_Tass_Helper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\ISync;
use Schoolbox\IConfigLoader;
use Schoolbox\Sync\Iterator;

class Sync extends Base implements ISync
{
    /** @var string $companyCode The TASS company code to use, for queries which require it. */
    protected $companyCode;

    /** @var External_Tass_Helper $helper */
    protected $helper;

    /**
     * @inheritdoc
     */
    public function __construct(IConfigLoader $config, ?IConnector $databaseExternal)
    {
        parent::__construct($config, $databaseExternal);

        /* die if external db is disabled
         */
        if (null === $databaseExternal) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_DATABASE_UNAVAILABLE);
        }

        $this->databaseExternal->addPostProcessor([$this, 'trimWhitespace']);

        $this->companyCode = $config->get('external_tass_company_code');
        $this->helper = new External_Tass_Helper();
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        $iterator = $this->databaseExternal->getQueryIterator(
            "
                SELECT
                    s.stud_code      AS student_id,
                    s.alt_id         AS network_login,
                    ''               AS student_title,
                    s.given_name     AS student_given_name,
                    s.preferred_name AS student_preferred,
                    s.surname        AS student_surname,
                    s.sex            AS student_gender,
                    ''               AS student_home_email,
                    s.e_mail         AS student_default_email,
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
                WHERE s.cmpy_code = :companyCode
                AND   (s.dol IS NULL OR s.dol >= getdate())
            ",
            ['companyCode' => $this->companyCode]
        );

        return new Iterator\Student($iterator);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        /* If Tass Comm rules are enabled then locate the guardian relationship
         * from there as well
         */
        if ($this->config->get('external_tass_commrules')) {

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
                    OR (scr.stud_code = s.stud_code AND scr.cmpy_code = s.cmpy_code)";
        } else {
            $studentjoin = "LEFT JOIN student as s
                    ON (p.par_code = s.par_code AND p.cmpy_code = s.cmpy_code)";
        }

        // Single Tuple contains data for [P]arent and [S]pouse
        $iterator = $this->databaseExternal->getQueryIterator
        (
            "
                SELECT DISTINCT
                    CASE
                        WHEN
                            (sf.sfa_num) IS NULL
                        THEN 'P' + RTRIM(p.par_code)
                        ELSE 'P' + RTRIM(p.par_code) + RTRIM(sf.sfa_num)
                    END              AS student_contact_id,
                    f.person_num     AS student_contact_network_login,
                    ''               AS student_contact_title,
                    RTRIM(p.f_name)  AS student_contact_given_name,
                    LEFT(p.f_name, CHARINDEX(' ', p.f_name)) AS student_contact_preferred,
                    CASE
                        WHEN
                            LEN(RTRIM(SUBSTRING(p.f_name, CHARINDEX(' ', p.f_name), 255))) > 0
                        THEN RTRIM(SUBSTRING(p.f_name, CHARINDEX(' ', p.f_name), 255))
                        ELSE RTRIM(p.surname)
                    END              AS student_contact_surname,
                    ''               AS student_contact_home_email,
                    RTRIM(pa.e_mail) AS student_contact_default_email,
                    ''               AS student_contact_work_email,
                    s.year_grp       AS student_year_level,
                    s.campus_code    AS student_campus,
                    s.house          AS student_house,
                    s.sex            AS student_gender,
                    RTRIM(s.preferred_name) AS student_preferred,
                    RTRIM(s.surname) AS student_surname
                FROM parent AS p
                INNER JOIN paraddress as pa
                    ON p.par_code = pa.par_code AND p.cmpy_code = pa.cmpy_code AND pa.add_num = '1'
                LEFT JOIN pia_sfaccess AS sf
                    ON p.par_code = sf.par_code
                    AND p.cmpy_code = sf.cmpy_code
                {$studentjoin}
                LEFT JOIN family f
                    ON f.entity_code = p.par_code AND f.entity_gender = 'M'
                LEFT JOIN persondefs pd
                    ON pd.person_num = f.person_num
                WHERE p.cmpy_code = :companyCode
                AND   p.f_name IS NOT NULL
                AND   s.given_name IS NOT NULL
                AND   (s.dol IS NULL OR CAST(s.dol AS DATE) >= CAST(GETDATE() AS DATE))
                AND   (pd.deceased_flg != 'Y' OR pd.deceased_flg IS NULL)
                AND   ((CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') IS NULL)
                OR    (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0))

                UNION ALL

                SELECT DISTINCT
                    CASE
                        WHEN
                            (sf.sfa_num) IS NULL
                        THEN '{$this->helper->getSpousePrefix()}' + RTRIM(p.par_code)
                        ELSE '{$this->helper->getSpousePrefix()}' + RTRIM(p.par_code) + RTRIM(sf.sfa_num)
                    END              AS student_contact_id,
                    f.person_num     AS student_contact_network_login,
                    ''               AS student_contact_title,
                    RTRIM(p.m_name)  AS student_contact_given_name,
                    LEFT(p.m_name, CHARINDEX(' ', p.m_name)) AS student_contact_preferred,
                    CASE
                        WHEN
                            LEN(RTRIM(SUBSTRING(p.m_name, CHARINDEX(' ', p.m_name), 255))) > 0
                        THEN RTRIM(SUBSTRING(p.m_name, CHARINDEX(' ', p.m_name), 255))
                        ELSE RTRIM(p.surname)
                    END              AS student_contact_surname,
                    ''               AS student_contact_home_email,
                    RTRIM(pa.e_mail) AS student_contact_default_email,
                    ''               AS student_contact_work_email,
                    s.year_grp       AS student_year_level,
                    s.campus_code    AS student_campus,
                    s.house          AS student_house,
                    s.sex            AS student_gender,
                    RTRIM(s.preferred_name) AS student_preferred,
                    RTRIM(s.surname)        AS student_surname
                FROM parent AS p
                INNER JOIN paraddress as pa
                    ON p.par_code = pa.par_code AND p.cmpy_code = pa.cmpy_code AND pa.add_num = '1'
                LEFT JOIN pia_sfaccess AS sf
                    ON p.par_code = sf.par_code
                    AND p.cmpy_code = sf.cmpy_code
                {$studentjoin}
                LEFT JOIN family f
                    ON f.entity_code = p.par_code AND f.entity_gender = 'F'
                LEFT JOIN persondefs pd
                    ON pd.person_num = f.person_num
                WHERE p.cmpy_code = :companyCode2
                AND   p.m_name IS NOT NULL
                AND   s.given_name IS NOT NULL
                AND   (s.dol IS NULL OR CAST(s.dol AS DATE) >= CAST(GETDATE() AS DATE))
                AND   (pd.deceased_flg != 'Y' OR pd.deceased_flg IS NULL)
                AND   ((CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') IS NULL)
                OR    (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0))

                ORDER BY student_contact_id ASC
            ",
            [
                'companyCode' => $this->companyCode,
                'companyCode2' => $this->companyCode
            ]
        );

        return new Iterator\Guardian($iterator);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        $iterator = $this->databaseExternal->getQueryIterator
        (
            "
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
                    ''                  AS home_email,
                    t.e_mail            AS default_email,
                    t.school_email      AS work_email,
                    ''                  AS staff_house,
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
                    td.tch_dept         AS staff_department,
                    tc.campus_code      AS staff_campus
                FROM tel_vw_telemf AS t
                LEFT JOIN teacher AS te ON t.emp_code = te.emp_code AND t.cust_code = te.cmpy_code
                LEFT JOIN tchdept AS td ON te.tch_code = td.tch_code AND te.cmpy_code = td.cmpy_code
                LEFT JOIN tchcampus AS tc ON te.tch_code = tc.tch_code AND t.cust_code = tc.cmpy_code
                LEFT JOIN portalusers AS p ON prodcode='TIA' AND t.cust_code = p.cmpy_code AND (t.emp_code = p.portal_code OR te.tch_code = p.portal_code)
                WHERE t.cust_code = :companyCode
            ",
            ['companyCode' => $this->companyCode]
        );

        return new Iterator\Staff($iterator);
    }

    /**
     * Function to trim whitespace from array/value
     *
     * @param array|string $input
     * @return array|string $input
     */
    public function trimWhitespace($input)
    {
        if (is_array($input)) {
            $output = [];
            foreach ($input as $key => $value) {
                $output[$key] = trim($value);
            }
            return $output;
        } else {
            return trim($input);
        }
    }
}

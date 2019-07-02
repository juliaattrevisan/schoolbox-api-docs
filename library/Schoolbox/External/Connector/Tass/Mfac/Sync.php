<?php
/**
 * TASS implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\External\Connector\Tass\Mfac;

use Schoolbox\Sync\Iterator;

class Sync extends \Schoolbox\External\Connector\Tass\Base\Sync
{

    /**
     * Find the contents of each table
     *
     * - everything seems to join onto a (par_code, cmpy_code) tuple in one way or another
     * - P = primary parent; S = secondary parent (spouse)
     * - add_num and commtype_code are instance-configurable csv
     * - persondefs is metadata about a specific individual, not a nukular family unit
     * - sfa_num determines whether the parent+spouse is split (null === split family, otherwise nukular)
     *
     * @throws \Alaress\Database\Exception
     */
    private function explorer()
    {
        $tables = [
            'parent',
            'paraddress',
            'pia_sfaccess',
            'family',
            'persondefs',
            'student',
            'studcommrules',
        ];

        foreach ($tables as $table) {
            $query = "
                SELECT TOP 1 * 
                FROM {$table}
            ";

            $statement = $this->databaseExternal->prepareQuery($query);
            $statement->execute();

            while ($row = $statement->fetch()) {
                print_r("{$table} ");
                print_r($row);
            }
        }

        die();
    }


    /**
     * Show split families and their humans
     * Group them up neatly
     * @param string $studentjoin
     * @throws \Alaress\Database\Exception
     */
    private function locator(string $studentjoin)
    {
        $query = "
            SELECT DISTINCT
                sf.sfa_num,
                CASE
                    WHEN
                        (sf.sfa_num) IS NULL
                    THEN 'P' + RTRIM(p.par_code)
                    ELSE 'P' + RTRIM(p.par_code) + RTRIM(sf.sfa_num)
                END              AS student_contact_id,
                CASE
                    WHEN
                        (sf.sfa_num) IS NULL
                    THEN 'P' + RTRIM(p.par_code)
                    ELSE 'P' + RTRIM(p.par_code) + RTRIM(sf.sfa_num)
                END              AS student_contact_network_login,
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
                sf.sfa_num,
                CASE
                    WHEN
                        (sf.sfa_num) IS NULL
                    THEN 'S' + RTRIM(p.par_code)
                    ELSE 'S' + RTRIM(p.par_code) + RTRIM(sf.sfa_num)
                END              AS student_contact_id,
                CASE
                    WHEN
                        (sf.sfa_num) IS NULL
                    THEN 'S' + RTRIM(p.par_code)
                    ELSE REVERSE('S' + RTRIM(p.par_code) + RTRIM(sf.sfa_num))
                END              AS student_contact_network_login,
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
        ";

        $statement = $this->databaseExternal->prepareQuery($query);
        $statement->execute([
            'companyCode' => $this->companyCode,
            'companyCode2' => $this->companyCode
        ]);

        $stack = [];

        while ($row = $statement->fetch()) {
            if ($row['sfa_num']) {
                $rawId     = substr($row['student_contact_id'], 1);
                $contactId = $row['student_contact_id'];
                $studentId = "{$row['student_preferred']} {$row['student_surname']}";

                $stack[$rawId]['contacts'][$contactId] = array_intersect_key($row, [
                    'sfa_num' => '',
                    'student_contact_id' => '',
                    'student_contact_network_login' => '',
                    'student_contact_title' => '',
                    'student_contact_given_name' => '',
                    'student_contact_preferred' => '',
                    'student_contact_surname' => '',
                ]);

                $stack[$rawId]['students'][$studentId] = array_intersect_key($row, [
                    'student_preferred' => '',
                    'student_surname' => '',
                    'student_year_level' => '',
                    'student_campus' => '',
                    'student_house' => '',
                    'student_gender' => '',
                ]);
            }
        }

        print_r($stack);

        die();
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

            $studentjoin .= '
                LEFT JOIN student as s
                    ON (p.par_code = s.par_code AND p.cmpy_code = s.cmpy_code)
                    OR (scr.stud_code = s.stud_code AND scr.cmpy_code = s.cmpy_code)';
        } else {
            $studentjoin = 'LEFT JOIN student as s
                    ON (p.par_code = s.par_code AND p.cmpy_code = s.cmpy_code)';
        }

        $this->locator($studentjoin);

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
                        THEN 'S' + RTRIM(p.par_code)
                        ELSE 'S' + RTRIM(p.par_code) + RTRIM(sf.sfa_num)
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
}

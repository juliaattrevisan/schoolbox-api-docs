<?php

/**
 * TASS implementation of sync connector.
 *
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Tass\V53;

use Schoolbox\External\Connector\Tass\Base\Sync as BaseSync;
use Schoolbox\External\Connector\ISync;
use Schoolbox\Sync\Iterator;

class Sync extends BaseSync implements ISync
{
    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        // Get basic parent query
        [$query, $params] = $this->getParentQuery(false);

        // Append comm query, if necessary
        if ($this->config->get('external_tass_commrules')) {
            [$queryComm, $paramsComm] = $this->getParentQuery(true);
            $query  .= ' UNION ALL ' . $queryComm;
            $params = array_merge($params, $paramsComm);
        }

        // Let the guardian iterator use the specialized contact type
        // (from `$this->helper->getAddNumFather/Mother()`) preferentially over
        // the generic contact type (1).
        $query    .= '   ORDER BY student_contact_id ASC, student_contact_type DESC';
        $iterator = $this->databaseExternal->getQueryIterator($query, $params);
        return new Iterator\Guardian($iterator);
    }

    /**
     * Returns query and parameters to get parents
     * @param bool $useCommRules
     * @return array
     */
    public function getParentQuery(bool $useCommRules): array
    {
        /* If Tass Comm rules are enabled then locate the guardian relationship
         * from there as well
         */
        if ($useCommRules) {
            // Address number
            $addNum = $this->helper->getAddNum();

            // Communication rules type code
            $typeCode  = $this->helper->getTypeCode();
            $typeWhere = '';
            if (!empty($typeCode)) {
                $typeWhere = "AND scr.commtype_code IN ({$typeCode}) ";
            }

            $studentJoin = "
                LEFT JOIN studcommrules as scr
                    ON p.par_code = scr.par_code
                    AND scr.add_num IN ({$addNum})
                    {$typeWhere}
                LEFT JOIN student as s
                    ON (scr.stud_code = s.stud_code AND scr.cmpy_code = s.cmpy_code)
            ";

            // Just a place holder
            $placeHolder = 'CompanyCodeComm';
        } else {
            $studentJoin = "
                LEFT JOIN student as s
                    ON (p.par_code = s.par_code AND p.cmpy_code = s.cmpy_code)";

            // Just a place holder
            $placeHolder = 'CompanyCode';
        }

        // In order of [Father, Mother]...
        // V53 introduced family.person_posn. However there's still no 1-to-1 mapping
        // between family table and paraddress table
        $prefix        = ['P', $this->helper->getSpousePrefix()]; // not quoted
        $placeHolders  = [$placeHolder . '1', $placeHolder . '2'];
        // $familyIndex   = [1, 2];
        $gender        = ['M', 'F'];
        $addressNumber = [$this->helper->getAddNumFather(), $this->helper->getAddNumMother()]; // quoted

        $queries = [];
        $params  = [];

        // Get two queries, for father and for mother
        foreach ([0, 1] as $i) {
            $queries[] = "
                SELECT DISTINCT
                    CASE
                        WHEN
                            (sf.sfa_num) IS NULL
                        THEN '{$prefix[$i]}' + RTRIM(p.par_code)
                        ELSE '{$prefix[$i]}' + RTRIM(p.par_code) + RTRIM(sf.sfa_num)
                    END                      AS student_contact_id,
                    f.person_num             AS student_contact_network_login,
                    pd.title                 AS student_contact_title,
                    RTRIM(pd.first_name)     AS student_contact_given_name,
                    RTRIM(pd.preferred_name) AS student_contact_preferred,
                    RTRIM(pd.surname)        AS student_contact_surname,
                    RTRIM(pa.e_mail2)        AS student_contact_home_email,
                    RTRIM(pa.e_mail)         AS student_contact_default_email,
                    ''                       AS student_contact_work_email,
                    pa.add_num               AS student_contact_type,
                    s.year_grp               AS student_year_level,
                    s.campus_code            AS student_campus,
                    s.house                  AS student_house,
                    s.sex                    AS student_gender,
                    RTRIM(s.preferred_name)  AS student_preferred,
                    RTRIM(s.surname)         AS student_surname
                FROM parent AS p
                INNER JOIN paraddress as pa
                    ON p.par_code = pa.par_code
                    AND p.cmpy_code = pa.cmpy_code
                    AND pa.add_num IN ('1', {$addressNumber[$i]})
                LEFT JOIN pia_sfaccess AS sf
                    ON p.par_code = sf.par_code
                    AND p.cmpy_code = sf.cmpy_code
                {$studentJoin}
                INNER JOIN family f
                    ON f.entity_code = p.par_code
--                    AND f.person_posn = 'familyIndex'
                    AND (f.entity_type = 'P' OR f.entity_type = 'E')
                INNER JOIN persondefs pd
                    ON pd.person_num = f.person_num
                    AND pd.cmpy_code = p.cmpy_code
                    AND pd.gender = '{$gender[$i]}'
                    AND (pd.deceased_flg = 'N' OR pd.deceased_flg IS NULL)
                WHERE p.cmpy_code = :{$placeHolders[$i]}
                AND   s.given_name IS NOT NULL
                AND   (s.dol IS NULL OR CAST(s.dol AS DATE) >= CAST(GETDATE() AS DATE))
                AND   ((CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') IS NULL)
                OR    (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0))
            ";
            $params[$placeHolders[$i]] = $this->companyCode;
        }
        return [
            implode(' UNION ALL ', $queries),
            $params
        ];
    }
}

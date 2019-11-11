<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

/**
 * Class External_Tass_Mfac_User
 *
 * This file provides access to TASS user info
 */
class External_Tass_Mfac_User extends External_Tass_User
{

    /**
    * Get enrolled students
    *
    * @param $externalUserId
    * @return array
    */
    public function getEnrolledStudents(string $externalUserId): array
    {
        /* MFAC users either the externalId, for the primary parent:
         * e.g. ABC123
         *
         * or the one with the prefix, for the secondary parent:
         * e.g. SABC123
         *
         * We need to match on ABC123. Therefore if the externalUserId is greater than 6
         * characters long, make a leap of faith that we can truncate the first character away
         */
        if (strlen($externalUserId) > 6) {
            $id = substr($externalUserId, 1);
        } else {
            $id = $externalUserId;
        }

        $companyCode = $this->database->smartQuote($this->companyCode);
        $commrules = Config::get('external_tass_commrules');
        $addnum = $this->helper->getAddNum();
        $typecode = $this->helper->getTypeCode();

        /* If Tass Comm rules are enabled then locate the guardian relationship from there as well */

        $splitParentLength = Config::get('external_tass_parent_id_length') + 1;
        if (strlen($id) == $splitParentLength) {
            // This is a split family parent, deal with them differently
            $parentCode = substr($id, 0, $splitParentLength-1);
            $parentNum = $id[$splitParentLength-1];

            $query = "
                SELECT DISTINCT
                    sf.stud_codes AS RelatedID
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
                    AND (s.dol IS NULL OR s.dol >= getdate())
                WHERE sf.par_code = {$this->database->smartQuote($parentCode)}
                AND   sf.sfa_num  = {$this->database->smartQuote($parentNum)}
            ";

            if ($commrules) {
                $query .= "
                    UNION

                    SELECT DISTINCT
                        scr.stud_code AS RelatedID
                    FROM studcommrules as scr
                    WHERE cmpy_code = {$companyCode}
                    AND par_code = {$this->database->smartQuote($parentCode)}
                    AND add_num IN({$addnum})
                ";

                if (!empty($typecode)) {
                    $query .= "AND commtype_code IN ({$typecode}) ";
                }
            }

            $return = array();
            $result = $this->database->getAll($query);

            // parse the array, because this data is stored in a funky way
            if (count($result) && is_array($result)) {
                foreach ($result as $row) {
                    // data is stored in a single field, comma separated
                    $list = explode(',', $row['RelatedID']);
                    foreach ($list as $childID) {
                        $return[trim($childID)] = array('RelatedID' => trim($childID));
                    }
                }
            }
        } else {
            $query = "
                SELECT DISTINCT
                    RTRIM(s.stud_code) AS RelatedID
                FROM student s
                LEFT JOIN parent p
                    ON  p.par_code = s.par_code
                    AND p.cmpy_code = s.cmpy_code
                WHERE s.par_code = {$this->database->smartQuote($id)}
                AND (s.dol IS NULL OR s.dol >= getdate())
            ";
            if ($commrules) {
                $query .= "
                    UNION

                    SELECT DISTINCT
                        scr.stud_code AS RelatedID
                    FROM studcommrules as scr
                    WHERE cmpy_code = {$companyCode}
                    AND par_code = {$this->database->smartQuote($id)}
                    AND add_num IN ({$addnum})
                ";

                if (!empty($typecode)) {
                    $query .= "AND commtype_code IN ({$typecode}) ";
                }
            }

            $return = $this->database->getAll($query);
        }

        return $return;
    }
}

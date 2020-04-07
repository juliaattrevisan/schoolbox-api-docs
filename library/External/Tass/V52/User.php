<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

class External_Tass_V52_User extends External_Tass_User
{
    /**
     * @inheritDoc
     */
    public function getParentEmails(array $externalUserIds): array
    {
        $emailList = [];
        $idList    = implode(',', array_map([$this->database, 'smartQuote'], $externalUserIds));

        if (empty($idList)) {
            return [];
        }

        $addNum = $this->helper->getAddNum();

        $query = "
            SELECT pa.par_code, pa.e_mail, pa.e_mail2, pa.add_num
            FROM paraddress pa
            INNER JOIN parent p ON pa.par_code=p.par_code AND pa.cmpy_code=p.cmpy_code
            INNER JOIN student s ON p.par_code=s.par_code AND p.cmpy_code=s.cmpy_code
            WHERE
                  (pa.e_mail IS NOT NULL OR pa.e_mail2 IS NOT NULL) AND
                  s.stud_code IN ({$idList}) AND
                  pa.cmpy_code = {$this->companyCode} AND
                  pa.add_num IN ({$addNum})
        ";

        /* If Tass Comm rules are enabled then locate the guardian relationship
         * from there as well
         */
        if (Config::get('external_tass_commrules')) {

            $typeCode = $this->helper->getTypeCode();

            $typeWhere = '';
            if (!empty($typeCode)) {
                $typeWhere = "AND scr.commtype_code IN ({$typeCode}) ";
            }

            $query .= "
                UNION " .
                "SELECT DISTINCT pa.par_code, pa.e_mail, pa.e_mail2, pa.add_num
                FROM paraddress pa
                INNER JOIN studcommrules scr ON pa.par_code=scr.par_code
                    AND pa.cmpy_code = scr.cmpy_code
                    AND scr.cmpy_code = {$this->companyCode}
                    AND pa.add_num = scr.add_num
                    {$typeWhere}
                INNER JOIN student s ON scr.stud_code=s.stud_code AND scr.cmpy_code=s.cmpy_code
                WHERE
                    pa.e_mail IS NOT NULL AND
                    s.stud_code IN ({$idList}) AND
                    pa.cmpy_code = {$this->companyCode} AND
                    pa.add_num IN ({$addNum})
            ";
        }

        $statement = $this->database->query($query);
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch()) {
            $currentEmailList = array_filter(array_map('trim', explode(';', $row['e_mail'])));
            if ($row['e_mail2'] && !empty(trim($row['e_mail2']))) {
                $currentEmailList[] = trim($row['e_mail2']);
            }
            $i = 0;
            foreach ($currentEmailList as $email) {
                $emailList[trim($row['par_code']) . '-' . trim($row['add_num'] . '-' . $i++)] = $email;
            }
        }
        return $emailList;
    }


    /**
     * @inheritDoc
     */
    public function getStudentContactList(array $externalUserIds): array
    {
        if (empty($externalUserIds)) {
            return [];
        }
        $idList = implode(',', array_map([$this->database, 'smartQuote'], $externalUserIds));

        $charIndexQuery = [];
        foreach ($externalUserIds as $externalId) {
            $charIndexQuery[] = "(CHARINDEX(','+ '{$this->database->quote($externalId)}' +',', ','+RTRIM(sf.stud_codes)+',') > 0)";
        }
        $charIndexQueryString = implode(' OR ', $charIndexQuery);

        // In order of [Father, Mother]...
        $prefix        = ['P', $this->helper->getSpousePrefix()]; // not quoted
        $gender        = ['M', 'F'];
        $addressNumber = [$this->helper->getAddNumFather(), $this->helper->getAddNumMother()]; // quoted

        $queries = [];

        // Iterating twice, for Father and for Mother
        foreach ([0, 1] as $i) {
            // search student table
            $queries[] = "
            SELECT DISTINCT
                '{$prefix[$i]}' + RTRIM(parent.par_code)
                                         AS StudentContactID,
                pd.title                 AS StudentContactTitle,
                RTRIM(pd.preferred_name) AS StudentContactPreferred,
                RTRIM(pd.surname)        AS StudentContactSurname,
                COALESCE(RTRIM(pa.e_mail), RTRIM(pa.e_mail2))
                                         AS StudentContactDefaultEmail,
                COALESCE(RTRIM(pa.mobile1), RTRIM(pa.mobile2))
                                         AS StudentContactMobilePhone,
                pa.bus_phone             AS StudentContactOccupPhone,
                pa.home_phone            AS StudentContactPhoneActual,
                0                        AS StudentContactSilentPhoneFlag,
                ''                       AS StudentContactRelation,
                ''                       AS StudentContactSpouseID,
                ''                       AS StudentContactSpouseTitle,
                ''                       AS StudentContactSpousePreferred,
                ''                       AS StudentContactSpouseSurname,
                ''                       AS StudentContactSpouseMobilePhone,
                ''                       AS StudentContactSpouseOccupPhone,
                ''                       AS StudentContactSpouseDefaultEmail,
                parent.par_code          AS parentCode,
                pd.person_num            AS personNumber,
                pa.add_num               AS addressNumber
            FROM student
            JOIN parent
                ON student.par_code = parent.par_code
                AND student.cmpy_code = parent.cmpy_code
            INNER JOIN family
                ON parent.par_code = family.entity_code
                AND family.entity_gender = '{$gender[$i]}'
            INNER JOIN persondefs pd
                ON family.person_num = pd.person_num
                AND pd.deceased_flg = 'N' OR pd.deceased_flg IS NULL
            JOIN paraddress AS pa
                ON parent.par_code = pa.par_code
                AND pa.cmpy_code = parent.cmpy_code
                AND pa.add_num IN ({$addressNumber[$i]},'1')
            WHERE stud_code IN ({$idList})
            ";

            // search pia_sfaccess table for separated parents
            $queries[] = "
            SELECT DISTINCT
                '{$prefix[$i]}' + RTRIM(parent.par_code) + RTRIM(sf.sfa_num)
                                         AS StudentContactID,
                pd.title                 AS StudentContactTitle,
                RTRIM(pd.preferred_name) AS StudentContactPreferred,
                RTRIM(pd.surname)        AS StudentContactSurname,
                COALESCE(RTRIM(pa.e_mail), RTRIM(pa.e_mail2))
                                         AS StudentContactDefaultEmail,
                COALESCE(RTRIM(pa.mobile1), RTRIM(pa.mobile2))
                                         AS StudentContactMobilePhone,
                pa.bus_phone             AS StudentContactOccupPhone,
                pa.home_phone            AS StudentContactPhoneActual,
                0                        AS StudentContactSilentPhoneFlag,
                ''                       AS StudentContactRelation,
                ''                       AS StudentContactSpouseID,
                ''                       AS StudentContactSpouseTitle,
                ''                       AS StudentContactSpousePreferred,
                ''                       AS StudentContactSpouseSurname,
                ''                       AS StudentContactSpouseMobilePhone,
                ''                       AS StudentContactSpouseOccupPhone,
                ''                       AS StudentContactSpouseDefaultEmail,
                parent.par_code          AS parentCode,
                pd.person_num            AS personNumber,
                pa.add_num               AS addressNumber
            FROM pia_sfaccess AS sf
            JOIN parent
                ON parent.par_code = sf.par_code
                AND parent.cmpy_code = sf.cmpy_code
            INNER JOIN family
                ON parent.par_code = family.entity_code
                AND family.entity_gender = '{$gender[$i]}'
            INNER JOIN persondefs pd
                ON family.person_num = pd.person_num
                AND pd.deceased_flg = 'N' OR pd.deceased_flg IS NULL
            JOIN paraddress AS pa
                ON parent.par_code = pa.par_code
                AND pa.cmpy_code = parent.cmpy_code
                AND pa.add_num IN ({$addressNumber[$i]},'1')
                AND (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0)
            WHERE $charIndexQueryString
        ";
        }

        $query              = implode(' UNION ', $queries)
            . ' ORDER BY parentCode, personNumber, addressNumber DESC ';
        $iterator           = $this->database->getIterator($query);
        $parents            = [];
        $previousParentCode = '';
        $previousPersonNum  = '';

        // Each of the parents may get two entries with different address (paraddress). Keep the first entry only.
        foreach ($iterator as $row) {
            $row = $this->database->trimRow($row);
            if ($row['addressNumber'] === '1'
                && $previousParentCode === $row['parentCode']
                && $previousPersonNum === $row['personNumber']
            ) {
                continue;
            }
            $parents[]          = $row;
            $previousParentCode = $row['parentCode'];
            $previousPersonNum  = $row['personNumber'];
        }

        return $parents;
    }
}

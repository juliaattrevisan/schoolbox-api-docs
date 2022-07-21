<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\External\Normalise\TupleStack;

class External_Tass_V53_User extends External_Tass_User
{
    /**
     * @inheritDoc
     */
    public function getParentEmails(array $externalUserIds): array
    {
        if (empty($externalUserIds)) {
            return [];
        }
        $emailList  = [];
        $idBindings = PredicateBuilder::getBindings($externalUserIds, 'externalId');

        $addNumBindings = $this->helper->getAddNumBindings();

        $query = "
            SELECT pa.par_code, pa.e_mail, pa.e_mail2, pa.add_num
            FROM paraddress pa
            INNER JOIN parent p ON pa.par_code=p.par_code AND pa.cmpy_code=p.cmpy_code
            INNER JOIN student s ON p.par_code=s.par_code AND p.cmpy_code=s.cmpy_code
            WHERE
                  (pa.e_mail IS NOT NULL OR pa.e_mail2 IS NOT NULL) AND
                  s.stud_code IN ({$idBindings->getPlaceholder()}) AND
                  pa.cmpy_code = :companyCode AND
                  pa.add_num IN ({$addNumBindings->getPlaceholder()})
        ";

        $params = array_merge(
            $idBindings->getParams(),
            ['companyCode' => $this->companyCode,],
            $addNumBindings->getParams()
        );

        /* If Tass Comm rules are enabled then locate the guardian relationship
         * from there as well
         */
        if (Config::get('external_tass_commrules')) {
            $typeCodeBindings = $this->helper->getTypeCodeBindings();

            $typeWhere = '';
            if (!empty($typeCodeBindings->getParams())) {
                $typeWhere = "AND scr.commtype_code IN ({$typeCodeBindings->getPlaceholder()}) ";
                $params    = array_merge($params, $typeCodeBindings->getParams());
            }

            $idBindingsComm         = PredicateBuilder::getBindings($externalUserIds, 'externalIdComm');
            $addNumBindingsComm     = $this->helper->getAddNumBindings('addNumComm');
            $query                  .= "
                UNION " .
                "SELECT DISTINCT pa.par_code, pa.e_mail, pa.e_mail2, pa.add_num
                FROM paraddress pa
                INNER JOIN studcommrules scr ON pa.par_code=scr.par_code
                    AND pa.cmpy_code = scr.cmpy_code
                    AND scr.cmpy_code = :companyCode2
                    AND pa.add_num = scr.add_num
                    {$typeWhere}
                INNER JOIN student s ON scr.stud_code=s.stud_code AND scr.cmpy_code=s.cmpy_code
                WHERE
                    pa.e_mail IS NOT NULL AND
                    s.stud_code IN ({$idBindingsComm->getPlaceholder()}) AND
                    pa.add_num IN ({$addNumBindingsComm->getPlaceholder()})
            ";
            $params['companyCode2'] = $this->companyCode;
            $params                 = array_merge(
                $params,
                $idBindingsComm->getParams(),
                $addNumBindingsComm->getParams()
            );
        }

        $statement = $this->database->query($query, $params);
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch()) {
            $currentEmailList = array_filter(array_map('trim', explode(';', $row['e_mail'] ?? '')));
            if ($row['e_mail2'] && !empty(trim($row['e_mail2']))) {
                $currentEmailList[] = trim($row['e_mail2']);
            }
            $i = 0;
            foreach ($currentEmailList as $email) {
                $emailList[trim($row['par_code']) . '-' . trim($row['add_num'] . '-' . $i++)] = $email;
            }
        }
        return (new TupleStack())->normaliseValues($emailList, ';');
    }


    /**
     * @inheritDoc
     */
    public function getStudentContactList(array $externalUserIds): array
    {
        $externalUserIdChunks = array_chunk($externalUserIds, 2000);
        $results = [];
        foreach ($externalUserIdChunks as $externalUserIdChunk) {
            $result = $this->getStudentContactListChunk($externalUserIdChunk);
            $results = array_merge($results, $result);
        }
        return $results;
    }

    /**
     *
     * @param array $externalUserIds
     * @return array
     */
    private function getStudentContactListChunk(array $externalUserIds): array
    {
        if (empty($externalUserIds)) {
            return [];
        }
        $idList = implode(',', array_map([$this->database, 'legacyQuote'], $externalUserIds));

        $charIndexQuery = [];
        foreach ($externalUserIds as $externalId) {
            $charIndexQuery[] = "(CHARINDEX(','+ {$this->database->legacyQuote($externalId)} +',', ','+RTRIM(sf.stud_codes)+',') > 0)";
        }
        $charIndexQueryString = implode(' OR ', $charIndexQuery);

        // In order of [Father, Mother]...
        $prefix        = ['P', $this->helper->getSpousePrefix()]; // not quoted
        // $familyIndex   = [1, 2];
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
                AND parent.cmpy_code = family.cmpy_code
                -- AND family.person_posn = 'familyIndex'
                AND (family.entity_type = 'P' OR family.entity_type = 'E')
            INNER JOIN persondefs pd
                ON family.person_num = pd.person_num
                AND pd.cmpy_code = parent.cmpy_code
                AND pd.gender = '{$gender[$i]}'
                AND (pd.deceased_flg = 'N' OR pd.deceased_flg IS NULL)
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
                AND parent.cmpy_code = family.cmpy_code
                -- AND family.person_posn = 'familyIndex'
                AND (family.entity_type = 'P' OR family.entity_type = 'E')
            INNER JOIN persondefs pd
                ON family.person_num = pd.person_num
                AND pd.cmpy_code = parent.cmpy_code
                AND pd.gender = '{$gender[$i]}'
                AND (pd.deceased_flg = 'N' OR pd.deceased_flg IS NULL)
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
            if (
                $row['addressNumber'] === '1'
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

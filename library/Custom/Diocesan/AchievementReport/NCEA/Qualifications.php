<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Custom\Diocesan\AchievementReport\NCEA;

/**
 * Class Qualifications
 *
 * Because mssql won't let me do this in LiteracyNumeracy
 *
 * @package Custom\Diocesan\AchievementReport\NCEA
 */
class Qualifications extends Base
{

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $params = [
            'externalId' => $this->getExternalUserId(),
        ];

        $query = '
            SELECT *
            FROM dbo.uvSchoolBoxNCEAResults
            WHERE id = :externalId
            ORDER BY StudentYear DESC
        ';

        $result = $this->getDb()->getAll($query, $params);

        /* Qualifications results are accumulated through each record in the
         * NCEA table. One record may have none; but as the student progresses
         * and obtains more records, the NCEALevel1Description fields get
         * assigned values. At the change of each year sometimes the fields are
         * emptied. The accumulation is discontinuous.
         *
         * So you have to get all the records, traverse all the records, then
         * dump all the dupes/empty values
         */
        $keys = [
            'NCEALevel1Description',
            'NCEALevel2Description',
            'NCEALevel3Description'
        ];

        $qualifications = array_fill_keys($keys, '');
        $numQualifications = 0;

        if (is_array($result) && count($result)) {
            foreach ($result as $current) {
                foreach ($keys as $currentKey) {
                    if (strlen($current[$currentKey])) {
                        $qualifications[$currentKey] = $current[$currentKey];
                        $numQualifications++;
                    }
                }
            }
        }

        $data = [
            'qualifications' => $qualifications,
            'num' => $numQualifications
        ];

        return $data;
    }
}

<?php
/**
 * Qualifications
 *
 * Because mssql won't let me do this in LiteracyNumeracy
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\NCEA;

use Alaress_Probe as Probe;

class Qualifications extends Base {

    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
        // TODO: Implement setup() method.
    }

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $query = "
            SELECT
                *
            FROM
                SynergyOne.dbo.uvSchoolBoxNCEAResults
            WHERE
                id = '{$this->getExternalUserId()}'
            ORDER BY
                StudentYear DESC
        ";

        $result = $this->getDb()->getAll($query);

        /* Qualifications results are accumulated through each record in the
         * NCEA table. One record may have none; but as the student progresses
         * and obtains more records, the NCEALevel1Description fields get
         * assigned values. At the change of each year sometimes the fields are
         * emptied. The accumulation is discontinuous.
         *
         * So you have to get all the records, traverse all the records, then
         * dump all the dupes/empty values
         */
        $keys = array(
            'NCEALevel1Description',
            'NCEALevel2Description',
            'NCEALevel3Description'
        );

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

        $data = array(
            'qualifications' => $qualifications,
            'num' => $numQualifications
        );

        return $data;
    }
}
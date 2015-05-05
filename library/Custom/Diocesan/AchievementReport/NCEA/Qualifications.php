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
                NCEALevel3Description AS 'NCEA Level 3 Description',
                NCEALevel2Description AS 'NCEA Level 2 Description',
                NCEALevel1Description AS 'NCEA Level 1 Description'
            FROM
                SynergyOne.dbo.uvSchoolBoxNCEAResults
            WHERE
                id = '{$this->getExternalUserId()}'
        ";

        $result = $this->getDb()->getRow($query);

        if (is_array($result)) {
            return array_values(array_filter(array_values($result)));
        }

        return array();
    }
}
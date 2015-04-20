<?php
/**
 * LiteracyNumeracyCheckbox
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\NCEA;

use Alaress_Probe as Probe;

class LiteracyNumeracyCheckbox extends Base {
    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
        $this->setYear(date('Y'));
        $this->setHeading('Progress Grade Summary');
    }

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $externalUser = $this->getExternalUser();
        $externalUserId = $externalUser['StudentID'];

        $result = $this->getDb()->getRow("
            SELECT
                (SELECT COUNT(*)
                 FROM SynergyOne.dbo.uvSchoolBoxNCEAResults
                 WHERE id = '{$externalUserId}'
                 GROUP BY UELiteracyMet
                )/COUNT(*) AS 'UE Literacy',
                (SELECT COUNT(*)
                 FROM SynergyOne.dbo.uvSchoolBoxNCEAResults
                 WHERE id = '{$externalUserId}'
                 GROUP BY UENumeracyMet
                )/COUNT(*) AS 'UE Numeracy',
                (SELECT COUNT(*)
                 FROM SynergyOne.dbo.uvSchoolBoxNCEAResults
                 WHERE id = '{$externalUserId}'
                 GROUP BY Level1LiteracyMet
                )/COUNT(*) AS 'Level 1 Literacy',
                (SELECT COUNT(*)
                 FROM SynergyOne.dbo.uvSchoolBoxNCEAResults
                 WHERE id = '{$externalUserId}'
                 GROUP BY Level1NumeracyMet
                )/COUNT(*) AS 'Level 1 Numeracy'
            FROM
                SynergyOne.dbo.uvSchoolBoxNCEAResults
            WHERE
                id = '{$externalUserId}'
        ");

        if (is_array($result)) {
            return $result;
        }

        return array(
            'UELiteracyMet' => 0,
            'UENumeracyMet' => 0,
            'Level1LiteracyMet' => 0,
            'Level1NumeracyMet' => 0
        );
    }

    /**
     * Explorer, reporting!
     */
    private function explorerReporting() {
        $explorer = $this->getDb()->getAll("
            SELECT TOP 25 *
            FROM SynergyOne.dbo.uvSchoolBoxNCEAResults
        ");

//        $explorer = $this->getDb()->getAll("
//            SELECT
//                TABLE_CATALOG,
//                TABLE_SCHEMA,
//                TABLE_NAME,
//                TABLE_TYPE
//            FROM
//                INFORMATION_SCHEMA.TABLES
//            WHERE
//                TABLE_NAME LIKE '%uv%'
//        ");

        return $explorer;
    }
}
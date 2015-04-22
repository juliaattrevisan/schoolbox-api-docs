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

class LiteracyNumeracyCheckbox extends Base {

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = '';

    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
        $this->setYear(date('Y'));
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

        $query = "
            SELECT
                (SELECT TOP 1 COUNT(*)
                 FROM SynergyOne.dbo.uvSchoolBoxNCEAResults
                 WHERE id = '{$externalUserId}'
                 GROUP BY UELiteracyMet
                )/COUNT(*) AS 'UE Literacy',
                (SELECT TOP 1 COUNT(*)
                 FROM SynergyOne.dbo.uvSchoolBoxNCEAResults
                 WHERE id = '{$externalUserId}'
                 GROUP BY UENumeracyMet
                )/COUNT(*) AS 'UE Numeracy',
                (SELECT TOP 1 COUNT(*)
                 FROM SynergyOne.dbo.uvSchoolBoxNCEAResults
                 WHERE id = '{$externalUserId}'
                 GROUP BY Level1LiteracyMet
                )/COUNT(*) AS 'Level 1 Literacy',
                (SELECT TOP 1 COUNT(*)
                 FROM SynergyOne.dbo.uvSchoolBoxNCEAResults
                 WHERE id = '{$externalUserId}'
                 GROUP BY Level1NumeracyMet
                )/COUNT(*) AS 'Level 1 Numeracy'
            FROM
                SynergyOne.dbo.uvSchoolBoxNCEAResults
            WHERE
                id = '{$externalUserId}'
        ";

        $result = $this->getDb()->getRow($query);

        if (is_array($result)) {
            return $result;
        }

        return array(
            'UE Literacy' => 0,
            'UE Numeracy' => 0,
            'Level 1 Literacy' => 0,
            'Level 1 Numeracy' => 0
        );
    }
}
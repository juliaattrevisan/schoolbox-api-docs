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
                (SELECT TOP 1 COUNT(*)
                 FROM dbo.uvSchoolBoxNCEAResults
                 WHERE id = '{$this->getExternalUserId()}'
                 AND UELiteracyMet = 'Y'
                 GROUP BY UELiteracyMet
                ) AS 'UE Literacy',
                (SELECT TOP 1 COUNT(*)
                 FROM dbo.uvSchoolBoxNCEAResults
                 WHERE id = '{$this->getExternalUserId()}'
                 AND UENumeracyMet = 'Y'
                 GROUP BY UENumeracyMet
                ) AS 'UE Numeracy',
                (SELECT TOP 1 COUNT(*)
                 FROM dbo.uvSchoolBoxNCEAResults
                 WHERE id = '{$this->getExternalUserId()}'
                 AND Level1LiteracyMet = 'Y'
                 GROUP BY Level1LiteracyMet
                ) AS 'Level 1 Literacy',
                (SELECT TOP 1 COUNT(*)
                 FROM dbo.uvSchoolBoxNCEAResults
                 WHERE id = '{$this->getExternalUserId()}'
                 AND Level1NumeracyMet = 'Y'
                 GROUP BY Level1NumeracyMet
                ) AS 'Level 1 Numeracy'
            FROM
                dbo.uvSchoolBoxNCEAResults
            WHERE
                id = '{$this->getExternalUserId()}'
        ";

        $result = $this->getDb()->getRow($query);

        if (is_array($result)) {

            /* Cast everything as an int - mssql can return empty
             * count value, which is a shame
             */
            foreach ($result as $key => &$value) {
                $value += 0;
            }

            /* That stupid php bug
             */
            unset($value);

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
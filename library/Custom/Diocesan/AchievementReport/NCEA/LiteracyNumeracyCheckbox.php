<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Custom\Diocesan\AchievementReport\NCEA;

/**
 * Class LiteracyNumeracyCheckbox
 *
 * @package Custom\Diocesan\AchievementReport\NCEA
 */
class LiteracyNumeracyCheckbox extends Base
{

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = '';


    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $params = [
            'id1' => $this->getExternalUserId(),
            'id2' => $this->getExternalUserId(),
            'id3' => $this->getExternalUserId(),
            'id4' => $this->getExternalUserId(),
        ];

        $query = "
            SELECT
                (SELECT TOP 1 COUNT(*)
                 FROM dbo.uvSchoolBoxNCEAResults
                 WHERE id = :id1
                 AND UELiteracyMet = 'Y'
                 GROUP BY UELiteracyMet
                ) AS 'Level 2 Literacy',
                (SELECT TOP 1 COUNT(*)
                 FROM dbo.uvSchoolBoxNCEAResults
                 WHERE id = :id2
                 AND Level1LiteracyMet = 'Y'
                 GROUP BY Level1LiteracyMet
                ) AS 'Level 1 Literacy',
                (SELECT TOP 1 COUNT(*)
                 FROM dbo.uvSchoolBoxNCEAResults
                 WHERE id = :id3
                 AND Level1NumeracyMet = 'Y'
                 GROUP BY Level1NumeracyMet
                ) AS 'Level 1 Numeracy'
            FROM
                dbo.uvSchoolBoxNCEAResults
            WHERE
                id = :id4
        ";

        $result = $this->db->getRow($query, $params);

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

        return [
            'Level 2 Literacy' => 0,
            'Level 1 Literacy' => 0,
            'Level 1 Numeracy' => 0
        ];
    }
}

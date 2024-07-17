<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Dsfg\AchievementReport\NCEA;

use Alaress\Database\Connector\IConnector;
use Schoolbox\User;

class LiteracyNumeracyCheckbox extends Base
{
    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'Literacy/Numeracy';


    /**
     * Table heading suffix
     *
     * @var string
     */
    protected string $headingSuffix = '';


    /**
     * Constructor
     *
     * @param User        $user
     * @param array       $externalUser
     * @param IConnector  $db
     * @param string|null $year
     */
    public function __construct(User $user, array $externalUser, IConnector $db, $year)
    {
        parent::__construct($user, $externalUser, $db, $year);

        $reportYear = (
            // The report date is the most recently passed 31st of January.
            // So if the current month is January, the report year is the previous year.  Else it is the current year.
            date('n') === '1' ? (int)$this->currentYear - 1 : $this->currentYear
        );
        $this->headingSuffix = "as at 31 January $reportYear";
    }


    /**
     * Get heading suffix
     *
     * @return string
     */
    public function getHeadingSuffix(): string
    {
        return $this->headingSuffix;
    }


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
            'id5' => $this->getExternalUserId(),
        ];
return [];

        $query = "
            SELECT
                (SELECT TOP 1 COUNT(*)
                 FROM dbo.uvSchoolBoxNCEAResults
                 WHERE id = :id1
                 AND UELiteracyMet = 'Y'
                 GROUP BY UELiteracyMet
                ) AS 'UE Literacy',
                (SELECT TOP 1 COUNT(*)
                 FROM dbo.uvSchoolBoxNCEAResults
                 WHERE id = :id2
                 AND UENumeracyMet = 'Y'
                 GROUP BY UENumeracyMet
                ) AS 'UE Numeracy',
                (SELECT TOP 1 COUNT(*)
                 FROM dbo.uvSchoolBoxNCEAResults
                 WHERE id = :id3
                 AND Level1LiteracyMet = 'Y'
                 GROUP BY Level1LiteracyMet
                ) AS 'Level 1 Literacy',
                (SELECT TOP 1 COUNT(*)
                 FROM dbo.uvSchoolBoxNCEAResults
                 WHERE id = :id4
                 AND Level1NumeracyMet = 'Y'
                 GROUP BY Level1NumeracyMet
                ) AS 'Level 1 Numeracy'
            FROM
                dbo.uvSchoolBoxNCEAResults
            WHERE
                id = :id5
        ";

        $result = $this->db->executeQuery($query, $params)->fetch();

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
}

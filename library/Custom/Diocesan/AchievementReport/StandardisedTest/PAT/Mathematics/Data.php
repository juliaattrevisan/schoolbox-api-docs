<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\PAT\Mathematics;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base;
use Custom\Diocesan\AchievementReport\StandardisedTest\Stanine\Payload;

/**
 * Class Data
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest
 */
class Data extends Base
{

    /**
     * Graph title
     */
    private const GRAPH_TITLE = 'PAT Mathematics';


    /**
     * Find all the data for PAT Mathematics graph for the year specified
     *
     * @return Payload
     */
    public function findData(): Payload
    {
        $params = [
            'externalId' => $this->getExternalUserId(),
            'year' => $this->year
        ];

        $query = '
            SELECT
                Seq,
                ThisStudentStanine,
                IndividualMathsPATStanineMarker,
                DiocesanMathsPATStaninePercentage,
                NationalMathsPATStaninePercentage
            FROM uvSchoolBoxPATSTARSummary
            WHERE ThisStudentID = :externalId
            AND Fileyear = :year
            ORDER BY seq';

        $result = $this->getDb()->getAll($query, $params);

        /* Find data for each of the three series
         */
        $payload = new Payload(self::GRAPH_TITLE, (int)$this->year);

        foreach ($result as $row) {
            $payload
                ->aggregateStudent((string)$row['Seq'], (string)$row['IndividualMathsPATStanineMarker'])
                ->aggregateDiocesan((string)$row['Seq'], (string)$row['DiocesanMathsPATStaninePercentage'])
                ->aggregateNational((string)$row['Seq'], (string)$row['NationalMathsPATStaninePercentage']);
        }

        return $payload;
    }


    /**
     * Find out if this student has data from any year
     *
     * @return bool
     */
    public function hasDataFromAnyYear(): bool
    {
        $params = [
            'externalId' => $this->getExternalUserId(),
        ];

        $query = '
            SELECT
                Seq,
                ThisStudentStanine,
                IndividualMathsPATStanineMarker,
                DiocesanMathsPATStaninePercentage,
                NationalMathsPATStaninePercentage
            FROM uvSchoolBoxPATSTARSummary
            WHERE ThisStudentID = :externalId
            AND IndividualMathsPATStanineMarker <> 0
            ORDER BY seq';

        $result = $this->getDb()->getAll($query, $params);

        return (bool)\count($result);
    }
}

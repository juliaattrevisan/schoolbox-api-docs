<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest;

use Custom\Diocesan\AchievementReport\StandardisedTest\Stanine\Payload;

/**
 * Class PATMathematics
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest
 */
class PATMathematics extends Base
{

    /**
     * Graph title
     */
    private const GRAPH_TITLE = 'PAT Mathematics';


    /**
     * Find all the data for PAT Mathematics graph
     *
     * @return Payload
     */
    public function findData(): Payload
    {
        /* Default to current year if none was specified
         */
        $this->year ?: $this->year = (int)date('Y');

        $query = "
            SELECT 
                Seq,
                ThisStudentStanine,
                IndividualMathsPATStanineMarker,
                DiocesanMathsPATStaninePercentage,
                NationalMathsPATStaninePercentage
            FROM uvSchoolBoxPATSTARSummary
            WHERE ThisStudentID = '{$this->externalUser['StudentID']}'
            AND Fileyear = '{$this->year}'
            ORDER BY seq
        ";

        $result = $this->getDb()->getAll($query);

        /* Find data for each of the three series
         */
        $payload = new Payload(self::GRAPH_TITLE, $this->year);

        foreach ($result as $row) {
            $payload
                ->aggregateStudent((string)$row['Seq'], (string)$row['IndividualMathsPATStanineMarker'])
                ->aggregateDiocesan((string)$row['Seq'], (string)$row['DiocesanMathsPATStaninePercentage'])
                ->aggregateNational((string)$row['Seq'], (string)$row['NationalMathsPATStaninePercentage']);
        }

        return $payload;
    }
}

<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest;

use Custom\Diocesan\AchievementReport\StandardisedTest\Stanine\Payload;

/**
 * Class PATReadingComprehension
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest
 */
class PATReadingComprehension extends Base
{

    /**
     * Find all the data for PAT Reading Comprehension graph
     *
     * @return Payload
     */
    public function findData(): Payload
    {
        /* Default to current year if none was specified
         */
        $this->year ?: $this->year = date('Y');

        $query = "
            SELECT 
                Seq,
                ThisStudentStanine,
                IndividualReadingComprehensionPATStanineMarker,
                DiocesanReadingComprehensionPATStaninePercentage,
                NationalReadingComprehensionPATStaninePercentage
            FROM uvSchoolBoxPATSTARSummary
            WHERE ThisStudentID = '{$this->externalUser['StudentID']}'
            AND Fileyear = '{$this->year}'
            ORDER BY seq
        ";

        $result = $this->getDb()->getAll($query);

        /* Find data for each of the three series
         */
        $payload = new Payload('PAT Reading Comprehension', $this->year);

        foreach ($result as $row) {
            $payload
                ->aggregateStudent((bool)$row['ThisStudentStanine'], (string)$row['Seq'], (string)$row['IndividualReadingComprehensionPATStanineMarker'])
                ->aggregateDiocesan((string)$row['Seq'], (string)$row['DiocesanReadingComprehensionPATStaninePercentage'])
                ->aggregateNational((string)$row['Seq'], (string)$row['NationalReadingComprehensionPATStaninePercentage'])
                ->setHasData(true);
        }

        return $payload;
    }
}

<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\PAT\ReadingComprehension;

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
    private const GRAPH_TITLE = 'PAT Reading Comprehension';


    /**
     * Find all the data for PAT Reading Comprehension graph
     *
     * @return Payload
     */
    public function findData(): Payload
    {
        $query = "
            SELECT
                Seq,
                ThisStudentStanine,
                IndividualReadingComprehensionPATStanineMarker,
                DiocesanReadingComprehensionPATStaninePercentage,
                NationalReadingComprehensionPATStaninePercentage
            FROM uvSchoolBoxPATSTARSummary
            WHERE ThisStudentID = '{$this->externalUser['StudentID']}'";

        if ($this->year) {
            $query .= " AND Fileyear = '{$this->year}' ";
        }

        $query .= " ORDER BY seq ";

        $result = $this->getDb()->getAll($query);

        /* Find data for each of the three series
         */
        $payload = new Payload(self::GRAPH_TITLE, (int)$this->year);

        foreach ($result as $row) {
            $payload
                ->aggregateStudent((string)$row['Seq'], (string)$row['IndividualReadingComprehensionPATStanineMarker'])
                ->aggregateDiocesan((string)$row['Seq'], (string)$row['DiocesanReadingComprehensionPATStaninePercentage'])
                ->aggregateNational((string)$row['Seq'], (string)$row['NationalReadingComprehensionPATStaninePercentage']);
        }

        return $payload;
    }
}

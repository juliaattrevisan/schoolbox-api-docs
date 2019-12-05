<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\PAT\ListeningComprehension;

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
    private const GRAPH_TITLE = 'PAT Listening Comprehension';


    /**
     * Find all the data for PAT Listening Comprehension graph
     *
     * @return Payload
     */
    public function findData(): Payload
    {
        $query = "
            SELECT
                Seq,
                ThisStudentStanine,
                IndividualListeningComprehensionPATStanineMarker,
                DiocesanListeningComprehensionPATStaninePercentage,
                NationalListeningComprehensionPATStaninePercentage
            FROM uvSchoolBoxPATSTARSummary
            WHERE ThisStudentID = '{$this->externalUser['StudentID']}'
            AND Fileyear = '{$this->year}'
            ORDER BY seq";

        $result = $this->getDb()->getAll($query);

        /* Find data for each of the three series
         */
        $payload = new Payload(self::GRAPH_TITLE, (int)$this->year);

        foreach ($result as $row) {
            $payload
                ->aggregateStudent((string)$row['Seq'], (string)$row['IndividualListeningComprehensionPATStanineMarker'])
                ->aggregateDiocesan((string)$row['Seq'], (string)$row['DiocesanListeningComprehensionPATStaninePercentage'])
                ->aggregateNational((string)$row['Seq'], (string)$row['NationalListeningComprehensionPATStaninePercentage']);
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
        $query = "
            SELECT
                Seq,
                ThisStudentStanine,
                IndividualListeningComprehensionPATStanineMarker,
                DiocesanListeningComprehensionPATStaninePercentage,
                NationalListeningComprehensionPATStaninePercentage
            FROM uvSchoolBoxPATSTARSummary
            WHERE ThisStudentID = '{$this->externalUser['StudentID']}'
            AND IndividualListeningComprehensionPATStanineMarker <> 0
            ORDER BY seq";

        $result = $this->getDb()->getAll($query);

        return (bool)\count($result);
    }
}

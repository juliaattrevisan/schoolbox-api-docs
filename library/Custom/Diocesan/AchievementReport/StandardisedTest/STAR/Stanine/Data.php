<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\STAR\Stanine;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base;
use Custom\Diocesan\AchievementReport\StandardisedTest\Stanine\Payload;

/**
 * Class Data
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\STAR\Stanine
 */
class Data extends Base
{

    /**
     * Find all the data for STAR Stanine graph
     *
     * @return Payload
     */
    public function findData(): Payload
    {
        $params = [
            'externalId' => $this->externalUser['StudentID'],
            'year' => $this->year
        ];

        $query = '
            SELECT
                YearLevel,
                Seq,
                ThisStudentStanine,
                IndividualStarTestStanineMarker,
                DiocesanStarTestStaninePercentage,
                NationalStarTestStaninePercentage
            FROM uvSchoolBoxPATSTARSummary
            WHERE ThisStudentID = :externalId
            AND Fileyear = :year
            ORDER BY Seq
        ';

        $result = $this->getDb()->getAll($query, $params);

        /* Find data for each of the three series
         * Inspect the data to find out what Year Level this actually was, for the given student
         */
        $payload = new Payload('', (int)$this->year);
        $year = '';

        foreach ($result as $row) {
            $year = $row['YearLevel'];

            $payload
                ->aggregateStudent((string)$row['Seq'], (string)$row['IndividualStarTestStanineMarker'])
                ->aggregateDiocesan((string)$row['Seq'], (string)$row['DiocesanStarTestStaninePercentage'])
                ->aggregateNational((string)$row['Seq'], (string)$row['NationalStarTestStaninePercentage']);
        }

        /* Set the heading after the fact
         * We needed the actual year level which can only be obtained by inspecting the data
         */
        $payload->setTitle("Percentage of Year {$year} Children by Stanine");

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
            'externalId' => $this->externalUser['StudentID'],
        ];

        $query = '
            SELECT COUNT(*) AS num
            FROM uvSchoolBoxPATSTARSummary
            WHERE ThisStudentID = :externalId
            AND IndividualStarTestStanineMarker <> 0
        ';

        $result = $this->getDb()->getOne($query, $params);

        return (bool)$result;
    }
}

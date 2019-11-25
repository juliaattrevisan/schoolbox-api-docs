<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest;

/**
 * Class PATMathematics
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest
 */
class PATMathematics extends Base
{

    /**
     * Find all the data for PAT Mathematics graph
     *
     * @return array
     */
    public function findData(): array
    {
        /* Default to current year if none was specified
         */
        $this->year ?: $this->year = date('Y');

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

        new \Alaress_Probe($result, $this->externalUser['StudentID'], $this->year);

        return [];
    }
}

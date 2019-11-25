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

        /* Find data for each of the three series
         */
        $stack = [
            'graph' => [
                'title' => "PAT Mathematics {$this->year}",
                'y' => '% of Children'
            ],
            'data' => [
                'student' => [],
                'diocesan' => [],
                'national' => [],
            ]
        ];

        /* An assumption.
         *
         * ThisStudentStanine looks like it equates to Seq, which is the x-axis.
         * ThisStudentStatnine an either equate to Seq, or be zero.
         * If ThisStudentStatnine is zero, I don't render anything.
         *
         * Otherwise, I render the value of IndividualMathsPATStanineMarker in the column
         * corresponding to ThisStudentStanine.
         */
        foreach ($result as $row) {
            if ($row['ThisStudentStanine']) {
                $stack['data']['student'][$row['Seq']] = $row['IndividualMathsPATStanineMarker'];
            } else {
                $stack['data']['student'][$row['Seq']] = 'null';
            }

            $stack['data']['diocesan'][$row['Seq']] = $row['DiocesanMathsPATStaninePercentage'];
            $stack['data']['national'][$row['Seq']] = $row['NationalMathsPATStaninePercentage'];
        }

        return $stack;
    }
}

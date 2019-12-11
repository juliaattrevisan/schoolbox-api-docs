<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Custom\Diocesan\AchievementReport\NCEA;

/**
 * Class CourseCreditSummary
 *
 * @package Custom\Diocesan\AchievementReport\NCEA
 */
class CourseCreditSummary extends Base
{

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'Course Credit Summary';


    /**
     * Find data set
     *
     * @return array
     */
    private function findCourseCreditSummary(): array
    {
        $params = [
            'externalId' => $this->getExternalUserId(),
            'year' => $this->getYear()
        ];

        $query = '
            SELECT
                StudentYear,
                CourseDescription,
                InternalorExternalAssessment,
                Result,
                StrippedResult,
                EnteredCredits,
                ResultCredits,
                Endorsement
            FROM
                dbo.uvSchoolBoxNCEAResults
            WHERE
                id = :externalId AND
                WithdrawnFlag = 0 AND
                StudentYear = :year
        ';

        $result = $this->getDb()->getAll($query, $params);

        if (is_array($result)) {
            return $result;
        }

        return [];
    }


    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $finalStack = [
            'courses' => [],
            'totals' => [
                'enteredCredits' => 0,
                'letters' => array_fill_keys($this->getLetterRegistry(), 0),
                'achieved' => 0,
                'attempted' => 0,
            ]
        ];

        $allResults = $this->findCourseCreditSummary();

        foreach ($allResults as $current) {
            $letter = $current['StrippedResult'];
            $courseName = $current['CourseDescription'];
            $internalExternal = $current['InternalorExternalAssessment'];
            $endorsement = $current['Endorsement'];

            if (!is_array($finalStack['courses'][$courseName])) {
                $finalStack['courses'][$courseName] = [
                    'enteredCredits' => 0,
                    'letters' => array_fill_keys($this->getLetterRegistry(), 0),
                    'achieved' => 0,
                    'attempted' => 0,
                    'endorsement' => '',
                ];
            }

            /* Entered Credits accumulator
             */
            $finalStack['courses'][$courseName]['enteredCredits'] += $current['EnteredCredits'];
            $finalStack['totals']['enteredCredits'] += $current['EnteredCredits'];

            /* Endorsement
             */
            $finalStack['courses'][$courseName]['endorsement'] = $endorsement;

            /* Only accumulate non-progressive results
             *
             * Only accumulate data in certain conditions based on
             * Internal/External and StudentYear
             */
            if (!$this->isProgressive($current['Result'])) {
                if ($this->includeData($internalExternal, $current['StudentYear'])) {

                    /* Only accumulate A/M/E values
                     */
                    if (in_array($current['StrippedResult'], $this->achievementLetterRegistry, false)) {
                        $finalStack['courses'][$courseName]['letters'][$letter] += $current['ResultCredits'];
                        $finalStack['courses'][$courseName]['attempted'] += $current['ResultCredits'];
                        $finalStack['courses'][$courseName]['achieved'] += $current['ResultCredits'];

                        $finalStack['totals']['attempted'] += $current['ResultCredits'];
                        $finalStack['totals']['achieved'] += $current['ResultCredits'];
                        $finalStack['totals']['letters'][$letter] += $current['ResultCredits'];
                    }
                    /* Special treatment
                     * No achievements for N, V, Y, Z but their attempts should still
                     * be summated (ie. you tried and didn't get an achievement)
                     */
                    elseif (in_array($letter, ['N', 'V', 'Y', 'Z'])) {
                        $finalStack['courses'][$courseName]['letters'][$letter] += $current['EnteredCredits'];
                        $finalStack['courses'][$courseName]['attempted'] += $current['EnteredCredits'];

                        $finalStack['totals']['attempted'] += $current['EnteredCredits'];
                        $finalStack['totals']['letters'][$letter] += $current['EnteredCredits'];
                    }
                }
            }
        }

        ksort($finalStack['courses']);

        return $finalStack;
    }
}

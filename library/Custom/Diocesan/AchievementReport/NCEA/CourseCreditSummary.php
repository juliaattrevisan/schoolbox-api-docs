<?php
/**
 * CourseCreditSummary
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\NCEA;

class CourseCreditSummary extends Base {

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'Course Credit Summary';

    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
    }

    /**
     * Find data set
     *
     * @return array|bool
     */
    private function findCourseCreditSummary() {
        $query = "
            SELECT
                StudentYear,
                CourseDescription,
                InternalorExternalAssessment,
                Result,
                StrippedResult,
                EnteredCredits,
                ResultCredits
            FROM
                SynergyOne.dbo.uvSchoolBoxNCEAResults
            WHERE
                id = '{$this->getExternalUserId()}' AND
                WithdrawnFlag = 0 AND
                StudentYear = '{$this->getYear()}'
        ";

        $result = $this->getDb()->getAll($query);

        if (is_array($result)) {
            return $result;
        }

        return array();
    }

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $finalStack = array(
            'courses' => array(),
            'totals' => array(
                'enteredCredits' => 0,
                'letters' => array_fill_keys($this->getLetterRegistry(), 0),
                'achieved' => 0,
                'attempted' => 0,
            )
        );

        $allResults = $this->findCourseCreditSummary();

        foreach ($allResults as $current) {
            $letter = $current['StrippedResult'];
            $courseName = $current['CourseDescription'];
            $internalExternal = $current['InternalorExternalAssessment'];

            if (!is_array($finalStack['courses'][$courseName])) {
                $finalStack['courses'][$courseName] = array(
                    'enteredCredits' => 0,
                    'letters' => array_fill_keys($this->getLetterRegistry(), 0),
                    'achieved' => 0,
                    'attempted' => 0,
                );
            }

            /* Entered Credits accumulator
             */
            $finalStack['courses'][$courseName]['enteredCredits'] += $current['EnteredCredits'];
            $finalStack['totals']['enteredCredits'] += $current['EnteredCredits'];

            /* Only accumulate nonprogressive results
             */
            if (!$this->isProgressive($current['Result'])) {

                /* Letter accumulator
                 */
                if ($this->includeData($internalExternal, $current['StudentYear'])) {
                    $finalStack['courses'][$courseName]['letters'][$letter] += $current['ResultCredits'];
                    $finalStack['totals']['letters'][$letter] += $current['ResultCredits'];

                    /* Achieved Accumulator
                     * Only accumulate A/M/E values
                     */
                    if (in_array($current['StrippedResult'], $this->getAchievementLetterRegistry())) {
                        $finalStack['courses'][$courseName]['achieved'] += $current['ResultCredits'];
                        $finalStack['totals']['achieved'] += $current['ResultCredits'];
                    }

                    /* Attempted accumulator
                     * Only accumulate N/A/M/E values
                     */
                    if (in_array($current['StrippedResult'], $this->getLetterRegistry())) {
                        $finalStack['courses'][$courseName]['attempted'] += $current['ResultCredits'];
                        $finalStack['totals']['attempted'] += $current['ResultCredits'];
                    }
                }
            }
        }

        ksort($finalStack['courses']);

        return $finalStack;
    }

    /**
     * Determine if this result is progressive
     * (has square brackets around Result value)
     *
     * @param $result
     * @return bool
     */
    private function isProgressive($result) {
        return preg_match('/\[['.implode('', $this->getLetterRegistry()).']\]/', $result);
    }
}
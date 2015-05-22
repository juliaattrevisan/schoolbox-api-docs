<?php
/**
 * CreditSummary
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\NCEA;

class CreditSummary extends Base {

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'Credit Summary - All Years';

    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
    }

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $allResults = $this->findValues();

        $finalStack = array();
        $finalStack['table']['creditSummary'] = $this->findTable($allResults);
        $finalStack['graph']['totalAchievedCredits'] = $this->findTotalAchievedCredits($allResults);
        $finalStack['graph']['standardType'] = $this->findStandardType($allResults);
        $finalStack['graph']['assessmentType'] = $this->findAssessmentType($allResults);
        $finalStack['graph']['result'] = $this->findResult($allResults);

        return $finalStack;
    }

    /**
     * Find all the numeric data to be used throughout this section
     *
     * @return array|bool
     */
    private function findValues() {
        $query = "
            SELECT
                id,
                Level,
                InternalorExternalAssessment,
                EnteredCredits,
                StrippedResult,
                ResultCredits,
                StandardType,
                StudentYear
            FROM
                SynergyOne.dbo.uvSchoolBoxNCEAResults
            WHERE
                id = '{$this->getExternalUserId()}' AND
                WithdrawnFlag = 0 AND
                NOT (StudentYear = '{$this->getCurrentYear()}' AND InternalorExternalAssessment = 'EX')
        ";

        if ($this->getYear()) {
            $query .= "
                AND StudentYear = '{$this->getYear()}'
            ";
        }

        $result = $this->getDb()->getAll($query);

        if (is_array($result)) {
            return $result;
        }

        return array();
    }

    /**
     * Find values to populate the Credit Summary - All Years table
     * Rearrange everything into a nice order
     *
     * @param $allResults
     * @return array
     */
    private function findTable($allResults) {
        $finalStack = array();

        foreach ($allResults as $current) {
            $level = $current['Level'];
            $internalExternal = $current['InternalorExternalAssessment'];
            $letter = $current['StrippedResult'];

            if (!is_array($finalStack[$level])) {
                $finalStack[$level] = array(
                    'totalEnteredCredits' => 0,
                    'IN' => array(
                        'enteredCredits' => 0,
                        'letters' => array_fill_keys($this->getLetterRegistry(), 0),
                        'availableCredits' => 0
                    ),
                    'EX' => array(
                        'enteredCredits' => 0,
                        'letters' => array_fill_keys($this->getLetterRegistry(), 0),
                        'availableCredits' => 0
                    )
                );
            }

            $finalStack[$level][$internalExternal]['enteredCredits'] += $current['EnteredCredits'];
            $finalStack[$level][$internalExternal]['letters'][$letter] += $current['ResultCredits'];
            $finalStack[$level][$internalExternal]['availableCredits'] += ($current['EnteredCredits'] - $current['ResultCredits']);

            /* Total Entered Credits = Internally Assessed Entered Credits + Externally Assessed Entered Credits
             *                       = sum(EnteredCredits)
             */
            $finalStack[$level]['totalEnteredCredits'] += $current['EnteredCredits'];
        }

        krsort($finalStack);

        return $finalStack;
    }

    /**
     * Find values to populate Total Achieved Credits graph
     *
     * @param $allResults
     * @return array
     */
    private function findTotalAchievedCredits($allResults) {
        $finalStack = array();
        $exclusions = array('N');
        $totalEnteredCredits = array();

        /* Accumulate "Achieved" values
         */
        foreach ($allResults as $current) {
            $level = $current['Level'];

            if (!is_array($finalStack[$level])) {
                $finalStack[$level] = array(
                    'achieved' => 0,
                    'available' => 0,
                );

                $totalEnteredCredits[$level] = 0;
            }

            /* Sum of A, M, E values from both Internally Assessed
             * and Externally Assessed types
             */
            if (!in_array($current['StrippedResult'], $exclusions)) {
                $finalStack[$level]['achieved'] += $current['ResultCredits'];
            }

            $totalEnteredCredits[$level] += $current['EnteredCredits'];
        }

        /* Accumulate "Available" values
         */
        foreach ($finalStack as $level => $totals) {
            $finalStack[$level]['available'] = $totalEnteredCredits[$level] - $totals['achieved'];
        }

        krsort($finalStack);

        return $finalStack;
    }

    /**
     * Find values to populate Standard Type graph
     *
     * @param $allResults
     * @return array
     */
    private function findStandardType($allResults) {
        $finalStack = array();
        $unitTypeList = array('U');
        $unitResultList = array('A', 'M', 'E');
        $achievementTypeList = array('A');

        foreach ($allResults as $current) {
            $level = $current['Level'];

            if (!is_array($finalStack[$level])) {
                $finalStack[$level] = array(
                    'unit' => 0,
                    'achievement' => 0,
                );
            }

            /* Only accumulate some values
             */
            if (in_array($current['StrippedResult'], $unitResultList)) {
                if (in_array($current['StandardType'], $unitTypeList)) {
                    $finalStack[$level]['unit'] += $current['ResultCredits'];
                }
                elseif (in_array($current['StandardType'], $achievementTypeList)) {
                    $finalStack[$level]['achievement'] += $current['ResultCredits'];
                }
            }
        }

        krsort($finalStack);

        return $finalStack;
    }

    /**
     * Find values to populate Assessment Type graph
     *
     * @param $allResults
     * @return array
     */
    private function findAssessmentType($allResults) {
        $finalStack = array();
        $allLetters = array('A', 'M', 'E');

        foreach ($allResults as $current) {
            $level = $current['Level'];
            $internalExternal = $current['InternalorExternalAssessment'];

            if (!is_array($finalStack[$level])) {
                $finalStack[$level] = array(
                    'IN' => 0,
                    'EX' => 0,
                    'available' => 0,
                );
            }

            $finalStack[$level]['available'] += $current['ResultCredits'];

            /* Only accumulate some values
             */
            if (in_array($current['StrippedResult'], $allLetters)) {
                $finalStack[$level][$internalExternal] += $current['ResultCredits'];
                $finalStack[$level]['available'] -= $current['ResultCredits'];
            }
        }

        krsort($finalStack);

        return $finalStack;
    }

    /**
     * Find values to populate Result graph
     *
     * @param $allResults
     * @return array
     */
    private function findResult($allResults) {
        $finalStack = array();
        $allLetters = array('A', 'M', 'E');

        foreach ($allResults as $current) {
            $level = $current['Level'];

            if (!is_array($finalStack[$level])) {
                $finalStack[$level] = array_fill_keys($allLetters, 0);
            }

            $finalStack[$level]['available'] += $current['ResultCredits'];

            /* Only accumulate some values
             */
            if (in_array($current['StrippedResult'], $allLetters)) {
                $finalStack[$level][$current['StrippedResult']] += $current['ResultCredits'];
                $finalStack[$level]['available'] -= $current['ResultCredits'];
            }
        }

        krsort($finalStack);
        
        return $finalStack;
    }
}
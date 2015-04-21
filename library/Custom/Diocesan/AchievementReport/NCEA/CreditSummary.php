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

use Alaress_Probe as Probe;

class CreditSummary extends Base {

    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
        $this->setYear(date('Y'));
        $this->setHeading('Credit Summary - All Years');
    }

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $allResults = $this->findValues();

        $finalStack['table']['creditSummary'] = $this->findTable($allResults);
        $finalStack['graph']['totalAchievedCredits'] = '';

        return $finalStack;
    }

    /**
     * Find all the numeric data to be used throughout this section
     *
     * @return array|bool
     */
    private function findValues() {
        $externalUser = $this->getExternalUser();
        $externalUserId = $externalUser['StudentID'];

        $allResults = $this->getDb()->getAll("
            SELECT
                id,
                Level,
                InternalorExternalAssessment,
                EnteredCredits,
                StrippedResult,
                ResultCredits
            FROM
                SynergyOne.dbo.uvSchoolBoxNCEAResults
            WHERE
                id = '{$externalUserId}' AND
                WithdrawnFlag = 0 AND
                NOT (StudentYear = '{$this->getYear()}' AND InternalorExternalAssessment = 'EX')
        ");

        return $allResults;
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

        return $finalStack;
    }

    /**
     * Find values to populate Total Achieved Credits graph
     *
     * @param $allResults
     */
    private function findTotalAchievedCredits($allResults) {
        $finalStack = array();

        foreach ($allResults as $current) {

        }
    }
}
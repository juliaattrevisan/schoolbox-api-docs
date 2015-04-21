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

        /**
         * Rearrange everything into a nice order
         */
        $finalStack = array();

        foreach ($allResults as $current) {
            $level = $current['Level'];
            $internalExternal = $current['InternalorExternalAssessment'];
            $letter = $current['StrippedResult'];

            if (!is_array($finalStack[$level])) {
                $finalStack[$level] = $this->initialiseLevel();
            }

            $finalStack[$level][$internalExternal]['enteredCredits'] += $current['EnteredCredits'];
            $finalStack[$level][$internalExternal]['letters'][$letter] += $current['ResultCredits'];
            $finalStack[$level][$internalExternal]['availableCredits'] += ($current['EnteredCredits'] - $current['ResultCredits']);
        }

//        new Probe($this->explorerReporting(), $allResults, $finalStack);

        return $finalStack;
    }

    /**
     * Set up a new level
     *
     * @return array
     */
    private function initialiseLevel() {
        return array(
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
}
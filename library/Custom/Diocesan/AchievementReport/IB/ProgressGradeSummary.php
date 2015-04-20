<?php
/**
 * ProgressGradeSummary
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\IB;

use Alaress_Probe as Probe;

class ProgressGradeSummary extends Base {

    /**
     * Heading for this table
     *
     * @var string
     */
    protected $heading = 'Grade Summary';

    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
        $this->setYear(date('Y'));
        $this->setHeading('Progress Grade Summary');
    }

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $allProgressGrades = $this->findAllProgressGrades();

        /**
         * Rearrange everything into a nice order
         */
        $tableStack = array();

        /* Only accumulate non-empty progressgrade values
         */
        foreach ($allProgressGrades as $current) {
            if (strlen($current['ProgressGrade'])) {
                $year = $current['StudentYearlevel'];
                $term = $current['Term'];
                $progressGrade = $current['ProgressGrade'];

                /* Initialise
                 */
                if (!is_array($tableStack[$year])) {
                    $tableStack[$year] = array(
                        '4' => array(
                            'enteredGrades' => 0,
                            'progressGrades' => array_fill_keys(array('1', '2', '3', '4', '5', '6', '7'), 0)
                        ),
                        '2' => array(
                            'enteredGrades' => 0,
                            'progressGrades' => array_fill_keys(array('1', '2', '3', '4', '5', '6', '7'), 0),
                        ),
                    );
                }

                /* Accumulate
                 */
                $tableStack[$year][$term]['enteredGrades']++;
                $tableStack[$year][$term]['progressGrades'][$progressGrade]++;
            }
        }

        /* Rearrange for charting
         */
        $graphStack = array();

        foreach ($tableStack as $year => $allTerms) {
            foreach ($allTerms as $currentTerm => $values) {
                foreach ($values['progressGrades'] as $progressGrade => $num) {
                    $denominator = array_sum($values['progressGrades']);

                    if ($denominator) {
                        $graphStack[$progressGrade][$year][$currentTerm] = 100*($num/$denominator);
                    }
                    else {
                        $graphStack[$progressGrade][$year][$currentTerm] = 0;
                    }
                }
            }
        }

        $finalStack['tableStack'] = $tableStack;
        $finalStack['graphStack'] = $graphStack;

        return $finalStack;
    }

    /**
     * Find all progress grades
     *
     * @return array
     */
    private function findAllProgressGrades() {
        $externalUser = $this->getExternalUser();
        $externalUserId = $externalUser['StudentID'];

        $query = "
            SELECT *
            FROM SynergyOne.dbo.uvSchoolBoxIBResults
            WHERE ID = '{$externalUserId}'
            ORDER BY classyear DESC, Term DESC
        ";

        return $this->getDb()->getAll($query);
    }
}
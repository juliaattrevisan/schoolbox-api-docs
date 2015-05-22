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

class ProgressGradeSummary extends Base {

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'Progress Grade Summary';

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
        $allProgressGrades = $this->findAllProgressGrades();

        /**
         * Rearrange everything into a nice order
         */
        $tableStack = array();

        /* A quick accumulation of year/term combinations which
         * contained a ProgressGrade value. This is to hide years
         * which contained nothing without having to do serious
         * twig iterations, which became way more complicated than
         * they should have been
         */
        $existenceStack = array();

        foreach ($allProgressGrades as $current) {
            $year = $current['StudentYearlevel'];
            $term = $current['Term'];
            $progressGrade = $current['ProgressGrade'];

            /* Current Year always shows both Terms
             */
            if ($current['Fileyear'] == $this->getCurrentYear()) {
                $existenceStack[$year][4] = true;
                $existenceStack[$year][2] = true;
            }

            /* Only accumulate non-empty progressgrade values
             */
            if (strlen($progressGrade)) {

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

                /* Remember that this year/term had values
                 */
                $existenceStack[$year][$term] = true;
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
        $finalStack['existenceStack'] = $existenceStack;

        return $finalStack;
    }

    /**
     * Find all progress grades
     *
     * @return array
     */
    private function findAllProgressGrades() {
        $query = "
            SELECT *
            FROM SynergyOne.dbo.uvSchoolBoxIBResults
            WHERE ID = '{$this->getExternalUserId()}'
            ORDER BY classyear DESC, Term DESC
        ";

        $result = $this->getDb()->getAll($query);

        if (is_array($result)) {
            return $result;
        }

        return array();
    }
}
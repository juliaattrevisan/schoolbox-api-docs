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
        $this->explorerReporting();

        $allProgressGrades = $this->findAllProgressGrades();

        /**
         * Rearrange everything into a nice order
         */
        $finalStack = array();

        /* Only accumulate non-empty progressgrade values
         */
        foreach ($allProgressGrades as $current) {
            if (strlen($current['ProgressGrade'])) {
                $year = $current['classyear'];
                $term = $current['Term'];
                $progressGrade = $current['ProgressGrade'];

                /* Initialise
                 */
                if (!is_array($finalStack[$year])) {
                    $finalStack[$year] = array(
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
                $finalStack[$year][$term]['enteredGrades']++;
                $finalStack[$year][$term]['progressGrades'][$progressGrade]++;
            }
        }

//        new Probe($this->explorerReporting(), $allProgressGrades, $finalStack);

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

    /**
     * Explorer, reporting!
     */
    private function explorerReporting() {
        $explorer = $this->getDb()->getAll("
            SELECT TOP 50 *
            FROM SynergyOne.dbo.uvSchoolBoxIBResults
        ");

        return $explorer;
    }
}
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
                    'endorsement' => '',
                );
            }

            /* Entered Credits accumulator
             */
            $finalStack['courses'][$courseName]['enteredCredits'] += $current['EnteredCredits'];
            $finalStack['totals']['enteredCredits'] += $current['EnteredCredits'];

            /* Only accumulate non-progressive results
             *
             * Only accumulate data in certain conditions based on
             * Internal/External and StudentYear
             */
            if (!$this->isProgressive($current['Result'])) {
                if ($this->includeData($internalExternal, $current['StudentYear'])) {

                    /* Only accumulate A/M/E values
                     */
                    if (in_array($current['StrippedResult'], $this->getAchievementLetterRegistry())) {
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
                    elseif (in_array($letter, array('N', 'V', 'Y', 'Z'))) {
                        $finalStack['courses'][$courseName]['letters'][$letter] += $current['EnteredCredits'];
                        $finalStack['courses'][$courseName]['attempted'] += $current['EnteredCredits'];

                        $finalStack['totals']['attempted'] += $current['EnteredCredits'];
                        $finalStack['totals']['letters'][$letter] += $current['EnteredCredits'];
                    }
                }
            }
        }

        ksort($finalStack['courses']);

        /* Append endorsements
         */
        $allEndorsements = $this->findEndorsements();

        foreach ($allEndorsements as $currentEndorsement) {
            $courseName = $currentEndorsement['CourseDescription'];
            $endorsement = $currentEndorsement['Endorsement'];

            $finalStack['courses'][$courseName]['endorsement'] = $endorsement;
        }

        return $finalStack;
    }

    /**
     * Get subject endorsement results
     *
     * @return array
     */
    private function findEndorsements() {

//        /* Temporary test result, since the db table doesn't exist yet
//         */
//        return array(
//            array(
//                'CourseDescription' => 'NCEA Level 1 Business Enterprise',
//                'Endorsement' => 'Alaress Test Value: Merit 1'
//            ),
//            array(
//                'CourseDescription' => 'NCEA Level 1 Mathematics',
//                'Endorsement' => 'Alaress Test Value: Merit 3'
//            ),
//        );

        new \Alaress_Probe($this->explorerReporting());
        $query = "
            SELECT
                CourseDescription,
                Endorsement
            FROM
                SynergyOne.dbo.uv schoolboxncearesultswithendorsements
            WHERE
                id = '{$this->getExternalUserId()}' AND
                StudentYear = '{$this->getYear()}'
            GROUP BY CourseDescription
        ";

        $result = $this->getDb()->getAll($query);

        if (is_array($result)) {
            return $result;
        }

        return array();
    }
}
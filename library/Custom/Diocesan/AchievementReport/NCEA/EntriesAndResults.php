<?php
/**
 * EntriesAndResults
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\NCEA;

use Alaress_Probe as Probe;

class EntriesAndResults extends Base {

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'Entries And Results';

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
        $finalStack = array();

        $allResults = $this->findEntries();

        foreach ($allResults as $current) {
            $courseName = $current['CourseDescription'];
            $internalExternal = $current['InternalorExternalAssessment'];
            $letter = $current['StrippedResult'];

            /* Teachers
             * Trim empty teachers out for course teachers
             */
            $finalStack[$courseName]['allTeachers'][] = $current['ClassTeacher1'];
            $finalStack[$courseName]['allTeachers'][] = $current['ClassTeacher2'];
            $finalStack[$courseName]['allTeachers'][] = $current['ClassTeacher3'];
            $finalStack[$courseName]['allTeachers'] = array_values(array_unique(array_filter($finalStack[$courseName]['allTeachers'])));

            /* Entries
             */
            $finalStack[$courseName]['allEntries'][] = array(
                'NCEAStandardNumber' => $current['NCEAStandardNumber'],
                'InternalorExternalAssessment' => $current['InternalorExternalAssessment'],
                'StandardTitle' => $current['StandardTitle'],
                'Level' => $current['Level'],
                'EnteredCredits' => $current['EnteredCredits'],
                'StrippedResult' => $current['StrippedResult'],
                'ResultBrackets' => $current['ResultBrackets']
            );

            /* Letter summary stuff
             */
            if (!is_array($finalStack[$courseName]['summary'])) {
                $finalStack[$courseName]['summary'] = array(
                    'level' => '',
                    'totalEnteredCredits' => 0,
                    'type' => array(
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
                    )
                );
            }

            $finalStack[$courseName]['summary']['level'] = $current['Level'];
            $finalStack[$courseName]['summary']['totalEnteredCredits'] += $current['EnteredCredits'];
            $finalStack[$courseName]['summary']['type'][$internalExternal]['enteredCredits'] += $current['EnteredCredits'];
            $finalStack[$courseName]['summary']['type'][$internalExternal]['letters'][$letter] += $current['ResultCredits'];
            $finalStack[$courseName]['summary']['type'][$internalExternal]['availableCredits'] += ($current['EnteredCredits'] - $current['ResultCredits']);
        }

        return $finalStack;
    }

    /**
     * Find data about Entries and Results
     *
     * @return array
     */
    private function findEntries() {
        $externalUser = $this->getExternalUser();
        $externalUserId = $externalUser['StudentID'];

        $query = "
            SELECT
                CourseDescription,
                ClassTeacher1,
                ClassTeacher2,
                ClassTeacher3,
                NCEAStandardNumber,
                InternalorExternalAssessment,
                StandardTitle,
                Level,
                EnteredCredits,
                StrippedResult,
                ResultCredits,
                CAST(CASE WHEN StudentYear = '{$this->getCurrentYear()}' AND InternalorExternalAssessment = 'EX' THEN 1 ELSE 0 END AS bit) AS ResultBrackets
            FROM
                SynergyOne.dbo.uvSchoolBoxNCEAResults
            WHERE
                id = '{$externalUserId}' AND
                WithdrawnFlag = 0
                AND StudentYear = '{$this->getYear()}'
        ";

        $result = $this->getDb()->getAll($query);

        if (is_array($result)) {
            return $result;
        }

        return array();
    }
}
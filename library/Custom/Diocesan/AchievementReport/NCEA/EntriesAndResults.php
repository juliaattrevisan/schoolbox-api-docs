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
        $totalEnteredCredits = array();

        $allResults = $this->findEntries();

        foreach ($allResults as $current) {
            $courseName = $current['CourseDescription'];
            $internalExternal = $current['InternalorExternalAssessment'];
            $letter = $current['StrippedResult'];
            $level = $current['Level'];
            $studentYear = $current['StudentYear'];

            /* Teachers
             * Trim empty teachers out for course teachers
             */
            foreach(array(1, 2, 3) as $currentTeacherIndex) {
                $key = "ClassTeacher{$currentTeacherIndex}";
                $emailKey = "ClassTeacher{$currentTeacherIndex}Email";

                $currentTeacher = $current[$key];
                $currentTeacherEmail = $current[$emailKey];

                if (strlen($currentTeacher)) {
                    $finalStack[$courseName]['allTeachers'][$currentTeacher] = $currentTeacherEmail;
                }
            }

            /* Externally-assessed items in the CurrentYear are always enclosed
             * with square brackets. This indicates that it is a progressive grade,
             * used specifically for Dio
             */
            $result = $current['Result'];

            if (strlen($result) && ($internalExternal == 'EX') && ($studentYear == $this->getCurrentYear())) {
                $result = "[{$result}]";
            }

            /* Entries
             */
            $finalStack[$courseName]['allEntries'][] = array(
                'NCEAStandardNumber' => $current['NCEAStandardNumber'],
                'InternalorExternalAssessment' => $current['InternalorExternalAssessment'],
                'StandardTitle' => $current['StandardTitle'],
                'Level' => $current['Level'],
                'EnteredCredits' => $current['EnteredCredits'],
                'Result' => $result,
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

                $totalEnteredCredits[$level] = array(
                    'IN' => 0,
                    'EX' => 0
                );
            }

            $totalEnteredCredits[$level][$internalExternal] += $current['EnteredCredits'];

            $finalStack[$courseName]['summary']['level'] = $current['Level'];
            $finalStack[$courseName]['summary']['totalEnteredCredits'] += $current['EnteredCredits'];
            $finalStack[$courseName]['summary']['type'][$internalExternal]['enteredCredits'] += $current['EnteredCredits'];

            /* Special treatment for the little table corresponding
             * to each subject
             */
            if ($studentYear == $this->getCurrentYear()) {
                if (!$this->isProgressive($current['Result'])) {

                    /* Exclude N/A/M/E values for current year/external items
                     */
                    if ($internalExternal != 'EX') {
                        if (in_array($letter, array('N'))) {
                            $finalStack[$courseName]['summary']['type'][$internalExternal]['letters'][$letter] += $current['EnteredCredits'];
                        }
                        else {
                            $finalStack[$courseName]['summary']['type'][$internalExternal]['letters'][$letter] += $current['ResultCredits'];
                            $finalStack[$courseName]['summary']['type'][$internalExternal]['availableCredits'] += $current['EnteredCredits'] - $current['ResultCredits'];
                        }
                    }
                }
            }
            else {
                if (in_array($letter, array('N'))) {
                    $finalStack[$courseName]['summary']['type'][$internalExternal]['letters'][$letter] += $current['EnteredCredits'];
                }
                else {
                    $finalStack[$courseName]['summary']['type'][$internalExternal]['letters'][$letter] += $current['ResultCredits'];
                    $finalStack[$courseName]['summary']['type'][$internalExternal]['availableCredits'] += $current['EnteredCredits'] - $current['ResultCredits'];
                }
            }
        }

        ksort($finalStack);

        return $finalStack;
    }

    /**
     * Find data about Entries and Results
     *
     * @return array
     */
    private function findEntries() {
        $query = "
            SELECT
                CourseDescription,
                ClassTeacher1,
                ClassTeacher1Email,
                ClassTeacher2,
                ClassTeacher2Email,
                ClassTeacher3,
                ClassTeacher3Email
                NCEAStandardNumber,
                InternalorExternalAssessment,
                StandardTitle,
                Level,
                EnteredCredits,
                Result,
                StrippedResult,
                ResultCredits,
                StudentYear
            FROM
                SynergyOne.dbo.uvSchoolBoxNCEAResults
            WHERE
                id = '{$this->getExternalUserId()}' AND
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
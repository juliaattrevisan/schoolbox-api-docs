<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Dsfg\AchievementReport\NCEA;

class EntriesAndResults extends Base
{
    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'Entries And Results';


    /**
     * Returns data for twig rendering
     *
     * @return array
     */
    public function findData(): array
    {
        $finalStack = [];
        $allResults = $this->findEntries();

        foreach ($allResults as $current) {
            $courseName = $current['CourseDescription'];
            $internalExternal = $current['InternalorExternalAssessment'];
            $letter = $current['StrippedResult'];
            $studentYear = $current['StudentYear'];
            $result = $current['Result'];
            $strippedResult = $current['StrippedResult'];

            // Note $internalExternal is expected to always be 'IN' or 'EX'.
            // Some data does not meet this requirement, so if it ain't 'EX', assume it's 'IN'.
            if ($internalExternal !== 'EX') {
                $internalExternal = 'IN';
            }

            /* Teachers
             * Trim empty teachers out for course teachers
             */
            if (!isset($finalStack[$courseName]['allTeachers'])) {
                $finalStack[$courseName]['allTeachers'] = [];
            }
            foreach ([1, 2, 3] as $currentTeacherIndex) {
                $key = "ClassTeacher{$currentTeacherIndex}";
                $emailKey = "ClassTeacher{$currentTeacherIndex}Email";

                $currentTeacher = $current[$key];
                $currentTeacherEmail = $current[$emailKey];

                if (strlen($currentTeacher)) {
                    $finalStack[$courseName]['allTeachers'][$currentTeacher] = $currentTeacherEmail;
                }
            }

            /* Exclude certain items from the Entries And Results tables
             * Externally-assessed items in the CurrentYear are always enclosed
             * with square brackets. This indicates that it is a progressive grade,
             * used specifically for Dio
             */
            if (!in_array($result, $this->exclusionLetterRegistry, false)) {
                if (strlen($result) && ($internalExternal === 'EX') && ($studentYear == $this->getCurrentYear())) {
                    $result = "[{$strippedResult}]";
                }
            } else {
                $result = '';
                $strippedResult = '';
            }

            /* Entries
             */
            $finalStack[$courseName]['allEntries'][] = [
                'NCEAStandardNumber' => $current['NCEAStandardNumber'],
                'InternalorExternalAssessment' => $current['InternalorExternalAssessment'],
                'StandardTitle' => $current['StandardTitle'],
                'Level' => $current['Level'],
                'Lit' => $current['Lit'],
                'Num' => $current['Num'],
                'EnteredCredits' => $current['EnteredCredits'],
                'Result' => $result,
                'StrippedResult' => $strippedResult,
            ];

            /* Letter summary stuff
             */
            if (!isset($finalStack[$courseName]['summary'])) {
                $finalStack[$courseName]['summary'] = [
                    'level' => '',
                    'totalEnteredCredits' => 0,
                    'type' => [
                        'IN' => [
                            'enteredCredits' => 0,
                            'letters' => array_fill_keys($this->getLetterRegistry(), 0),
                            'availableCredits' => 0
                        ],
                        'EX' => [
                            'enteredCredits' => 0,
                            'letters' => array_fill_keys($this->getLetterRegistry(), 0),
                            'availableCredits' => 0
                        ]
                    ]
                ];
            }

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
                    if ($internalExternal !== 'EX') {
                        if ($letter === 'N') {
                            $finalStack[$courseName]['summary']['type'][$internalExternal]['letters'][$letter] += $current['EnteredCredits'];
                        } elseif (in_array($letter, $this->achievementLetterRegistry, false)) {
                            $finalStack[$courseName]['summary']['type'][$internalExternal]['letters'][$letter] += $current['ResultCredits'];
                            $finalStack[$courseName]['summary']['type'][$internalExternal]['availableCredits'] += $current['EnteredCredits'] - $current['ResultCredits'];
                        }
                    }
                }
            } else {
                if ($letter === 'N') {
                    $finalStack[$courseName]['summary']['type'][$internalExternal]['letters'][$letter] += $current['EnteredCredits'];
                } elseif (in_array($letter, $this->achievementLetterRegistry, false)) {
                    $finalStack[$courseName]['summary']['type'][$internalExternal]['letters'][$letter] += $current['ResultCredits'];
                    $finalStack[$courseName]['summary']['type'][$internalExternal]['availableCredits'] += $current['EnteredCredits'] - $current['ResultCredits'];
                }
            }
        }

        /* Accumulate "Available" values
         */
        foreach ($finalStack as $level => $data) {
            foreach ($data['summary']['type'] as $type => $values) {
                $finalStack[$level]['summary']['type'][$type]['availableCredits'] = $values['enteredCredits'] - array_sum($values['letters']);
            }
        }

        return $finalStack;
    }


    /**
     * Find data about Entries and Results
     *
     * @return array
     */
    private function findEntries(): array
    {
        $dbTableName = (
            ($this->getDbTableName() === self::MAIN_DB_TABLE_NCEA)
            ? 'dbo.uvSchoolBoxNCEAResultsV2'
            : $this->getDbTableName()
        );

        $columnForSorting = (
            ($dbTableName === self::MAIN_DB_TABLE_YEAR11)
            ? 'CourseDescriptionForSorting'
            : 'CourseDescription'
        );

        $params = [
            'externalId' => $this->getExternalUserId(),
            'year' => $this->year
        ];

        $query = '
            SELECT
                CourseDescription,
                ' . $columnForSorting . ' AS CourseDescriptionForSorting,
                ClassTeacher1,
                ClassTeacher1Email,
                ClassTeacher2,
                ClassTeacher2Email,
                ClassTeacher3,
                ClassTeacher3Email,
                NCEAStandardNumber,
                InternalorExternalAssessment,
                StandardTitle,
                Level,
                Lit,
                Num,
                EnteredCredits,
                Result,
                StrippedResult,
                ResultCredits,
                StudentYear
            FROM
                ' . $dbTableName . '
            WHERE
                id = :externalId AND
                WithdrawnFlag = 0
                AND StudentYear = :year
            ORDER BY
                ' . $columnForSorting . ' ASC
        ';

        return $this->db->executeQuery($query, $params)->fetchAll();
    }
}

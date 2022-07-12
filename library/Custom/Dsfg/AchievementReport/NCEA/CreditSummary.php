<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Dsfg\AchievementReport\NCEA;

class CreditSummary extends Base
{

    /**
     * Heading
     *
     * @var string
     */
    protected $heading = 'Credit Summary - All Years';


    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $allResults = $this->findValues();

        return [
            'table' => [
                'creditSummary' => $this->findTable($allResults)
            ],
            'graph' => [
                'totalAchievedCredits' => $this->findTotalAchievedCredits($allResults),
                'standardType' => $this->findStandardType($allResults),
                'assessmentType' => $this->findAssessmentType($allResults),
                'result' => $this->findResult($allResults)
            ]
        ];
    }


    /**
     * Find all the numeric data to be used throughout this section
     *
     * @return array
     */
    private function findValues(): array
    {
        $params = [
            'externalId' => $this->getExternalUserId(),
        ];

        $query = '
            SELECT
                id,
                Level,
                InternalorExternalAssessment,
                EnteredCredits,
                Result,
                StrippedResult,
                ResultCredits,
                StandardType,
                StudentYear
            FROM
                dbo.uvSchoolBoxNCEAResults
            WHERE
                id = :externalId AND
                WithdrawnFlag = 0
        ';

        if ($this->year) {
            $params['year'] = $this->year;

            $query .= '
                AND StudentYear = :year
            ';
        }

        return $this->db->executeQuery($query, $params)->fetchAll();
    }


    /**
     * Find values to populate the Credit Summary - All Years table
     * Rearrange everything into a nice order
     *
     * @param array $allResults
     * @return array
     */
    private function findTable($allResults): array
    {
        $finalStack = [];

        foreach ($allResults as $current) {
            $level = $current['Level'];
            $internalExternal = $current['InternalorExternalAssessment'];
            $letter = $current['StrippedResult'];

            if (!isset($finalStack[$level])) {
                $finalStack[$level] = [
                    'totalEnteredCredits' => 0,
                    'IN' => [
                        'enteredCredits' => 0,
                        'letters' => array_fill_keys($this->getLetterRegistry(), 0),
                        'availableCredits' => 0,
                    ],
                    'EX' => [
                        'enteredCredits' => 0,
                        'letters' => array_fill_keys($this->getLetterRegistry(), 0),
                        'availableCredits' => 0,
                    ]
                ];
            }

            /* Total Entered Credits = Internally Assessed Entered Credits + Externally Assessed Entered Credits
             *                       = sum(EnteredCredits)
             */
            $finalStack[$level]['totalEnteredCredits'] += $current['EnteredCredits'];

            /* Entered Credits for a particular level and internal/external assessed type
             */
            $finalStack[$level][$internalExternal]['enteredCredits'] += $current['EnteredCredits'];
            $finalStack[$level][$internalExternal]['availableCredits'] += $current['EnteredCredits'];

            /* Ignore progressive results for current year
             */
            if (!$this->isProgressive($current['Result'])) {
                if ($this->includeData($internalExternal, $current['StudentYear'])) {
                    /* Special case for N
                     *
                     * According to Sheeba,
                     * N = sum of enteredcredits where strippedresult=N  [when result is not achieved
                     * the resultscredit data is 0, hence use entered credits]
                     */
                    if ($letter === 'N') {
                        $finalStack[$level][$internalExternal]['letters'][$letter] += $current['EnteredCredits'];
                        $finalStack[$level][$internalExternal]['availableCredits'] -= $current['EnteredCredits'];
                    } elseif (in_array($letter, $this->achievementLetterRegistry, false)) {
                        // Regular summation for A, M, E
                        $finalStack[$level][$internalExternal]['letters'][$letter] += $current['ResultCredits'];
                        $finalStack[$level][$internalExternal]['availableCredits'] -= $current['ResultCredits'];
                    } elseif (in_array($letter, $this->exclusionLetterRegistry, false)) {
                        /* Other cases to exclude in Available Credits calculation
                         *
                         * According to Sheeba,
                         * Available = 1-2-(sum of enteredcredits where strippedresult=Y, V or Z)
                         * [when result of Y,V or Z is assigned due to absence or not attempting,
                         * it is no longer available for assessment]
                         */
                        $finalStack[$level][$internalExternal]['availableCredits'] -= $current['EnteredCredits'];
                    }
                }
            }
        }

        krsort($finalStack);

        return $finalStack;
    }


    /**
     * Find values to populate Total Achieved Credits graph
     *
     * @param array $allResults
     * @return array
     */
    private function findTotalAchievedCredits($allResults): array
    {
        $finalStack = [];
        $totalEnteredCredits = [];

        /* Accumulate "Achieved" values
         */
        foreach ($allResults as $current) {
            $level = $current['Level'];
            $internalExternal = $current['InternalorExternalAssessment'];

            if (!isset($finalStack[$level])) {
                $finalStack[$level] = [
                    'achieved' => 0,
                    'available' => 0,
                    'exclusions' => 0,
                ];

                $totalEnteredCredits[$level] = 0;
            }

            /* Ignore progressive results for current year
             */
            if (!$this->isProgressive($current['Result'])) {
                if ($this->includeData($internalExternal, $current['StudentYear'])) {
                    /* Sum of A, M, E values from both Internally Assessed
                     * and Externally Assessed types
                     */
                    if (in_array($current['StrippedResult'], $this->achievementLetterRegistry, false)) {
                        $finalStack[$level]['achieved'] += $current['ResultCredits'];
                    } elseif (in_array($current['StrippedResult'], $this->exclusionLetterRegistry, false) || in_array($current['StrippedResult'], array('N'))) {
                        // Special treatment
                        $finalStack[$level]['exclusions'] += $current['EnteredCredits'];
                    }
                }
            }

            $totalEnteredCredits[$level] += $current['EnteredCredits'];
        }

        /* Accumulate "Available" values
         */
        foreach ($finalStack as $level => $totals) {
            $finalStack[$level]['available'] = $totalEnteredCredits[$level] - $totals['achieved'] - $finalStack[$level]['exclusions'];
        }

        krsort($finalStack);

        return $finalStack;
    }


    /**
     * Find values to populate Standard Type graph
     *
     * @param array $allResults
     * @return array
     */
    private function findStandardType($allResults): array
    {
        $finalStack = [];
        $unitTypeList = ['U'];
        $achievementTypeList = ['A'];
        $totalEnteredCredits = [];

        foreach ($allResults as $current) {
            $level = $current['Level'];
            $internalExternal = $current['InternalorExternalAssessment'];

            if (!isset($finalStack[$level])) {
                $finalStack[$level] = [
                    'unit' => 0,
                    'achievement' => 0,
                    'available' => 0,
                    'exclusions' => 0,
                ];

                $totalEnteredCredits[$level] = 0;
            }

            /* Ignore progressive results for current year
             */
            if (!$this->isProgressive($current['Result'])) {
                if ($this->includeData($internalExternal, $current['StudentYear'])) {
                    /* Only accumulate some values
                     */
                    if (in_array($current['StrippedResult'], $this->achievementLetterRegistry, false)) {
                        if (in_array($current['StandardType'], $unitTypeList, false)) {
                            $finalStack[$level]['unit'] += $current['ResultCredits'];
                        } elseif (in_array($current['StandardType'], $achievementTypeList, false)) {
                            $finalStack[$level]['achievement'] += $current['ResultCredits'];
                        }
                    } elseif (in_array($current['StrippedResult'], $this->exclusionLetterRegistry, false) || $current['StrippedResult'] === 'N') {
                        // Special treatment
                        $finalStack[$level]['exclusions'] += $current['EnteredCredits'];
                    }
                }
            }

            $totalEnteredCredits[$level] += $current['EnteredCredits'];
        }

        /* Accumulate "Available" values
         */
        foreach ($finalStack as $level => $totals) {
            $finalStack[$level]['available'] = $totalEnteredCredits[$level] - $totals['unit'] - $totals['achievement'] - $finalStack[$level]['exclusions'];
        }

        krsort($finalStack);

        return $finalStack;
    }


    /**
     * Find values to populate Assessment Type graph
     *
     * @param array $allResults
     * @return array
     */
    private function findAssessmentType($allResults): array
    {
        $finalStack = [];
        $totalEnteredCredits = [];

        foreach ($allResults as $current) {
            $level = $current['Level'];
            $internalExternal = $current['InternalorExternalAssessment'];

            if (!isset($finalStack[$level])) {
                $finalStack[$level] = [
                    'IN' => 0,
                    'EX' => 0,
                    'available' => 0,
                    'exclusions' => 0,
                ];

                $totalEnteredCredits[$level] = 0;
            }

            /* Ignore progressive results for current year
             */
            if (!$this->isProgressive($current['Result'])) {
                if ($this->includeData($internalExternal, $current['StudentYear'])) {
                    /* Only accumulate some values
                     */
                    if (in_array($current['StrippedResult'], $this->achievementLetterRegistry, false)) {
                        $finalStack[$level][$internalExternal] += $current['ResultCredits'];
                    } elseif (in_array($current['StrippedResult'], $this->exclusionLetterRegistry, false) || in_array($current['StrippedResult'], array('N'))) {
                        // Special treatment
                        $finalStack[$level]['exclusions'] += $current['EnteredCredits'];
                    }
                }
            }

            $totalEnteredCredits[$level] += $current['EnteredCredits'];
        }

        /* Accumulate "Available" values
         */
        foreach ($finalStack as $level => $totals) {
            $finalStack[$level]['available'] = $totalEnteredCredits[$level] - $totals['IN'] - $totals['EX'] - $finalStack[$level]['exclusions'];
        }

        krsort($finalStack);

        return $finalStack;
    }


    /**
     * Find values to populate Result graph
     *
     * @param array $allResults
     * @return array
     */
    private function findResult($allResults): array
    {
        $finalStack = [];
        $totalEnteredCredits = [];

        foreach ($allResults as $current) {
            $level = $current['Level'];
            $internalExternal = $current['InternalorExternalAssessment'];

            if (!isset($finalStack[$level])) {
                $finalStack[$level] = array_fill_keys($this->achievementLetterRegistry, 0);

                $finalStack[$level]['available'] = 0;
                $finalStack[$level]['exclusions'] = 0;

                $totalEnteredCredits[$level] = 0;
            }

            $totalEnteredCredits[$level] += $current['EnteredCredits'];

            /* Ignore progressive results for current year
             */
            if (!$this->isProgressive($current['Result'])) {
                if ($this->includeData($internalExternal, $current['StudentYear'])) {
                    if (in_array($current['StrippedResult'], $this->achievementLetterRegistry, false)) {
                        /* Only accumulate some values
                         */
                        $finalStack[$level][$current['StrippedResult']] += $current['ResultCredits'];
                    } elseif (in_array($current['StrippedResult'], $this->exclusionLetterRegistry, false) || in_array($current['StrippedResult'], array('N'))) {
                        /* Special treatment
                         */
                        $finalStack[$level]['exclusions'] += $current['EnteredCredits'];
                    }
                }
            }
        }

        /* Accumulate "Available" values
         */
        foreach ($finalStack as $level => $totals) {
            $finalStack[$level]['available'] = $totalEnteredCredits[$level] - $totals['A'] - $totals['M'] - $totals['E'] - $finalStack[$level]['exclusions'];
        }

        krsort($finalStack);

        return $finalStack;
    }
}

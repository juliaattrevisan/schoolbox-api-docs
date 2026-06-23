<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Markbook;

use Schoolbox\User;

class LongitudinalDataset
{
    /**
     * Constructor
     *
     * @param bool $includeComparison If true, accumulate five-point summaries that fuel the boxplot graphs in the Progression Panel.
     */
    public function __construct(private bool $includeComparison)
    {
    }


    /**
     * From the set of rubric marks data $studentMarks, find a particular $student's result set and rotate the hash
     * lookup data struct: this is because assessments render horizontally in longitudinally-oriented areas where we
     * look at one student's accomplishments:
     *     - capability marks panel
     *     - learning levels panel
     *     - progression panel
     *     - etc.
     *
     * @param User $student
     * @param array $studentMarks
     * @return array
     */
    public function generate(\Schoolbox\User $student, array $studentMarks): array
    {
        /* We only need data for this one particular student
         */
        $data = $studentMarks[$student->getId()] ?? null;

        /* Student doesn't have any data; exit earlyish
         */
        if (null === $data) {
            return [];
        }

        /* Transpose this rubric markbook data
         */
        $stack = [];
        $summaryIterator = null;

        foreach ($data['gridData'] as $assessmentId => $rubricInstanceData) {
            foreach ($rubricInstanceData as $rubricInstanceId => $rubricGridData) {
                foreach ($rubricGridData['capabilities'] as $capabilityId => $capabilityData) {
                    if (!isset($stack[$capabilityId])) {
                        $stack[$capabilityId] = [
                            'name' => $capabilityData['name'],
                            /* We only care about published data here; these are results for a student indexed by
                             * their assessment id and grid id
                             */
                            'assessmentResults' => [],
                            'indicators' => [],
                        ];
                    }

                    /* Chuck in this student's result, indexed by assessment identifier
                     */
                    $stack[$capabilityId]['assessmentResults'][$assessmentId]['grid'][$rubricInstanceId]['published'] = $capabilityData['published'];

                    foreach ($capabilityData['indicators'] as $indicatorId => $indicatorData) {
                        if (!isset($stack[$capabilityId]['indicators'][$indicatorId])) {
                            $stack[$capabilityId]['indicators'][$indicatorId] = [
                                'name' => $indicatorData['name'],
                                /* We only care about published data here; these are results for a student indexed by
                                 * the assessment id and contains descriptor values
                                 */
                                'assessmentResults' => [],
                            ];
                        }

                        /* Chuck in this student's result, indexed by assessment identifier
                         */
                        $stack[$capabilityId]['indicators'][$indicatorId]['assessmentResults'][$assessmentId]['grid'][$rubricInstanceId]['published'] = $indicatorData['published'];

                        /* If $includeComparison is truthy, also accumulate a five-point-summary for the boxplots in
                         * the user interface. This requires you to collect everyone's value for an indicator.
                         * You'll have to iterate over everyone else's indicator value to chuck into the boxplot summary.
                         *
                         * Reuse this old "statistics" node, for familiarity I guess
                         */
                        if ($this->includeComparison) {
                            /* Only spawn this once
                             */
                            if (null === $summaryIterator) {
                                $summaryIterator = (new SummaryIterator($studentMarks));
                            }

                            /*  All the normalised marks for this $indicatorId go into a data set, which then gets
                             *  pumped into a new Summary() instance's constructor.
                             */
                            $stack[$capabilityId]['indicators'][$indicatorId]['assessmentResults'][$assessmentId]['grid'][$rubricInstanceId]['published']['statistics'] = $summaryIterator->accumulate($assessmentId, $rubricInstanceId, $capabilityId, $indicatorId);;
                        }
                    }
                }
            }
        }

        /* Sort capabilities alphabetically
         */
        uasort($stack, static function ($a, $b) {
            return $a['name'] <=> $b['name'];
        });

        /* Sort indicators alphabetically
         */
        foreach ($stack as $capabilityId => &$capability) {
            uasort($capability['indicators'], static function ($a, $b) {
                return $a['name'] <=> $b['name'];
            });
        }

        return $stack;
    }
}

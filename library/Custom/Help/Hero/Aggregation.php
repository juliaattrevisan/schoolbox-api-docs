<?php
/**
 * Aggregation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Help\Hero;

class Aggregation
{

    /**
     * Aggregate data to work out who qualified for Hero status
     *
     * @param array $data Data from Acquisition
     * @param int   $numRequired Number of assessments that must be 100% to get Hero status
     * @return array
     */
    public function aggregate(array $data, int $numRequired)
    {
        $aggregatedData = [];

        /* Build a hierarchy of data
         */
        /**
         * @var int $id
         * @var array $assessmentData
         */
        foreach ($data as $userId => $assessmentData) {
            foreach ($assessmentData as $currentAssessmentData) {
                if (!array_key_exists($userId, $aggregatedData)) {
                    $aggregatedData[$userId] = [
                        'firstname' => $currentAssessmentData['firstname'],
                        'lastname' => $currentAssessmentData['lastname'],
                        'fullmarks' => 0,
                        'qualified' => false,
                        'assessments' => []
                    ];
                }

                /* Figure out if this assessment spec stack already exists
                 *
                 * Reassignation shouldn't happen for Due Works; Acquisition gets unique Due Works with the highest
                 * mark already, so I didn't need to throw data away here
                 *
                 * However, with quizzes, for some reason subqueries didn't work so I have to find the one with the
                 * highest mark manually
                 */
                $key = $currentAssessmentData['specId'];

                $assessmentSpecNotStackedYet = !array_key_exists($key, $aggregatedData[$userId]['assessments']);
                $existingSpecHasLowerMark = ((float)$aggregatedData[$userId]['assessments'][$key]['mark'] < (float)$currentAssessmentData['mark']);

                /* Stack it if it doesn't exist
                 * Update the existing stack record if it already exists but mark was lower than current record
                 */
                if ($assessmentSpecNotStackedYet || $existingSpecHasLowerMark) {
                    $aggregatedData[$userId]['assessments'][$key] = [
                        'assessmentId' => $currentAssessmentData['assessmentId'],
                        'assessmentTitle' => $currentAssessmentData['assessmentTitle'],
                        'mark' => $currentAssessmentData['mark'],
                    ];
                }

                /* Add to the count of assessments that received 100%
                 */
                if ((float)$currentAssessmentData['mark'] === (float)100) {
                    $aggregatedData[$userId]['fullmarks']++;
                }
            }
        }

        /* Identify those who qualified
         */
        foreach ($aggregatedData as $userId => &$userData) {
            if ($userData['fullmarks'] === $numRequired) {
                $userData['qualified'] = true;
            }
        }

        return $aggregatedData;
    }
}
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
     * @param array $data
     * @return array
     */
    public function aggregate(array $data)
    {
        $aggregatedData = [];

        /* Build a hierarchy of data
         */
        /**
         * @var int $id
         * @var array $assessmentData
         */
        foreach ($data as $id => $assessmentData) {
            foreach ($assessmentData as $currentAssessmentData) {
                if (!array_key_exists($id, $aggregatedData)) {
                    $aggregatedData[$id] = [
                        'firstname' => $currentAssessmentData['firstname'],
                        'lastname' => $currentAssessmentData['lastname'],
                        'fullmarks' => 0,
                        'assessments' => []
                    ];
                }

                $key = $currentAssessmentData['assessmentId'];

                $aggregatedData[$id]['assessments'][$key] = [
                    'specId' => $currentAssessmentData['specId'],
                    'assessmentId' => $currentAssessmentData['assessmentId'],
                    'assessmentTitle' => $currentAssessmentData['assessmentTitle'],
                    'mark' => $currentAssessmentData['highestMark'],
                ];

                /* Add to the count of assessments that received 100%
                 */
                if ((float)$currentAssessmentData['highestMark'] === (float)100) {
                    $aggregatedData[$id]['fullmarks']++;
                }
            }
        }

        return $aggregatedData;
    }
}
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
     */
    public function aggregate(array $data)
    {
        $aggregatedData = [];

        /* Build a hierarchy of data
         */
        foreach ($data as $id => $currentData) {
            if (!array_key_exists($id, $aggregatedData)) {
                $aggregatedData[$id] = [
                    'firstname' => $currentData['firstname'],
                    'lastname' => $currentData['lastname'],
                    'assessments' => []
                ];
            }

            $key = $currentData['assessmentId'];

            $aggregatedData[$id]['assessments'][$key] = [
                'specId' => $currentData['specId'],
                'assessmentId' => $currentData['assessmentId'],
                'assessmentTitle' => $currentData['assessmentTitle'],
                'mark' => $currentData['highestMark'],
            ];

            new \Alaress_Probe($aggregatedData);
        }
    }
}
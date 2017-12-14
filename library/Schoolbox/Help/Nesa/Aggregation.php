<?php
/**
 * NESA Report Data Aggregation
 *
 * Combine all the results on a per-user basis across all their assessments' rubrics
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Help\Nesa;

class Aggregation
{
    /**
     * Report constructor.
     * @param array $dataSet an array of investigation results from Acquisition
     */
    public function __construct(array $dataSet) {
        $this->dataSet = $dataSet;
    }

    /**
     * Process all the $dataSet rows that were generated
     *
     * @return array
     */
    public function generate()
    {
        /* A condensed version of the output stack that discards the capability/indicator hierarchy
         * and provides you the descriptor outputs, dumping them into a hashtable
         */
        $condensedData = [];

        foreach ($this->dataSet as $current) {

            /* Store first stack of condensed descriptor data for this user into a hashtable
             * Otherwise merge into existing
             */
            if (!isset($condensedData[$current['user']->getId()])) {
                $condensedData[$current['user']->getId()] = [
                    'user' => [
                        'id' => $current['user']->getId(),
                        'firstname' => $current['user']->getFirstname(),
                        'lastname' => $current['user']->getLastname(),
                        'fullname' => $current['user']->getFullname()
                    ],
                    'condensed' => $current['condensed']
                ];
            } else {
                $existingIndicators = $condensedData[$current['user']->getId()]['condensed'];
                $additionalIndicators = $current['condensed'];

                $condensedData[$current['user']->getId()]['condensed'] = array_merge($existingIndicators, $additionalIndicators);
            }
        }

        return $condensedData;
    }
}
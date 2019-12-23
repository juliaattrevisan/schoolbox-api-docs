<?php
/**
 * NESA Report Data Aggregation
 *
 * Combine all the results on a per-user basis across all their assessments' rubrics
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Help\Nesa;

use Schoolbox\User;

class Aggregation
{
    /**
     * @var array
     */
    private $dataSet;

    /**
     * Aggregation constructor.
     *
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
    public function generate() :array
    {

        /* A condensed version of the output stack that discards the capability/indicator hierarchy
         * and provides you the descriptor outputs, dumping them into a hashtable
         */
        $condensedData = [];

        /* Combine it with the full roster of people who were found
         * TODO: Eventually this will go away, but I think this will be useful for debugging initially
         */
        $pdRoster = $this->dataSet['roster'];

        foreach ((array)$this->dataSet['stack'] as $current) {

            /* Store condensed descriptor data for this user into a hashtable for quick lookups when rendering
             * Otherwise merge into existing hashtable index
             */
            /** @var User $user */
            $user = $current['user'];

            if (!isset($condensedData[$user->getId()])) {
                $condensedData[$user->getId()] = [
                    'user' => [
                        'id' => $user->getId(),
                        'number' => $current['number'],
                        'firstname' => $user->getFirstname(),
                        'lastname' => $user->getLastname(),
                        'fullname' => $user->getFullname()
                    ],
                    'condensed' => $current['condensed']
                ];
            } else {
                $existingIndicators = $condensedData[$user->getId()]['condensed'];
                $additionalIndicators = $current['condensed'];

                /* Check the contents of $additionalIndicators to see if any "N" (Not Shown) outcomes exist there
                 * as well as in $existingIndicators; don't merge "N" outcomes if there's a "Y" in existence already
                 */
                foreach ((array)$current['condensed'] as $key => $data) {
                    if (array_key_exists($key, $existingIndicators) && $existingIndicators[$key]['outcome']) {
                        unset($additionalIndicators[$key]);
                    }
                }

                if (\count($additionalIndicators)) {
                    $condensedData[$user->getId()]['condensed'] = array_merge($existingIndicators, $additionalIndicators);
                }
            }

            $pdRoster[$user->getId()]['condensed'] = $condensedData[$user->getId()]['condensed'];
        }

        /* Return $condensedData for a list of people who have received rubrics only
         * Otherwise return $pdRoster for a list of everyone matching the Selection criteria
         */
        return $pdRoster;
    }
}

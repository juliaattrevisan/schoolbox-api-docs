<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\SingleFile\Turnitin\Process;

/**
 * From two arrays, calculate a diff while preserving as much as possible
 * so that you're only performing the minimal amount of mutations
 */
class Differential
{

    /**
     * Find all the $local things you need to add
     * Find all the $remote things you need to remove
     * Ignore the intersection between them $local and $remote sets
     *
     * Assumptions:
     *      - $localIds and $remoteIds are both array_unique'd (no dupes)
     *      - $localIds and $remoteIds do not contain null/rubbish data
     *      - $localIds and $remoteIds are strictly typematched; 1 !== '1'
     *
     * @param array $localIds
     * @param array $remoteIds
     * @return array
     */
    public function generate(array $localIds, array $remoteIds): array
    {
        $add = $keep = [];

        /* Find the ones to remove
         */
        $delete = array_values(array_diff($remoteIds, $localIds));

        /* Find the ones to add
         */
        foreach ($localIds as $localId) {
            if (in_array($localId, $remoteIds, true)) {
                /* This is already in the system; keep it (no change required, normally)
                 * You might be able to cheapen this further using array_keys instead, but meh
                 */
                $keep[] = $localId;
            } else {
                /* This is a new addition; add it
                 */
                $add[] = $localId;
            }
        }

        /* Give a summary of what you need to do in the $remote environment
         */
        return [
            'delete' => $delete,
            'keep' => $keep,
            'add' => $add
        ];
    }
}
<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Help\Superstar;

class Payload
{
    /**
     * Generate an array of data about everyone's candidacy potential
     *
     * @param \PDOStatement $statement
     * @return array
     */
    public function generate(\PDOStatement $statement): array
    {
        /* A list of tuples:
         *
         * $stack = [
         *     spec id => [
         *         unit spec title => ...
         *         candidates => [
         *             user id => [
         *                 firstname => ...
         *                 lastname => ...
         *                 candidacy => superstar, star, or null
         *             ],
         *             user id => [
         *                 firstname => ...
         *                 lastname => ...
         *                 candidacy => superstar, star, or null
         *             ],
         *             ...
         *         ],
         *     ],
         *     spec id => [
         *         unit spec title => ...
         *         candidates => [
         *             user id => [
         *                 firstname => ...
         *                 lastname => ...
         *                 candidacy => superstar, star, or null
         *             ],
         *             user id => [
         *                 firstname => ...
         *                 lastname => ...
         *                 candidacy => superstar, star, or null
         *             ],
         *             ...
         *         ],
         *         ...
         *     ]
         *     ...
         * ]
         */
        $stack = [];

        /* Start preprocessing each guy in the result set,
         * analysing each row's norm_mark to find out what award should be given
         * (if any) when we're done with each guy's unit.
         *
         * Collate those outcomes into a mega struct for frontend
         */
        $candidacyDecider = new Candidacy();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /* Initialise this unit spec's entry
             */
            if (!isset($stack[$row['unit_spec_id']])) {
                $stack[$row['unit_spec_id']] = [
                    'title' => $row['unit_spec_title'],
                    'candidates' => []
                ];
            }

            /* Initialise this guy's entry
             */
            if (!isset($stack[$row['unit_spec_id']]['candidates'][$row['user_id']])) {
                $stack[$row['unit_spec_id']]['candidates'][$row['user_id']] = [
                    'firstname' => $row['user_firstname'],
                    'lastname' => $row['user_lastname'],
                    'school' => [
                        'name' => $row['school_name'],
                        'code' => $row['school_code']
                    ],
                    'candidacy' => null,
                    'marks' => []
                ];
            }

            /* Accumulate this person's mark
             */
            if (null !== $row['norm_mark']) {
                $stack[$row['unit_spec_id']]['candidates'][$row['user_id']]['marks'][] = (float)$row['norm_mark'];
            }
        }

        /* Now decide everyone's outcome
         */
        foreach ($stack as $unitSpecId => &$unitSpec) {
            foreach ($unitSpec['candidates'] as $userId => &$userData) {
                $outcome = $candidacyDecider->decide($userData['marks']);

                /* Null outcome means this guy didn't qualify for anything
                 * just unset them entirely from the stack
                 */
                if (null === $outcome) {
                    unset($unitSpec['candidates'][$userId]);
                } else {
                    $userData['candidacy'] = $outcome;
                }
            }
        }

        return $stack;
    }
}

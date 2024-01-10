<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Help\Superstar;

use Alaress\Database\Connector\IConnector;

class Payload
{
    /**
     * We only care about the Unit Specs in this particular course
     * TODO Potentially made configurable in a ui somewhere
     */
//    private const COURSE_ID = 34;
    private const COURSE_ID = 27;


    /**
     * @param IConnector $database
     */
    public function __construct(private IConnector $database)
    {
    }


    /**
     * Generate an array of data about everyone's candidacy potential
     *
     * @param int $year
     * @return array
     * @throws \PDOException
     */
    public function generate(int $year): array
    {
        $statement = $this->generateCandidatesStatement();

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
                    /* These guys can be awarded a badge
                     */
                    'actionables' => [
                        Candidacy::SUPERSTAR => [],
                        Candidacy::STAR => []
                    ],
                    /* Raw data for each user in this unit
                     */
                    'candidates' => []
                ];
            }

            /* Initialise this guy's entry
             */
            if (!isset($stack[$row['unit_spec_id']]['candidates'][$row['user_id']])) {
                $stack[$row['unit_spec_id']]['candidates'][$row['user_id']] = [
                    'meta' => [
                        'id' => $row['user_id'],
                        'firstname' => $row['user_firstname'],
                        'lastname' => $row['user_lastname'],
                        'school' => [
                            'folderId' => $row['folder_id'],
                            'name' => $row['school_name'],
                            'code' => $row['school_code']
                        ],
                    ],
                    'marks' => []
                ];
            }

            /* Accumulate this person's mark
             */
            if (null !== $row['norm_mark']) {
                $stack[$row['unit_spec_id']]['candidates'][$row['user_id']]['marks'][] = (float)$row['norm_mark'];
            }
        }

        /* TODO Special Case
         *  There's a random assessment outside of projects so its result won't appear in
         *  the megaquery above; the results aren't in project_result table. They're elsewhere.
         *  For OLC 2023 we specifically have to use the swamp or the assess_attempt table
         *  directly, to obtain these marks for this particular quiz. This precipitates a
         *  querystring param, ?year=2023, to decide when to look for this rando quiz.
         *  We can augment $stack here, then subject it to the usual decisionmaking
         */
        if (2023 === $year) {
            /* Find this random quiz, based on its spec, and use the mapper proxy to
             * find all the authoritative feedbacks, get their norm_marks, then shank
             * those straight into $stack
             */
        }

        /* Now decide everyone's outcome
         */
        foreach ($stack as $unitSpecId => &$unitSpec) {
            foreach ($unitSpec['candidates'] as $userId => $userData) {
                $outcome = $candidacyDecider->decide($userData['marks']);

                /* Only remember people who achieved Superstardom or Stardom
                 * Everyone else doesn't matter
                 */
                if (null !== $outcome) {
                    /* TODO Cross reference if they already received this badge previously
                     *  We don't need to give them the same badge twice
                     */
                    $stack[$unitSpecId]['actionables'][$outcome][] = $userData['meta'];
                }
            }

            /* We don't need their raw data anymore
             */
            unset($unitSpec['candidates']);
        }

        return $stack;
    }


    /**
     * Prepare a statement for award candidates
     *
     * @return \PDOStatement
     * @throws \PDOException
     */
    private function generateCandidatesStatement(): \PDOStatement
    {
        /* TODO Potentially insert a course_unit_specification.id param here,
         *  to only obtain candidacy for a particular unit - this would be a
         *  performance workaround that avoids generating the entire data set
         *  at the very beginning...but it means you gotta click a lot
         */

        /* TODO deal with soft-deleted users and other soft-deleted data here
         */
        $query = "
            SELECT 
                cui.course_unit_specification_id AS unit_spec_id,
                f0.`name` AS unit_spec_title,
                u.id AS user_id,
                u.firstname AS user_firstname,
                u.lastname AS user_lastname,
                f2.id AS folder_id,
                f2.`name` AS school_name,
                fc.`code` AS school_code,
                prc.id AS project_id,
                prc.norm_mark
            FROM course_implementation ci
            INNER JOIN course_unit_implementation cui on ci.id = cui.course_implementation_id
            INNER JOIN course_unit_specification cus ON cui.course_unit_specification_id = cus.id
            INNER JOIN folder f0 ON cus.folder_id = f0.id
            INNER JOIN assessment_course_unit_implementation acui ON cui.id = acui.course_unit_implementation_id
            INNER JOIN project p ON acui.assessment_id = p.assessment_id
            INNER JOIN project_result prc ON p.id = prc.project_id
            INNER JOIN `user` u ON prc.user_id = u.id
            INNER JOIN mv_assessment_relation mvar ON p.assessment_id = mvar.assessment_id
            INNER JOIN folder f1 ON mvar.folder_id = f1.id
            INNER JOIN folder f2 ON f1.parent = f2.id
            LEFT JOIN folder_code fc ON f2.id = fc.folder_id
            WHERE ci.course_specification_id = :courseId
            AND prc.norm_mark IS NOT NULL
            ORDER BY unit_spec_id, unit_spec_title, user_lastname, user_id
        ";

        $statement = $this->database->prepare($query);

        $statement->execute([
            'courseId' => self::COURSE_ID
        ]);

        return $statement;
    }
}

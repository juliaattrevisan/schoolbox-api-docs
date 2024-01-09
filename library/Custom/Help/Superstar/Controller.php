<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Help\Superstar;

use Alaress\Web\Controller\Base;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * The name of this report which appears when you bookmark it
     * in yer browser
     */
    private const REPORT_TITLE = 'OLC Superstar Report';


    /**
     * We only care about the Unit Specs in this particular course
     * TODO Potentially made configurable in a ui somewhere
     */
    // private const COURSE_ID = 34;
    private const COURSE_ID = 27;


    /**
     * Controller constructor.
     */
    public function __construct(
    ) {
    }


    /**
     * Show a list of people who could potentially be awarded Star or Superstar
     * badges for accomplishing 99% and 100% respectively, in their Units
     *
     * @throws Error
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getCandidates(): void
    {
        /* Get all the data ready
         */
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
         *                 candidacy => superstar, or star
         *             ],
         *             user id => [
         *                 firstname => ...
         *                 lastname => ...
         *                 candidacy => superstar, or star
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
         *                 candidacy => superstar, or star
         *             ],
         *             user id => [
         *                 firstname => ...
         *                 lastname => ...
         *                 candidacy => superstar, or star
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

        $this->response = $this->responseFromTemplate('@superstar/index.twig', [
            'report' => [
                'title' => self::REPORT_TITLE,
            ],
            'results' => $stack
        ], self::REPORT_TITLE);
    }


    /**
     * Prepare a statement for award candidates
     *
     * @return \PDOStatement
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function generateCandidatesStatement(): \PDOStatement
    {
        /* TODO Potentially insert a course_unit_specification.id param here,
         *  to only obtain candidacy for a particular unit - this would be a
         *  performance workaround that avoids generating the entire data set
         *  at the very beginning...but it means you gotta click a lot
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
            ORDER BY unit_spec_id, unit_spec_title, user_lastname, user_id
        ";

        $statement = $this->getDi()->get('databaseInternal')->prepare($query);

        $statement->execute([
            'courseId' => self::COURSE_ID
        ]);

        return $statement;
    }


    /**
     * Award a badge to a particular person
     */
    public function postAward(): void
    {
    }
}

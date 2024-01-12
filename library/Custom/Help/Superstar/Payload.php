<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Help\Superstar;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\RoleMapper;

class Payload
{
    /**
     * We only care about the Unit Specs in this particular course
     * TODO Potentially made configurable in a ui somewhere
     */
    /* These are for a local vagrant emulation
     */
//    private const COURSE_ID = 27;

    /* These are the actual values in help
     */
    private const COURSE_ID = 34;


    /**
     * For a special OLC 2023 edge case
     */
    /* These are for a local vagrant emulation
     */
//    private const SUPERUSER_UNIT_SPEC_ID = '90';
//    private const SUPERUSER_QUIZ_SPEC_ASSESSMENT_ID = 633;

    /* These are the actual values in help
     */
    private const SUPERUSER_UNIT_SPEC_ID = '137';
    private const SUPERUSER_QUIZ_SPEC_ASSESSMENT_ID = 81927;


    /**
     * @param IConnector  $database
     * @param MapperProxy $assessmentMapperProxy
     * @param RoleMapper  $roleMapper
     */
    public function __construct(
        private IConnector $database,
        private MapperProxy $assessmentMapperProxy,
        private \Schoolbox\RoleMapper $roleMapper
    ) {
    }


    /**
     * Generate an array of data about everyone's candidacy potential
     *
     * @param int $year
     * @return array
     * @throws DefinitionException
     * @throws Deleted
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function generate(int $year): array
    {
        $statement = $this->generateCandidatesStatement();

        /* A list of tuples:
         *
         * $stack = [
         *     spec id => [
         *         unit spec title => ...
         *         actionables => [
         *             superstar => [],
         *             star => [],
         *         ],
         *         candidates => [
         *             user id => [
         *                 meta => [
         *                     id => ...
         *                     firstname => ...
         *                     lastname => ...
         *                     school => [
         *                         'folderId' => ...
         *                         'name' => ...
         *                         'code' => ...
         *                     ]
         *                 ],
         *                 marks => [
         *                     ...
         *                 ],
         *             ],
         *             user id => [
         *                 meta => [
         *                     id => ...
         *                     firstname => ...
         *                     lastname => ...
         *                     school => [
         *                         'folderId' => ...
         *                         'name' => ...
         *                         'code' => ...
         *                     ]
         *                 ],
         *                 marks => [
         *                     ...
         *                 ],
         *             ],
         *             ...
         *         ],
         *     ],
         *     spec id => [
         *         unit spec title => ...
         *         actionables => [
         *             superstar => [],
         *             star => [],
         *         ],
         *         candidates => [
         *             user id => [
         *                 meta => [
         *                     id => ...
         *                     firstname => ...
         *                     lastname => ...
         *                     school => [
         *                         'folderId' => ...
         *                         'name' => ...
         *                         'code' => ...
         *                     ]
         *                 ],
         *                 marks => [
         *                     ...
         *                 ],
         *             ],
         *             user id => [
         *                 meta => [
         *                     id => ...
         *                     firstname => ...
         *                     lastname => ...
         *                     school => [
         *                         'folderId' => ...
         *                         'name' => ...
         *                         'code' => ...
         *                     ]
         *                 ],
         *                 marks => [
         *                     ...
         *                 ],
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
            $stack[$row['unit_spec_id']]['candidates'][$row['user_id']]['marks'][] = $row['norm_mark'];
        }

        /* Special Case for OLC 2023 incarnation only:
         * You can remove this augmentation once you're fully done with OLC 2023
         */
        if (2023 === $year) {
            /* Find this random quiz, based on its spec id
             * Build this extra dangling data struct lol
             */
            $augmentation = $this->augment2023SpecialCase();

            /* Shank it in
             */
            foreach ($augmentation as $userId => $userData) {
                $stack[self::SUPERUSER_UNIT_SPEC_ID]['candidates'][$userId] = $userData;
            }
        }

        /* Now decide everyone's outcome
         */
        foreach ($stack as $unitSpecId => &$unitSpec) {
            /* Sort candidates by school code,
             * then by the guy's lastname
             */
            uasort($unitSpec['candidates'], static function ($a, $b) {
                $schoolA = (string)$a['meta']['school']['code'];
                $schoolB = (string)$b['meta']['school']['code'];
                $lastnameA = (string)$a['meta']['lastname'];
                $lastnameB = (string)$b['meta']['lastname'];

                if ($schoolA === $schoolB) {
                    if ($lastnameA === $lastnameB) {
                        return 0;
                    }

                    return strnatcasecmp($lastnameA, $lastnameB);
                }

                return strnatcasecmp($schoolA, $schoolB);
            });

            /* Figure out Superstardom and Stardom candidacy for each guy
             */
            foreach ($unitSpec['candidates'] as $userId => $userData) {
                $outcome = $candidacyDecider->decide($userData['marks']);

                /* Only remember people who achieved Superstardom or Stardom
                 * Everyone else doesn't matter
                 */
                if (null !== $outcome) {
                    /* Cross reference if they already received this badge previously
                     * We don't need to give them the same badge twice
                     * (this is done in twig to hide the button)
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
        $validGroupTypeIds = implode(',', $this->roleMapper->findByColumn('group_type', \Schoolbox\Role::STUDENT)->indexBy('id')->keys());

        /* TODO Potentially insert a course_unit_specification.id param here,
         *  to only obtain candidacy for a particular unit - this would be a
         *  performance workaround that avoids generating the entire data set
         *  at the very beginning...but it means you gotta click a lot
         */
        $query = "
            SELECT
                cui.course_unit_specification_id AS unit_spec_id,
                f0.name AS unit_spec_title,
                u.id AS user_id,
                u.firstname AS user_firstname,
                u.lastname AS user_lastname,
                f2.id AS folder_id,
                f2.name AS school_name,
                fc.code AS school_code,
                prc.project_id AS project_id,
                prc.norm_mark
            FROM course_implementation ci
            INNER JOIN course_unit_implementation cui on ci.id = cui.course_implementation_id
            INNER JOIN course_unit_specification cus ON cui.course_unit_specification_id = cus.id
            INNER JOIN folder f0 ON cus.folder_id = f0.id
            INNER JOIN assessment_course_unit_implementation acui ON cui.id = acui.course_unit_implementation_id
            INNER JOIN project p ON acui.assessment_id = p.assessment_id
            INNER JOIN project_result prc ON p.assessment_id = prc.project_id
            INNER JOIN `user` u ON prc.user_id = u.id
            INNER JOIN folder f1 ON ci.folder_id = f1.id
            INNER JOIN folder f2 ON f1.parent = f2.id
            LEFT JOIN folder_code fc ON f2.id = fc.folder_id
            WHERE ci.course_specification_id = :courseId
            AND cui.deleted_at IS NULL
            AND cus.deleted_at IS NULL
            AND f0.deleted_at IS NULL
            AND p.deleted_at IS NULL
            AND prc.deleted_at IS NULL
            AND u.deleted_at IS NULL
            AND f1.deleted_at IS NULL
            AND f2.deleted_at IS NULL
            AND fc.deleted_at IS NULL
            AND fc.code IS NOT NULL
            AND u.group_id IN ({$validGroupTypeIds})
            ORDER BY unit_spec_title, user_lastname, user_id
        ";

        $statement = $this->database->prepare($query);

        $statement->execute([
            'courseId' => self::COURSE_ID
        ]);

        return $statement;
    }


    /**
     *  There's a random assessment outside of projects so its result won't appear in
     *  the megaquery above; the results aren't in project_result table. They're elsewhere.
     *  For OLC 2023 we specifically have to use the swamp or the assess_attempt table
     *  directly, to obtain these marks for this particular quiz. This precipitates a
     *  querystring param, ?year=2023, to decide when to look for this rando quiz.
     *  We can augment $stack here, then subject it to the usual decisionmaking
     *
     * @return array
     * @throws DefinitionException
     * @throws Deleted
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function augment2023SpecialCase(): array
    {
        $query = "
            SELECT
                implementation_assessment_id,
                f2.id AS folder_id,
                f2.`name` AS school_name,
                fc.`code` AS school_code
            FROM assessment_specification_implementation asi
            INNER JOIN assessment a1 ON asi.implementation_assessment_id = a1.id
            INNER JOIN assessment a2 ON asi.specification_assessment_id = a2.id
            INNER JOIN mv_assessment_relation mvar ON asi.implementation_assessment_id = mvar.assessment_id
            INNER JOIN folder f1 ON mvar.folder_id = f1.id
            INNER JOIN folder f2 ON f1.parent = f2.id
            LEFT JOIN folder_code fc ON fc.folder_id = f2.id
            WHERE asi.specification_assessment_id = :assessmentSpecId
            AND f1.deleted_at IS NULL
            AND f2.deleted_at IS NULL
            AND fc.deleted_at IS NULL
            AND a1.deleted_at IS NULL
            AND a2.deleted_at IS NULL
        ";

        $statement = $this->database->prepare($query);

        $statement->execute([
            'assessmentSpecId' => self::SUPERUSER_QUIZ_SPEC_ASSESSMENT_ID
        ]);

        /* Fetch feedbacks for each implementation and manually collate it into
         * a data struct synonymous with $stack's - because it's a special case
         * we can probably hardcode a bunch of shortcuts
         */
        $stack = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $assessment = $this->assessmentMapperProxy->findByIdForReview((int)$row['implementation_assessment_id']);

            foreach ($assessment->getAllParticipants() as $participant) {
                $normMark = $participant->getNormalisedMark(true);

                /* Assign some metadata about this user
                 */
                $stack[$participant->getUser()->getId()]['meta'] = [
                    'id' => $participant->getUser()->getId(),
                    'firstname' => $participant->getUser()->getFirstname(),
                    'lastname' => $participant->getUser()->getLastname(),
                    'school' => [
                        'folderId' => $row['folder_id'],
                        'name' => $row['school_name'],
                        'code' => $row['school_code']
                    ]
                ];

                $stack[$participant->getUser()->getId()]['marks'][] = $normMark;
            }
        }

        /* I guess we don't need this anymore; it just occupies memory
         */
        unset($assessment);

        return $stack;
    }
}

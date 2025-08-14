<?php

namespace Schoolbox\Learning\Moment\Evidence\CohortReport;

use Alaress\Database\Predicate\Builder;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Moment\Evidence;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;

class Payload
{
    /**
     * Cohort Report for learning moments looks seven days in the past by default
     */
    private const COHORT_REPORT_PAST_THRESHOLD = -7;


    /**
     * Constructor
     *
     * @param Evidence\Mapper $mapper
     * @param UserMapper $userMapper
     * @param \External_Class $externalClass
     * @param UserAccessMapper $userAccessMapper
     */
    public function __construct(
        private Evidence\Mapper $mapper,
        private UserMapper $userMapper,
        private \External_Class $externalClass,
        private UserAccessMapper $userAccessMapper,
    ) {
    }


    /**
     * Find a set of learning moments for the cohort of users
     *
     *     Cohort of Users def.
     *       A set of user ids in a particular folder (either as a class folder and/or group)
     *       This is the "cohort of users" (members of a particular folder) which can be further
     *       reduced by intersecting $userIds, a set of users chosen by the Filters ui
     *
     * ...according to optional filters chosen:
     *
     *   - where the filtered recipients $userIds is a subset of $cohortIds,
     *   - where updated between a date range specified by [$fromDate, $toDate],
     *   - where moments were authored by one of $authorIds
     *   - where moments are currently in one of the states in $states
     *
     * These should be considered boolean AND between these clauses.
     *
     * @param Folder\Model $folder
     * @param string|null $userIds
     * @param string|null $fromDate
     * @param string|null $toDate
     * @param string|null $authorIds
     * @param string|null $tagIds
     * @param string|null $states
     * @return GenericCollection
     * @throws Exception
     * @throws NotFound
     */
    public function findByFilters(
        Folder\Model $folder,
        ?string $userIds,
        ?string $fromDate,
        ?string $toDate,
        ?string $authorIds,
        ?string $tagIds,
        ?string $states
    ): GenericCollection {
        $params = [];

        $wherePieces = [
            'WHERE deleted_at IS NULL',
        ];

        /* Set up agglutinated query
         */
        $query = "
            SELECT id
            FROM {$this->mapper->getTable()}
        ";

        /* Detect if we needed to filter by Tags
         * This involves a conditional inner join
         */
        if ($tagIds) {
            $tagIds = explode(',', $tagIds);

            if (\count($tagIds)) {
                $placeholders = Builder::getArrayPlaceholders($tagIds, 'tagIds');
                $params = array_merge($params, Builder::getArrayParameters($tagIds, 'tagIds'));

                $query .= "
                    INNER JOIN evidence_tag et ON et.evidence_id = id
                ";

                $wherePieces[] = "
                    AND et.tag_id IN {$placeholders}
                ";
            }
        }

        /* Filter $cohortIds to a set of recipient userIds, if specified
         */
        if ($userIds) {
            $userIds = explode(',', $userIds);

            if (\count($userIds)) {
                $cohortIds = $this->findCohortIds($folder);
                $intersectingUsers = array_intersect($cohortIds, $userIds);

                if (\count($intersectingUsers)) {
                    $placeholders = Builder::getArrayPlaceholders($intersectingUsers, 'intersectingUserIds');
                    $params = array_merge($params, Builder::getArrayParameters($intersectingUsers, 'intersectingUserIds'));

                    $wherePieces[] = "
                        AND user_id IN {$placeholders}
                    ";
                } else {
                    $placeholders = Builder::getArrayPlaceholders($cohortIds, 'cohortIds');
                    $params = array_merge($params, Builder::getArrayParameters($cohortIds, 'cohortIds'));

                    $wherePieces[] = "
                        AND user_id IN {$placeholders}
                    ";
                }
            }
        }

        /* Filter to a set of authors, if specified
         */
        if ($authorIds) {
            $authorIds = explode(',', $authorIds);

            if (\count($authorIds)) {
                $placeholders = Builder::getArrayPlaceholders($authorIds, 'authorIds');
                $params = array_merge($params, Builder::getArrayParameters($authorIds, 'authorIds'));

                $wherePieces[] = "
                    AND created_by IN {$placeholders}
                ";
            }
        }

        /* Filter to a date range, if specified; both dates must be specified to use a user-defined date range,
         * otherwise resorts to system defaults of [today, -7 days]
         */
        if ($fromDate && $toDate) {
            $from = (new \Alaress_Date_Format($fromDate, \Alaress_Date_Format::DATABASE_DATE_FORMAT))->startOfDay();
            $to = (new \Alaress_Date_Format($toDate, \Alaress_Date_Format::DATABASE_DATE_FORMAT))->endOfDay();

            /* Check for a maximum length of time of [now, -1 year at start of day]
             */
            $threshold = $to->addYears(-1)->startOfDay();

            if ($from->lt($threshold)) {
                $from = $threshold;
            }
        } else {
            $from = \Alaress_Date_Format::now()->addDays(self::COHORT_REPORT_PAST_THRESHOLD)->startOfDay();
            $to = \Alaress_Date_Format::now()->endOfDay();
        }

        $wherePieces[] = "
            AND updated_at BETWEEN :from AND :to
        ";

        $params = array_merge($params, [
            'from' => $from->getDatabase(),
            'to' => $to->getDatabase(),
        ]);

        /* TODO Add "Filter by classes and groups"
         *  Homepage components allow students to create learning moments from a homepage
         *  that isn't necessarily the one whose Cohort Overview you're currently looking at.
         *  If you're in 9ENGA Cohort Overview, you can still see the learning moments that
         *  a student experienced -in some other folder elsewhere-, not just your 9ENGA class.
         *
         * Jezwords:
         *     > There will be 3 places that students can create a learning moment from. Dashboards, the activity feed
         *     > and any homepage with a component.  As a result, the tagging of a class or group may not occur correctly,
         *     > and students might also do it wrongly.
         *     >
         *     > We therefore want to give a teacher a method to look specifically at things created for a particular
         *     > class/group, but first and foremost focus on the students belonging to that cohort and make all of the
         *     > students' learning moments more discoverable.
         *     >
         *     > If you're in 3RED Cohort Overview, you can still see the learning moments that
         *     > a student experienced -in some other folder elsewhere-, not just your 3RED class.
         */

        /* TODO Add curriculum node filtering
         */

        /* Filter to a set of states/statuses, if specified
         */
        if ($states) {
            $states = explode(',', $states);

            if (\count($states)) {
                /* Ensure that the state provided is one of the canonical states we've established
                 * so you can't request moments with the status "cat"
                 */
                $validStates = array_intersect($states, Evidence\Model::VALID_STATUSES);

                /* Do a deep inspection for the pb, which isn't exactly nice; look at the various
                 * *_by and *_at date fields to determine what state a learning moment is in.
                 * We don't have access to the convenience methods isDraft() isPublished() etc.
                 * for the pub; those require hydrated models. Maybe split this out into something
                 * else later.
                 */
                if (\count($validStates)) {
                    $stateQueryPieces = [];

                    foreach ($validStates as $state) {
                        if (Evidence\Model::STATUS_DRAFT === $state) {
                            /* TODO maybe we should have had a draft_at and draft_by columns
                             *  to shortcut this nonsense, but that's also kind of annoying...
                             */
                            $stateQueryPieces[] = '(
                            submitted_at IS NULL
                            AND submitted_by IS NULL
                            AND published_at IS NULL
                            AND published_by IS NULL
                            AND rejected_at IS NULL
                            AND rejected_by IS NULL
                        )';
                        } elseif (Evidence\Model::STATUS_SUBMITTED === $state) {
                            $stateQueryPieces[] = '(
                            submitted_at IS NOT NULL
                            AND submitted_by IS NOT NULL
                            AND published_at IS NULL
                            AND published_by IS NULL
                            AND rejected_at IS NULL
                            AND rejected_by IS NULL
                        )';
                        } elseif (Evidence\Model::STATUS_PUBLISHED === $state) {
                            $stateQueryPieces[] = '(
                            submitted_at IS NULL
                            AND submitted_by IS NULL
                            AND published_at IS NOT NULL
                            AND published_by IS NOT NULL
                            AND rejected_at IS NULL
                            AND rejected_by IS NULL
                        )';
                        } elseif (Evidence\Model::STATUS_REJECTED === $state) {
                            $stateQueryPieces[] = '(
                            submitted_at IS NULL
                            AND submitted_by IS NULL
                            AND published_at IS NULL
                            AND published_by IS NULL
                            AND rejected_at IS NOT NULL
                            AND rejected_by IS NOT NULL
                        )';
                        }
                    }

                    $stateQuery = implode(' OR ', $stateQueryPieces);

                    $wherePieces[] = "AND ({$stateQuery})";
                }
            }
        }

        /* Finally compute WHERE clause
         */
        $query .= implode("\n", $wherePieces);

        /* All the learning moments that fulfil the filter criteria
         */
        $momentIds = $this->mapper->getDatabase()->executeQuery($query, $params)->fetchColumnArray('id');

        /* Find fully hydrated instances of these moment ids
         */
        return $this->mapper->findByColumn('id', $momentIds)->sortBy('updatedAt', 'DESC');
    }


    /**
     * Find a set of users who are somehow enrolled in $folder as students,
     * and/or involved in $folder as group members
     *
     * Note that a folder can be both a group and a class-coded folder which derives its
     * members from the Externals
     *
     * @param Folder\Model $folder
     * @return array
     * @throws Exception
     * @throws NotFound
     */
    private function findCohortIds(Folder\Model $folder): array
    {
        $groupIds = [];
        $classIds = [];

        /* Get a list of student ids that are in $folder and use a hash lookup
         * to cross-reference everything we find later
         */
        if ($folder->isGroup()) {
            $userAccessList = $this->userAccessMapper->findForFolder($folder)
                ->indexByCallback(static function (UserAccess $userAccess) {
                    return $userAccess->getUser()->getId();
                });

            if ($userAccessList->count()) {
                $groupIds = $this->userMapper->findByColumn('id', $userAccessList->keys())
                    ->filter(function (\Schoolbox\User $user) {
                        return ($user->getRole()->isStudent());
                    })
                    ->indexBy('id')
                    ->keys();
            }
        }

        /* Students of a class-coded folder
         */
        if ('' !== $folder->getCodeString()) {
            $students = array_column($this->externalClass->getStudents($folder->getCodeString()), 'synergy_id');

            /* These are the user instances associated with $folder's class list of students
             * We'll use this to intersect with learning moments $recipients
             */
            if (\count($students)) {
                $classIds = $this->userMapper->findByExternalIds($students, \Schoolbox\Role::STUDENT)
                    ->indexBy('id')
                    ->keys();
            }
        }

        /* Combine stacks
         */
        return array_values(array_unique(array_merge($groupIds, $classIds)));
    }
}

<?php

namespace Schoolbox\Learning\Moment\Evidence\CohortReport;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Alaress\Database\Predicate\Builder;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Moment\Evidence;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;

/**
 * Cursor for the cohort report payload
 *
 * Retrieves all learning moments for each day up to DAYS_PER_REQUEST days in each page
 * Respects any filters applied, as specified in the constructor
 */
class Cursor implements ICursor
{
    /**
     * The length of time in days which each page of results should cover
     */
    private const DAYS_PER_REQUEST = 7;

    /**
     * The codec implementation used for the cursor.
     */
    private ICodec $codec;

    /**
     * Constructor
     *
     * @param Evidence\Mapper $mapper
     * @param UserAccessMapper $userAccessMapper
     * @param Folder\Model $folder
     * @param string|null $userIds
     * @param string|null $fromDate
     * @param string|null $toDate
     * @param string|null $authorIds
     * @param string|null $tagIds
     * @param string|null $states
     */
    public function __construct(
        private Evidence\Mapper $mapper,
        private UserAccessMapper $userAccessMapper,
        private Folder\Model $folder,
        private ?string $userIds = null,
        private ?string $fromDate = null,
        private ?string $toDate = null,
        private ?string $authorIds = null,
        private ?string $tagIds = null,
        private ?string $states = null,
    ) {
        $this->codec = new Base64();
    }

    /**
     * @inheritdoc
     */
    public function next(Collection $collection): ?string
    {
        if ($collection->count() === 0) {
            return null;
        }

        // The next cursor points to the day after the oldest item in the collection
        $earliestDate = null;
        foreach ($collection as $evidence) {
            if ($earliestDate === null || $evidence->getCreatedAt()->lt($earliestDate)) {
                $earliestDate = $evidence->getCreatedAt();
            }
        }

        // TODO: bug, this doesn't detect the last cursor correctly. need to check if we hit the end

        // Encode the cursor with the earliest date we've seen
        return $this->codec->encode([
            'lastDate' => $earliestDate->addDays(-1)->getDatabaseDate(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function current(Collection $collection): ?string
    {
        if ($collection->count() === 0) {
            return null;
        }

        // The current cursor points to the day after the most recent item in the collection
        $latestDate = null;
        foreach ($collection as $evidence) {
            if ($latestDate === null || $evidence->getCreatedAt()->gt($latestDate)) {
                $latestDate = $evidence->getCreatedAt();
            }
        }

        return $this->codec->encode([
            'lastDate' =>  $latestDate->addDays(1)->getDatabaseDate(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function retrieveResultCount(): ?int
    {
        $momentIds = $this->findIdsByFilters();
        return count($momentIds);
    }

    /**
     * @inheritdoc
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        try {
            // All the moment IDs that match the filters
            $momentIds = $this->findIdsByFilters();

            if (empty($momentIds)) {
                return new GenericCollection(Evidence\Model::class);
            }

            // Determine the date range for this page
            $dateRange = $this->getDateRangeForCursor($encodedCursor, $momentIds);
            if ($dateRange === null) {
                return new GenericCollection(Evidence\Model::class);
            }

            // Build predicate to get moments within the date range
            $pb = $this->mapper->getPredicateBuilder()
                ->in($this->mapper->getIdentifierColumn(), $momentIds)
                ->between('created_at', $dateRange['from'], $dateRange['to']);

            // Get the results ordered by creation date descending, then by ID descending
            return $this->mapper->findByPredicates(
                $pb,
                [ 'order' => [ 'created_at' => 'DESC' ] ]
            );
        } catch (Exception | NotFound $e) {
            return new GenericCollection(Evidence\Model::class);
        }
    }

    /**
     * Find a set of learning moment IDs for the cohort of users
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
     * @return array<int>
     * @throws Exception
     * @throws NotFound
     */
    private function findIdsByFilters(): array
    {
        /* If this $folder had no members, there's no meaningful cohort overview to display:
         * return an empty payload
         */
        $cohortIds = $this->findCohortIds($this->folder);

        if (!\count($cohortIds)) {
            return [];
        }

        /* Otherwise we have guys to filter upon
         */

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
        if ($this->tagIds) {
            $tagIds = explode(',', $this->tagIds);

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
        if ($this->userIds) {
            $userIds = explode(',', $this->userIds);

            if (\count($userIds)) {
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
        } else {
            $placeholders = Builder::getArrayPlaceholders($cohortIds, 'cohortIds');
            $params = array_merge($params, Builder::getArrayParameters($cohortIds, 'cohortIds'));

            $wherePieces[] = "
                AND user_id IN {$placeholders}
            ";
        }

        /* Filter to a set of authors, if specified
         */
        if ($this->authorIds) {
            $authorIds = explode(',', $this->authorIds);

            if (\count($authorIds)) {
                $placeholders = Builder::getArrayPlaceholders($authorIds, 'authorIds');
                $params = array_merge($params, Builder::getArrayParameters($authorIds, 'authorIds'));

                $wherePieces[] = "
                    AND created_by IN {$placeholders}
                ";
            }
        }

        /* Filter to a date range, if specified (either FROM, TO, neither, or both)
         */
        $from = null;
        if ($this->fromDate) {
            $from = (new \Alaress_Date_Format($this->fromDate, \Alaress_Date_Format::DATABASE_DATE_FORMAT))->startOfDay();

            $wherePieces[] = "AND created_at >= :from";
            $params['from'] = $from->getDatabase();
        }

        $to = null;
        if ($this->toDate) {
            $to = (new \Alaress_Date_Format($this->toDate, \Alaress_Date_Format::DATABASE_DATE_FORMAT))->endOfDay();

            $wherePieces[] = "AND created_at <= :to";
            $params['to'] = $to->getDatabase();
        }

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
        $states = explode(',', $this->states ?? '');
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

        /* Finally compute WHERE clause
         */
        $query .= implode("\n", $wherePieces);

        /* All the learning moments that fulfil the filter criteria
         */
        return $this->mapper->getDatabase()->executeQuery($query, $params)->fetchColumnArray('id');
    }


    /**
     * Get the date range for the current cursor position
     *
     * @param string|null $encodedCursor
     * @param array<int> $momentIds
     * @return array{from:string,to:string}|null
     */
    private function getDateRangeForCursor(?string $encodedCursor, array $momentIds): ?array
    {
        // Find all possible dates for each moment ID
        // TODO: could avoid this second query if $momentIds already contained the dates
        $pb = $this->mapper->getPredicateBuilder()
            ->in($this->mapper->getIdentifierColumn(), $momentIds);

        $rows = $this->mapper->findColumnsByPredicates(['created_at'], $pb);

        if (empty($rows)) {
            return null;
        }

        // Parse and sort the dates
        $dates = array_map(
            fn($row) => (new \Alaress_Date_Format(
                $row['created_at'],
                \Alaress_Date_Format::DATABASE_DATE_FORMAT
            ))->startOfDay(),
            $rows
        );

        usort($dates, fn($a, $b) => $b->compare($a)); // DESC

        // Find the first date to start the sliding window
        $toDate = null;
        if ($encodedCursor !== null && $encodedCursor !== '') {
            $decoded = $this->codec->decode($encodedCursor);
            if (isset($decoded['lastDate'])) {
                $nextDate = new \Alaress_Date_Format($decoded['lastDate'], \Alaress_Date_Format::DATABASE_DATE_FORMAT);

                // Find next relevant date after (or including) the cursor date
                foreach ($dates as $date) {
                    if ($date->lte($nextDate)) {
                        $toDate = $date;
                        break;
                    }
                }
            }
        }

        // If there wasn't a valid cursor, start from the most recent date
        if ($toDate === null) {
            $toDate = $dates[0];
        }

        // The oldest date is X-1 days before the start date since startOfDay/endOfDay is used below
        $fromDate = (clone $toDate)->addDays(1 - self::DAYS_PER_REQUEST);

        return [
            'from' => $fromDate->startOfDay()->getDatabase(),
            'to' => $toDate->endOfDay()->getDatabase(),
        ];
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
        return $this->userAccessMapper->findUsersInCategory($folder, \Schoolbox\Role::STUDENT)->indexBy('id')->keys();
    }
}

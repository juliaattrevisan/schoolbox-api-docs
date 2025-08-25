<?php

namespace Schoolbox\Learning\Moment\CohortReport;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Alaress\Database\Predicate\Builder;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Moment;
use Schoolbox\Learning\Moment\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Model;

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
     * @param Mapper $mapper
     * @param UserAccessMapper $userAccessMapper
     * @param Model $folder
     * @param array|null $userIds
     * @param string|null $fromDate
     * @param string|null $toDate
     * @param array|null $authorIds
     * @param array|null $folderIds
     * @param array|null $tagIds
     * @param array|null $states
     */
    public function __construct(
        private Moment\Mapper $mapper,
        private UserAccessMapper $userAccessMapper,
        private Folder\Model $folder,
        private ?array $userIds = null,
        private ?string $fromDate = null,
        private ?string $toDate = null,
        private ?array $authorIds = null,
        private ?array $folderIds = null,
        private ?array $tagIds = null,
        private ?array $states = null,
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

        $nextCursorDate = (clone $earliestDate)->addDays(-1);

        // However, there may be no more results left... so check if the cursor is valid
        // Find the learning moment that would start the next page
        $momentIds = $this->findIdsByFilters();
        if (empty($momentIds)) {
            return null;
        }
        $momentDates = $this->findMomentDates($momentIds);

        $nextMoment = null;
        foreach ($momentDates as $date) {
            if ($date->lte($nextCursorDate)) {
                $nextMoment = $date;
                break;
            }
        }

        if ($nextMoment === null) {
            // No more results left, so no cursor
            return null;
        }

        return $this->codec->encode([
            'lastDate' => $nextCursorDate->getDatabaseDate(),
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
            'lastDate' =>  (clone $latestDate)->addDays(1)->getDatabaseDate(),
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
    public function retrieveResults(?string $encodedCursor): GenericCollection
    {
        try {
            // All the moment IDs that match the filters
            $momentIds = $this->findIdsByFilters();

            if (empty($momentIds)) {
                return new GenericCollection(Moment\Model::class);
            }

            // Determine the date range for this page
            $momentDates = $this->findMomentDates($momentIds);
            $dateRange = $this->getDateRangeForCursor($encodedCursor, $momentDates);
            if ($dateRange === null) {
                return new GenericCollection(Moment\Model::class);
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
            return new GenericCollection(Moment\Model::class);
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
        /* Find a set of users who are somehow enrolled in $folder as students,
         * and/or involved in $folder as group members
         *
         * Note that a folder can be both a group and a class-coded folder which derives its
         * members from the Externals
         */
        $cohortIds = $this->userAccessMapper->findUsersInCategory($this->folder, \Schoolbox\Role::STUDENT)->indexBy('id')->keys();

        /* If this $folder had no members, there's no meaningful cohort overview to display:
         * return an empty payload
         */
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
        if ($this->tagIds && \count($this->tagIds)) {
            $placeholders = Builder::getArrayPlaceholders($this->tagIds, 'tagIds');
            $params = array_merge($params, Builder::getArrayParameters($this->tagIds, 'tagIds'));

            $query .= "
                INNER JOIN moment_tag mt ON mt.moment_id = id
            ";

            $wherePieces[] = "
                AND mt.tag_id IN {$placeholders}
            ";
        }

        /* Filter $cohortIds to a set of recipient userIds, if specified
         */
        $intersectingUsers = $this->userIds && \count($this->userIds) ? array_intersect($cohortIds, $this->userIds) : [];
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

        /* Filter to a set of authors, if specified
         */
        if ($this->authorIds && count($this->authorIds)) {
            $placeholders = Builder::getArrayPlaceholders($this->authorIds, 'authorIds');
            $params = array_merge($params, Builder::getArrayParameters($this->authorIds, 'authorIds'));

            $wherePieces[] = "
                AND created_by IN {$placeholders}
            ";
        }

        /* Filter to a date range, if specified (either FROM, TO, neither, or both)
         */
        if ($this->fromDate) {
            $from = (new \Alaress_Date_Format($this->fromDate, \Alaress_Date_Format::DATABASE_DATE_FORMAT))->startOfDay();

            $wherePieces[] = "AND created_at >= :from";
            $params['from'] = $from->getDatabase();
        }

        if ($this->toDate) {
            $to = (new \Alaress_Date_Format($this->toDate, \Alaress_Date_Format::DATABASE_DATE_FORMAT))->endOfDay();

            $wherePieces[] = "AND created_at <= :to";
            $params['to'] = $to->getDatabase();
        }

        /*  Add "Filter by classes and groups"
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
        /* TODO remove this testing diagnostic once the ui actually exists
         */
        $this->folderIds = [
            78, // 9spanisha
            80, // 9enga
        ];

        if ($this->folderIds && count($this->folderIds)) {
            $placeholders = Builder::getArrayPlaceholders($this->folderIds, 'folderIds');
            $params = array_merge($params, Builder::getArrayParameters($this->folderIds, 'folderIds'));

            $query .= "
                INNER JOIN moment_folder mf ON mf.moment_id = id
            ";

            $wherePieces[] = "
                AND mf.folder_id IN {$placeholders}
            ";
        }

        /* TODO Add curriculum node filtering
         */

        /* Filter to a set of states/statuses, if specified
         */
        if ($this->states && \count($this->states)) {
            /* Ensure that the state provided is one of the canonical states we've established
             * so you can't request moments with the status "cat"
             */
            $validStates = array_intersect($this->states, Moment\Model::VALID_STATUSES);

            /* Do a deep inspection for the pb, which isn't exactly nice; look at the various
             * *_by and *_at date fields to determine what state a learning moment is in.
             * We don't have access to the convenience methods isDraft() isPublished() etc.
             * for the pub; those require hydrated models. Maybe split this out into something
             * else later.
             */
            if (\count($validStates)) {
                $stateQueryPieces = [];

                foreach ($validStates as $state) {
                    if (Moment\Model::STATUS_DRAFT === $state) {
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
                    } elseif (Moment\Model::STATUS_SUBMITTED === $state) {
                        $stateQueryPieces[] = '(
                            submitted_at IS NOT NULL
                            AND submitted_by IS NOT NULL
                            AND published_at IS NULL
                            AND published_by IS NULL
                            AND rejected_at IS NULL
                            AND rejected_by IS NULL
                        )';
                    } elseif (Moment\Model::STATUS_PUBLISHED === $state) {
                        $stateQueryPieces[] = '(
                            submitted_at IS NULL
                            AND submitted_by IS NULL
                            AND published_at IS NOT NULL
                            AND published_by IS NOT NULL
                            AND rejected_at IS NULL
                            AND rejected_by IS NULL
                        )';
                    } elseif (Moment\Model::STATUS_REJECTED === $state) {
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
     * Find all the createdAt dates for the learning moments to be used when determining the date range
     * Returns the startOfDay equivalent of each date, sorted in descending order (most recent first)
     *
     * @param array<int> $momentIds
     * @return array<int,\Alaress_Date_Format>
     */
    public function findMomentDates(array $momentIds): array
    {
        if (empty($momentIds)) {
            return [];
        }

        $pb = $this->mapper->getPredicateBuilder()
            ->in($this->mapper->getIdentifierColumn(), $momentIds);
        $rows = $this->mapper->findColumnsByPredicates(['created_at'], $pb);

        $dates = array_map(
            fn($row) => (new \Alaress_Date_Format($row['created_at'], \Alaress_Date_Format::DATABASE_DATE_FORMAT))->startOfDay(),
            $rows
        );

        usort($dates, fn($a, $b) => $b->compare($a)); // DESC

        return $dates;
    }


    /**
     * Get the date range for the current cursor position
     *
     * @param string|null $encodedCursor
     * @param array<int,\Alaress_Date_Format> $momentDates
     * @return array{from:string,to:string}|null
     */
    private function getDateRangeForCursor(?string $encodedCursor, array $momentDates): ?array
    {
        // Find the first date to start the sliding window
        $toDate = null;
        if ($encodedCursor !== null && $encodedCursor !== '') {
            $decoded = $this->codec->decode($encodedCursor);
            if (isset($decoded['lastDate'])) {
                $nextDate = new \Alaress_Date_Format($decoded['lastDate'], \Alaress_Date_Format::DATABASE_DATE_FORMAT);

                // Find next relevant date after (or including) the cursor date
                foreach ($momentDates as $date) {
                    if ($date->lte($nextDate)) {
                        $toDate = $date;
                        break;
                    }
                }

                // If we didn't find anything, no point in continuing
                if ($toDate === null) {
                    return null;
                }
            }
        }

        // If there wasn't a valid cursor, start from the most recent date
        if ($toDate === null) {
            $toDate = $momentDates[0];
        }

        // The oldest date is X-1 days before the start date since startOfDay/endOfDay is used below
        $fromDate = (clone $toDate)->addDays(1 - self::DAYS_PER_REQUEST);

        return [
            'from' => $fromDate->startOfDay()->getDatabase(),
            'to' => $toDate->endOfDay()->getDatabase(),
        ];
    }
}

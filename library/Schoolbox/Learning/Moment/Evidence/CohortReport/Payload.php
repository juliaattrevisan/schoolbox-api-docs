<?php

namespace Schoolbox\Learning\Moment\Evidence\CohortReport;

use Alaress\Database\Predicate\Builder;
use Schoolbox\GenericCollection;

class Payload
{
    /**
     * Cohort Report for learning moments looks seven days in the past by default
     */
    private const COHORT_REPORT_PAST_THRESHOLD = -7;


    /**
     * @param \Schoolbox\Learning\Moment\Evidence\Mapper $mapper
     */
    public function __construct(private \Schoolbox\Learning\Moment\Evidence\Mapper $mapper) {

    }


    /**
     * Find a set of learning moments according to optional filters chosen:
     *
     *   - where the recipients are in $userIds,
     *   - where updated between a date range specified by [$fromDate, $toDate],
     *   - where moments were authored by $authorId
     *   - where moments are currently in the  state $state
     *
     * These should be considered boolean AND between these clauses.
     *
     * @param string|null $userIds
     * @param string|null $fromDate
     * @param string|null $toDate
     * @param string|null $authorIds
     * @param string|null $states
     * @return GenericCollection
     */
    public function findByFilters(
        ?string $userIds,
        ?string $fromDate,
        ?string $toDate,
        ?string $authorIds,
        ?string $states
    ): GenericCollection {
        $query = "
            SELECT id
            FROM {$this->mapper->getTable()}
            WHERE deleted_at IS NULL
        ";

        $params = [];

        /* Filter to a set of recipient userIds, if specified
         */
        $userIds = explode(',', $userIds);

        if ($userIds && \count($userIds)) {
            $placeholders = Builder::getArrayPlaceholders($userIds, 'userIds');
            $params = array_merge($params, Builder::getArrayParameters($userIds, 'userIds'));

            $query .= "
                AND user_id IN {$placeholders}
            ";
        }

        /* Filter to a set of authors, if specified
         */
        $authorIds = explode(',', $authorIds);

        if ($authorIds && \count($authorIds)) {
            $placeholders = Builder::getArrayPlaceholders($authorIds, 'authorIds');
            $params = array_merge($params, Builder::getArrayParameters($authorIds, 'authorIds'));

            $query .= "
                AND created_by IN {$placeholders}
            ";
        }

        /* Filter to a date range, if specified; both dates must be specified to use a user-defined date range,
         * otherwise resorts to system defaults of [today, -7 days]
         */
        if ($fromDate && $toDate) {
            $from = (new \Alaress_Date_Format($fromDate, \Alaress_Date_Format::DATABASE_DATE_FORMAT))->startOfDay();
            $to = (new \Alaress_Date_Format($toDate, \Alaress_Date_Format::DATABASE_DATE_FORMAT))->endOfDay();

            /* Check for a maximum length of time of [now, -1 year at start of day]
             */
            $threshold = \Alaress_Date_Format::now()->addYears(-1)->startOfDay();

            if ($from->lt($threshold)) {
                $from = $threshold;
            }
        } else {
            $from = \Alaress_Date_Format::now()->addDays(self::COHORT_REPORT_PAST_THRESHOLD)->startOfDay();
            $to = \Alaress_Date_Format::now()->endOfDay();
        }

        $query .= "
            AND updated_at BETWEEN :from AND :to
        ";

        $params = array_merge($params, [
            'from' => $from->getDatabase(),
            'to' => $to->getDatabase(),
        ]);



        new \Alaress_Probe($query, $params);
        die();

//
//        /* Optionally use filter settings to minimise the result set;
//         */
        //
        //        /* Ensure that the state provided is one of the canonical states we've established
        //         * so you can't request moments with the status "cat"
        //         */
        //        $validStates = array_intersect($states, Model::VALID_STATuSES);
        //
        //        /* Do a deep inspection for the pb, which isn't exactly nice; look at the various
        //         * *_by and *_at date fields to determine what state a learning moment is in.
        //         * We don't have access to the convenience methods isDraft() isPublished() etc.
        //         * for the pub; those require hydrated models. Maybe split this out into something
        //         * else later.
        //         */
        //        if (\count($validStates)) {
        //            $subpredicate = $this->getPredicateBuilder()->any
        //
        //            foreach ($validStates as $state) {
        //                if (Model::STATUS_DRAFT === $state) {
        //                    /* TODO maybe we should have had a draft_at and draft_by columns
        //                     *  to shortcut this nonsense, but that's also kind of annoying...
        //                     */
        //                    $draftPb = $this->getPredicateBuilder()
        //                        ->isNull('submitted_at')
        //                        ->isNull('submitted_by')
        //                        ->isNull('published_at')
        //                        ->isNull('published_by')
        //                        ->isNull('rejected_at')
        //                        ->isNull('rejected_by');
        //                } elseif (Model::STATUS_SUBMITTED === $state) {
        //                    $submittedPb = $this->getPredicateBuilder()
        //                        ->notNull('submitted_at')
        //                        ->notNull('submitted_by');
        //                } elseif (Model::STATUS_PUBLISHED === $state) {
        //                    $publishedPb = $this->getPredicateBuilder()
        //                        ->notNull('published_at')
        //                        ->notNull('published_by');
        //                } elseif (Model::STATUS_REJECTED === $state) {
        //                    $rejectedPb = $this->getPredicateBuilder()
        //                        ->notNull('rejected_at')
        //                        ->notNull('rejected_by');
        //                }
        //            }
        //        }

        die();

        /* Could maybe be reductive, or convert to a raw sql for a mega join
         * Mega join sounds too dirty for the number of tags that could exist.
         */
        $tagIds = null;

        /* TODO Curric nodes come later.
         */
        $curriculumNodeIds = null;

        return $this->mapper->findByPredicates($pb)->sortBy('updatedAt', 'DESC');
    }
}
<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Feed;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Alaress\Database\Connector\IConnector;
use Alaress_Date_Format;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Feed for a user's learning moment feed.
 *
 * This combines dueWorkFeedback, evidence and badgeAwards into the one cursored result.
 *
 * The purpose of a cursor
 * -----------------------
 * A call to Cursor->retrieveResults() will return a list of items that are either 'evidence', 'badgeAward', or
 * 'dueWorkFeedback', sorted into buckets according to date.  The purpose of the cursor is to uniquely identify a
 * position in this sorted list.  Since each type of item has its own set of IDs, ID alone is not enough information.
 * We need the date/time, the type of object, and the object's ID.  For more info see the comments in the ICursor class.
 */
class Cursor implements ICursor
{
    /**
     * Discriminator value for identifying that a retrieved result points to an
     * evidence record.
     */
    public const DISCRIMINATOR_EVIDENCE = 'evidence';

    /**
     * Discriminator value for identifying that a retrieved result points to a
     * badge award.
     */
    public const DISCRIMINATOR_BADGE_AWARD = 'badgeAward';

    /**
     * Discriminator value for identifying that a retrieved result points to a
     * duework feedback.
     */
    public const DISCRIMINATOR_DUE_WORK_FEEDBACK = 'dueWorkFeedback';

    /**
     * Whether the learning moments module is enabled
     */
    private bool $learningMomentsEnabled;

    /**
     * Whether the badgeAwards module is enabled
     */
    private bool $badgeAwardsEnabled;

    private bool $gradesEnabled;

    /**
     * Constructor
     *
     * @param IConnector               $database
     * @param User                     $viewee
     * @param User                     $viewer
     * @param Alaress_Date_Format|null $expiryDate If provided: don't fetch any badge awards which expire before this date.
     * @param int                      $limit      The maximum number of results to fetch in one query.
     * @param IConfigLoader            $config
     * @param ICodec|null              $codec      The codec for encoding/decoding a cursor.
     *
     * @param array $filters
     *     Array of conditions to be met for records to be included.
     *     If the filters array is empty, then all records should be included.
     *     If the filters array is non-empty, then only records that match one or more filters should be included.
     *     The format expected for each condition depends on the feedItemType.  See examples below.
     *
     *     Eg. $filters = [
     *         ['feedItemType' => 'dueWorkFeedback'],                            // Filter 1 (for assessments     ).
     *         ['feedItemType' => 'badgeAward', 'badgeStatus' => 'achievement'], // Filter 2 (for achievements    ).
     *         ['feedItemType' => 'badgeAward', 'badgeStatus' => 'goal'],        // Filter 3 (for goals           ).
     *         ['feedItemType' => 'evidence', 'tags' => ['Tag 1', 'Tag 2']],     // Filter 4 (for learning moments).
     *     ];
     *     The filters array above should be interpreted like so:
     *         $result =
     *             SELECT * FROM submission_return                      // Due to Filter 1.
     *             UNION
     *             SELECT * FROM badge_user  WHERE (
     *                 status='achievement' OR                          // Due to Filter 2.
     *                 status='goal'                                    // Due to Filter 3.
     *             )
     *             UNION
     *             SELECT *
     *             FROM evidence        AS e
     *             JOIN evidence_tag    AS et ON et.evidence_id=e.id
     *             JOIN lu_evidence_tag AS luet ON luet.id=et.tag_id
     *             WHERE luet.name IN ('Tag 1', 'Tag 2')                // Due to Filter 4.
     *
     *     Notes:
     *      * If the $filters array is non-empty, but no filter with 'feedItemType'='badgeAward' exists, no records
     *        from badge_user should be included.  Likewise for the other feedItemTypes and corresponding tables.
     *      * Where conditions not relevant to filters have been left out of the example query (eg. deleted_at=NULL, etc.)
     */
    public function __construct(
        private IConnector $database,
        private User $viewee,
        private User $viewer,
        private Array $filters,
        private ?Alaress_Date_Format $expiryDate,
        private int $limit,
        IConfigLoader $config,
        private ?ICodec $codec = null
    ) {
        $this->codec                  = $codec ?? new Base64();
        $this->learningMomentsEnabled = $config->get(ConfigMapper::NAME_LEARNING_MOMENTS_ENABLED)
            && $viewee->getLearningMomentsEnabled()
            && (!$viewer->getRole()->isStaff() || $viewer->getRole()->getAccessEvidence());
        $this->badgeAwardsEnabled          = $config->get(ConfigMapper::NAME_BADGES_ENABLED);
        $this->gradesEnabled          = $config->get(ConfigMapper::NAME_GRADES_ENABLED)
            && (!$viewer->getRole()->isStaff() || $viewer->getRole()->getViewStudentGrades());
    }

    /**
     * @inheritdoc
     */
    public function next(Collection $collection): ?string
    {
        // As usual: if the collection is empty, there is no next
        if ($collection->count() === 0) {
            return null;
        }

        // Find the last item in the collection: get a pointer to that
        // Expect `$last` to be an array
        return $this->codec->encode($collection->last());
    }

    /**
     * This returns null for now, until there's a need for the current cursor.
     *
     * @param Collection $collection
     * @return null
     */
    public function current(Collection $collection): ?string
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function retrieveResultCount(): ?int
    {
        $count        = 0;
        $countQueries = [];

        if ($this->learningMomentsEnabled) {
            [$evidenceCountQuery, $evidenceCountParams] = $this->getEvidenceQuery([], $this->filters, countOnly: true);
            if ($evidenceCountQuery !== null) {
                $countQueries[] = $this->database->executeQuery($evidenceCountQuery, $evidenceCountParams);
            }
        }

        if ($this->badgeAwardsEnabled) {
            [$badgeAwardsCountQuery, $badgeAwardsCountParams] = $this->getBadgeAwardsQuery([], $this->filters, countOnly: true);
            if ($badgeAwardsCountQuery !== null) {
                $countQueries[] = $this->database->executeQuery($badgeAwardsCountQuery, $badgeAwardsCountParams);
            }
        }

        if ($this->gradesEnabled) {
            [$dueWorkFeedbackCountQuery, $dueWorkFeedbackCountParams] = $this->getDueWorkFeedbackQuery([], $this->filters, countOnly: true);
            if ($dueWorkFeedbackCountQuery !== null) {
                $countQueries[] = $this->database->executeQuery($dueWorkFeedbackCountQuery, $dueWorkFeedbackCountParams);
            }
        }

        foreach ($countQueries as $countQuery) {
            $count += (int)$countQuery->fetchColumn();
        }

        return $count;
    }

    /**
     * @inheritdoc
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        // Don't need to know the details of the badge:
        // just what it points to
        // There's a possible issue in that `badge_user.badge_id` is not unique
        $params    = ['limit' => $this->limit,];
        $wherePart = '';

        if (isset($encodedCursor)) {
            // the cursor contains 'date', 'id', 'discriminator' fields
            $cursor = $this->codec->decode($encodedCursor);

            // The usual cursor where part:
            // fetch items from before the last item's date, as well as ones
            // which match the last item's date and whose other fields are less
            // than the current field
            $whereParts[]             = '(date < :date1)';
            $whereParts[]             = '(date = :date2 AND discriminator > :discriminator2)';
            $whereParts[]             = '(date = :date3 AND discriminator = :discriminator3 AND id < :id3)';
            $wherePart                = 'WHERE ' . implode(' OR ', $whereParts);
            $params['date1']          = $cursor['date'];
            $params['date2']          = $cursor['date'];
            $params['date3']          = $cursor['date'];
            $params['discriminator2'] = $cursor['discriminator'];
            $params['discriminator3'] = $cursor['discriminator'];
            $params['id3']            = $cursor['id'];
        }

        $queries = [];

        if ($this->learningMomentsEnabled) {
            [$evidenceQuery, $params] = $this->getEvidenceQuery($params, $this->filters, countOnly: false);
            if ($evidenceQuery !== null) {
                $queries[] = $evidenceQuery;
            }
        }

        if ($this->badgeAwardsEnabled) {
            [$badgeAwardsQuery, $params] = $this->getBadgeAwardsQuery($params, $this->filters, countOnly: false);
            if ($badgeAwardsQuery !== null) {
                $queries[] = $badgeAwardsQuery;
            }
        }

        if ($this->gradesEnabled) {
            [$dueWorkFeedbackQuery, $params] = $this->getDueWorkFeedbackQuery($params, $this->filters, countOnly: false);
            if ($dueWorkFeedbackQuery !== null) {
                $queries[] = $dueWorkFeedbackQuery;
            }
        }

        if (count($queries) === 0) {
            return new Collection();
        }

        $query = (
            'SELECT id, discriminator, date FROM (
                 ' . implode(' UNION ALL ', $queries) . "
             ) AS combined
             $wherePart
             ORDER BY date desc, discriminator asc, id desc
             LIMIT :limit"
        );

        return new Collection(
            $this->database->executeQuery($query, $params)->fetchAll()
        );
    }

    /**
     * Returns query to get due work feedback.
     * @param array $params
     * @param array $filters
     * @param bool  $countOnly
     * @return array
     */
    private function getDueWorkFeedbackQuery(array $params, array $filters, bool $countOnly): array
    {
        // Only include if $this->filters is empty or 'dueWorkFeedback' is specified in at least one filter.
        $includeDueWorkFeedback = (count($filters) === 0);
        foreach ($filters as $filter) {
            if ($filter['feedItemType'] === 'dueWorkFeedback') {
                $includeDueWorkFeedback = true;
            }
        }

        if (!$includeDueWorkFeedback) {
            return [null, $params]; // This indicates that the query should not be run because we know that no results will be returned.
        }                           // NOTE: $params must be returned unchanged.

        // Determine work type and mark visibility.
        $filterWorkType = false;
        if ($this->viewer->getRole()->isStaff()) {
            $visibility = IAssessable::MARK_VISIBILITY_PRIVATE;
        } elseif ($this->viewer->getRole()->isStudent()) {
            $visibility     = IAssessable::MARK_VISIBILITY_STUDENT;
            $filterWorkType = true;
        } elseif ($this->viewer->getRole()->isParent()) {
            $visibility     = IAssessable::MARK_VISIBILITY_PARENT_STUDENT;
            $filterWorkType = true;
        } else {
            throw new UnexpectedValueException('Unexpected User: ' . $this->viewer->getId());
        }

        if ($countOnly) {
            $dueWorkFeedbackQuery = ' SELECT COUNT(*) ';
        } else {
            $dueWorkFeedbackQuery = (
                'SELECT
                 submission_return.id           AS id,
                 :discriminatorDueWorkFeedback  AS discriminator,
                 submission_return.published_at AS date '
            );
            $params['discriminatorDueWorkFeedback'] = self::DISCRIMINATOR_DUE_WORK_FEEDBACK;
        }

        $dueWorkFeedbackQuery .= (
            'FROM `submission_return`
             JOIN `submission_box` ON `submission_return`.`box_id` = `submission_box`.`id`
             JOIN `assessment` ON `assessment`.`id` = `submission_box`.`assessment_id`
             WHERE `review_route` = :reviewRoute
             AND `submission_return`.`published_at` IS NOT NULL
             AND `submission_return`.`owner` = :userIdDueWorkFeedback
             AND `submission_box`.`mark_visibility` >= :markVisibility
             AND `submission_return`.`deleted_at` IS NULL
             AND `assessment`.`deleted_at` IS NULL'
        );

        if ($filterWorkType) {
            $dueWorkFeedbackQuery .= (
                'AND (
                     `assessment`.`work_type_id` IS NULL
                     OR
                     `assessment`.`work_type_id`
                     IN (
                         SELECT `id` FROM `lu_work_type`
                         WHERE `staff_or_instructor_visible_only` = 0
                         AND `deleted_at` IS NULL
                     )
                 )'
            );
        }

        $params['userIdDueWorkFeedback'] = $this->viewee->getId();
        $params['markVisibility']        = $visibility;
        $params['reviewRoute']           = ReturnedWork::REVIEW_ROUTE_INSTRUCTOR;

        return [$dueWorkFeedbackQuery, $params];
    }

    /**
     * Returns query to get evidence.
     * @param array $params
     * @param array $filters
     * @param bool  $countOnly
     * @return array
     */
    private function getEvidenceQuery(array $params, array $filters, bool $countOnly): array
    {
        // Only include if $this->filters is empty or 'evidence' is specified in at least one filter.
        $includeEvidence = (count($filters) === 0);
        foreach ($filters as $filter) {
            if ($filter['feedItemType'] === 'evidence') {
                $includeEvidence = true;
            }
        }

        if (!$includeEvidence) {
            return [null, $params]; // This indicates that the query should not be run because we know that no results will be returned.
        }                           // NOTE: $params must be returned unchanged.

        if ($countOnly) {
            $evidenceQuery = ' SELECT COUNT(*) ';
        } else {
            $evidenceQuery = (
                'SELECT
                 e.id                   AS id,
                 :discriminatorEvidence AS discriminator,
                 e.created_at           AS date '
            );
            $params['discriminatorEvidence'] = self::DISCRIMINATOR_EVIDENCE;
        }

        $sqlWhereCondition = 'TRUE';

        foreach ($filters as $filter) {
            if ($filter['feedItemType'] === 'evidence' && array_key_exists('tags', $filter) && count($filter['tags']) > 0) {
                $sqlWhereCondition = 'luet.name IN (' . implode(', ', $this->quoteArrayOfStrings($filter['tags'])) . ')';
            }
        }

        $evidenceQuery .= (
            "FROM evidence        AS e
             JOIN evidence_tag    AS et   ON et.evidence_id=e.id
             JOIN lu_evidence_tag AS luet ON luet.id=et.tag_id
             WHERE e.user_id = :userIdEvidence
             AND e.deleted_at IS NULL
             AND $sqlWhereCondition"
        );

        $params['userIdEvidence'] = $this->viewee->getId();

        return [$evidenceQuery, $params];
    }

    /**
     * Returns query to get badgeAwards.
     * @param array $params
     * @param array $filters
     * @param bool  $countOnly
     * @return array
     */
    private function getBadgeAwardsQuery(array $params, array $filters, bool $countOnly): array
    {
        // Only include if $this->filters is empty or 'badgeAwards' is specified in at least one filter.
        $includeBadgeAwards = (count($filters) === 0);
        foreach ($filters as $filter) {
            if ($filter['feedItemType'] === 'badgeAward') {
                $includeBadgeAwards = true;
            }
        }

        if (!$includeBadgeAwards) {
            return [null, $params]; // This indicates that the query should not be run because we know that no results will be returned.
        }                           // NOTE: $params must be returned unchanged.

        if ($countOnly) {
            $badgeAwardsQuery = ' SELECT COUNT(*) ';
        } else {
            $badgeAwardsQuery = (
                'SELECT
                 id                       AS id,
                 :discriminatorBadgeAward AS discriminator,
                 awarded_at               AS date '
            );
            $params['discriminatorBadgeAward'] = self::DISCRIMINATOR_BADGE_AWARD;
        }

        $filterOrClauses = [];

        foreach ($filters as $filter) {
            if ($filter['feedItemType'] === 'badgeAward') {
                switch ($filter['badgeStatus']) {
                    case 'achievement': $filterOrClauses[] = "status = 'achievement'"; break;
                    case 'goal':        $filterOrClauses[] = "status = 'goal'"; break;
                    default: // Do nothing.
                }
            }
        }

        $sqlWhereCondition = (
            (count($filterOrClauses) === 0)? 'TRUE': '(' . implode(' OR ', $filterOrClauses) . ')'
        );

        $badgeAwardsQuery .= (
            "FROM badge_user
             WHERE $sqlWhereCondition
             AND recipient_id = :userIdBadgeAward AND deleted_at IS NULL"
        );

        $params['userIdBadgeAward'] = $this->viewee->getId();

        if ($this->expiryDate) {
            $badgeAwardsQuery .= ' AND (expires_at IS NULL OR expires_at >= :expiryBadgeAward)';
            $params['expiryBadgeAward'] = $this->expiryDate->getDatabaseDate();
        }

        return [$badgeAwardsQuery, $params];
    }

    /**
     * @param array $strings
     * @return array
     */
    private function quoteArrayOfStrings(array $strings): array
    {
        $stringsQuoted = [];

        foreach ($strings as $string) {
            $stringsQuoted[] = $this->database->legacyQuote($string);
        }

        return $stringsQuoted;
    }
}

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
use InvalidArgumentException;
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
 * We need the date, the type of object, and the object's ID.  For more info see the comments in the ICursor class.
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
     * Discriminator value for identifying that a retrieved result points to a
     * quiz feedback.
     */
    public const DISCRIMINATOR_QUIZ_FEEDBACK = 'quizFeedback';


    /**
     * This is the set of feed item types that we expect to receive from the client.
     *
     * NOTE: There is a matching array in library/Schoolbox/Learning/Moment/Evidence/vue/feed.vue.
     *       If any change is made to this array, a similar change should be made to that array.
     */
    private const FILTER_NAMES = ['goals', 'learningMoments', 'achievements', 'feedback'];

    /**
     * Whether the learning moments module is enabled
     */
    private bool $learningMomentsEnabled;

    /**
     * Whether the badgeAwards module is enabled
     */
    private bool $badgeAwardsEnabled;

    private bool $gradesEnabled;

    private array $filter;

    /**
     * Constructor
     *
     * @param IConnector               $database
     * @param User                     $viewee
     * @param User                     $viewer
     * @param Alaress_Date_Format|null $expiryDate     If provided: don't fetch any badge awards which expire before this date.
     * @param int                      $limit          The maximum number of results to fetch in one query.
     * @param IConfigLoader            $config
     * @param ICodec|null              $codec          The codec for encoding/decoding a cursor.
     * @param string|null              $encodedFilter  Base64 encoded JSON encoded Javascript array.
     */
    public function __construct(
        private IConnector $database,
        private User $viewee,
        private User $viewer,
        ?string $encodedFilter,
        private ?Alaress_Date_Format $expiryDate,
        private int $limit,
        IConfigLoader $config,
        private ?ICodec $codec = null
    ) {
        $this->codec = $codec ?? new Base64();
        $this->filter = (
            ($encodedFilter === null) ? [
                'filterNames' => [],
                'tag' => ''
            ] :
            $this->codec->decode($encodedFilter)
        );

        self::validateFilter($this->filter);

        $this->learningMomentsEnabled = $config->get(ConfigMapper::NAME_LEARNING_MOMENTS_ENABLED)
            && $viewee->getLearningMomentsEnabled()
            && (!$viewer->getRole()->isStaff() || $viewer->getRole()->getAccessEvidence());
        $this->badgeAwardsEnabled     = $config->get(ConfigMapper::NAME_BADGES_ENABLED);
        $this->gradesEnabled          = $config->get(ConfigMapper::NAME_GRADES_ENABLED)
            && $config->get(ConfigMapper::NAME_ACTIVITY_FEED_FEEDBACK_ENABLED)
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
     * @return null|string
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
        $count = 0;

        if ($this->learningMomentsEnabled) {
            [$evidenceCountQuery, $evidenceCountParams] = $this->getEvidenceQueryAndParams(countOnly: true);
            if ($evidenceCountQuery !== null) {
                $result = $this->database->executeQuery($evidenceCountQuery, $evidenceCountParams);
                $count += (int)$result->fetchColumn();
            }
        }

        if ($this->badgeAwardsEnabled) {
            [$badgeAwardsCountQuery, $badgeAwardsCountParams] = $this->getBadgeAwardsQueryAndParams(countOnly: true);
            if ($badgeAwardsCountQuery !== null) {
                $result = $this->database->executeQuery($badgeAwardsCountQuery, $badgeAwardsCountParams);
                $count += (int)$result->fetchColumn();
            }
        }

        if ($this->gradesEnabled) {
            [$dueWorkFeedbackCountQuery, $dueWorkFeedbackCountParams] = $this->getDueWorkFeedbackQueryAndParams(countOnly: true);
            if ($dueWorkFeedbackCountQuery !== null) {
                $result = $this->database->executeQuery($dueWorkFeedbackCountQuery, $dueWorkFeedbackCountParams);
                $count += (int)$result->fetchColumn();
            }

            [$quizFeedbackCountQuery, $quizFeedbackCountParams] = $this->getQuizFeedbackQueryAndParams(countOnly: true);
            if ($quizFeedbackCountQuery !== null) {
                $result = $this->database->executeQuery($quizFeedbackCountQuery, $quizFeedbackCountParams);
                $count += (int)$result->fetchColumn();
            }
        }

        return $count;
    }

    /**
     * @inheritdoc
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        $queriesToBeUnioned = [];
        $params = [];

        if ($this->learningMomentsEnabled) {
            [$evidenceQuery, $evidenceParams] = $this->getEvidenceQueryAndParams(countOnly: false);
            if ($evidenceQuery !== null) {
                $queriesToBeUnioned[] = $evidenceQuery;
                $params = array_merge($params, $evidenceParams);
            }
        }

        if ($this->badgeAwardsEnabled) {
            [$badgeAwardsQuery, $badgeAwardsParams] = $this->getBadgeAwardsQueryAndParams(countOnly: false);
            if ($badgeAwardsQuery !== null) {
                $queriesToBeUnioned[] = $badgeAwardsQuery;
                $params = array_merge($params, $badgeAwardsParams);
            }
        }

        if ($this->gradesEnabled) {
            [$dueWorkFeedbackQuery, $dueWorkFeedbackParams] = $this->getDueWorkFeedbackQueryAndParams(countOnly: false);
            if ($dueWorkFeedbackQuery !== null) {
                $queriesToBeUnioned[] = $dueWorkFeedbackQuery;
                $params = array_merge($params, $dueWorkFeedbackParams);
            }

            [$quizFeedbackQuery, $quizFeedbackParams] = $this->getQuizFeedbackQueryAndParams(countOnly: false);
            if ($quizFeedbackQuery !== null) {
                $queriesToBeUnioned[] = $quizFeedbackQuery;
                $params = array_merge($params, $quizFeedbackParams);
            }
        }

        if (count($queriesToBeUnioned) === 0) {
            return new Collection();
        }

        $wherePart = '';

        if ($encodedCursor !== null) {
            // the cursor contains 'date', 'id', 'discriminator' fields
            $cursor = $this->codec->decode($encodedCursor);
            if (isset($cursor['date']) && isset($cursor['id']) && isset($cursor['discriminator'])) {
                // The usual cursor where part:
                // fetch items from before the last item's date, as well as ones
                // which match the last item's date and whose other fields are less
                // than the current field
                $wherePart                = (
                    'WHERE
                     (date < :date1) OR
                     (date = :date2 AND discriminator > :discriminator2) OR
                     (date = :date3 AND discriminator = :discriminator3 AND id < :id3)'
                );
                $params['date1']          = $cursor['date'];
                $params['date2']          = $cursor['date'];
                $params['date3']          = $cursor['date'];
                $params['discriminator2'] = $cursor['discriminator'];
                $params['discriminator3'] = $cursor['discriminator'];
                $params['id3']            = $cursor['id'];
            }
        }

        $query = (
            'SELECT id, discriminator, date FROM (
                 ' . implode(' UNION ALL ', $queriesToBeUnioned) . "
             ) AS combined
             $wherePart
             ORDER BY date desc, discriminator asc, id desc
             LIMIT :limit"
        );

        // Don't need to know the details of the badge:
        // just what it points to
        // There's a possible issue in that `badge_user.badge_id` is not unique
        $params['limit'] = $this->limit;

        return new Collection($this->database->executeQuery($query, $params)->fetchAll());
    }

    /**
     * Returns query to get evidence.
     *
     * Notes:
     *  * $this->filter['tag'] is used to restrict the rows returned by the query to those linked to a matching tag.
     *
     * @param bool $countOnly - If true, return a query that fetches the number of rows that would have been returned had $countOnly been false.
     * @return array containing query and associated params.
     */
    private function getEvidenceQueryAndParams(bool $countOnly): array
    {
        $includeEvidence = (
            count($this->filter['filterNames']) === 0 ||
            in_array('learningMoments', $this->filter['filterNames'], true)
        );

        if (!$includeEvidence) {
            return [null, []]; // This indicates that the query should not be run because we know that no results will be returned.
        }

        $query = 'SELECT ';
        $params = [];

        if ($countOnly) {
            $query .= 'COUNT(*) ';
        } else {
            $query .= (
                'e.id                   AS id,
                 :discriminatorEvidence AS discriminator,
                 e.created_at           AS date '
            );
            $params['discriminatorEvidence'] = self::DISCRIMINATOR_EVIDENCE;
        }

        $sqlWhereCondition = 'TRUE';

        if (trim($this->filter['tag']) !== '') {
            $sqlWhereCondition = (
                'EXISTS(
                     SELECT *
                     FROM evidence_tag    AS et
                     JOIN lu_evidence_tag AS luet ON luet.id = et.tag_id
                     WHERE et.evidence_id = e.id
                     AND luet.name = :tagEvidence
                 )'
            );
            $params['tagEvidence'] = $this->filter['tag'];
        }

        $query .= (
            "FROM evidence AS e
             WHERE e.user_id = :userIdEvidence
             AND e.deleted_at IS NULL
             AND $sqlWhereCondition"
        );
        $params['userIdEvidence'] = $this->viewee->getId();

        return [$query, $params];
    }

    /**
     * Returns query to get badgeAwards.
     * @param bool  $countOnly
     * @return array containing query and associated params.
     */
    private function getBadgeAwardsQueryAndParams(bool $countOnly): array
    {
        $includeAchievements = (
            count($this->filter['filterNames']) === 0 ||
            in_array('achievements', $this->filter['filterNames'], true) // Achievements are badges with status 'achievement'.
        );
        $includeGoals = (
            count($this->filter['filterNames']) === 0 ||
            in_array('goals', $this->filter['filterNames'], true) // Goals are badges with status 'goal'.
        );

        if (
            !$includeAchievements && !$includeGoals ||
            trim($this->filter['tag']) !== '' // Badges cannot have tags, so if a tag filter exists, return no results.
        ) {
            return [null, []]; // This indicates that the query should not be run because we know that no results will be returned.
        }

        $query = 'SELECT ';
        $params = [];

        if ($countOnly) {
            $query .= 'COUNT(*) ';
        } else {
            $query .= (
                'bu.id                    AS id,
                 :discriminatorBadgeAward AS discriminator,
                 awarded_at               AS date '
            );
            $params['discriminatorBadgeAward'] = self::DISCRIMINATOR_BADGE_AWARD;
        }

        $filterOrClauses = [];
        if ($includeAchievements) {
            $filterOrClauses[] = "status = 'achievement'";
        }
        if ($includeGoals) {
            $filterOrClauses[] = "status = 'goal'";
        }

        $query .= (
            'FROM badge_user AS bu
             JOIN badge      AS b ON (b.id = bu.badge_id)
             WHERE (' . implode(' OR ', $filterOrClauses) . ')
             AND bu.recipient_id = :userIdBadgeAward
             AND bu.deleted_at IS NULL
             AND b.deleted_at IS NULL' // Protect against situation where badge rows are deleted, but child badge_user
        );                             // rows are not.  Child rows of deleted parent rows should always be deleted.
                                       // But this rule is not enforced by the database, we must handle this case.
        $params['userIdBadgeAward'] = $this->viewee->getId();

        if ($this->expiryDate) {
            $query .= 'AND (expires_at IS NULL OR expires_at >= :expiryBadgeAward)';
            $params['expiryBadgeAward'] = $this->expiryDate->getDatabaseDate();
        }

        return [$query, $params];
    }

    /**
     * Returns query to get due work feedback.
     * @param bool  $countOnly
     * @return array containing query and associated params.
     */
    private function getDueWorkFeedbackQueryAndParams(bool $countOnly): array
    {
        $includeDueWorkFeedback = (
            count($this->filter['filterNames']) === 0 ||
            in_array('feedback', $this->filter['filterNames'], true)
        );

        if (
            !$includeDueWorkFeedback ||
            trim($this->filter['tag']) !== '' // Due work feedback cannot have tags, so if a tag filter exists, return no results.
        ) {
            return [null, []]; // This indicates that the query should not be run because we know that no results will be returned.
        }

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

        $query = 'SELECT ';
        $params = [];

        if ($countOnly) {
            $query .= 'COUNT(*) ';
        } else {
            $query .= (
                'submission_return.id           AS id,
                 :discriminatorDueWorkFeedback  AS discriminator,
                 submission_return.published_at AS date '
            );
            $params['discriminatorDueWorkFeedback'] = self::DISCRIMINATOR_DUE_WORK_FEEDBACK;
        }

        $query .= (
            'FROM `submission_return`
             JOIN `submission_box` ON `submission_return`.`box_id` = `submission_box`.`id`
             JOIN `assessment` ON `assessment`.`id` = `submission_box`.`assessment_id`
             WHERE `review_route` = :reviewRoute
             AND `submission_return`.`published_at` IS NOT NULL
             AND `submission_return`.`owner` = :userIdDueWorkFeedback
             AND `submission_box`.`mark_visibility` >= :markVisibility
             AND `submission_return`.`deleted_at` IS NULL
             AND `assessment`.`deleted_at` IS NULL '
        );
        $params['userIdDueWorkFeedback'] = $this->viewee->getId();
        $params['markVisibility']        = $visibility;
        $params['reviewRoute']           = ReturnedWork::REVIEW_ROUTE_INSTRUCTOR;

        if ($filterWorkType) {
            $query .= (
                'AND (
                    assessment.work_type_id IS NULL OR
                    assessment.work_type_id IN (
                        SELECT `id` FROM `lu_work_type`
                        WHERE `staff_or_instructor_visible_only` = 0
                        AND `deleted_at` IS NULL
                    )
                )'
            );
        }

        return [$query, $params];
    }


    /**
     * Returns query to get quiz feedback.
     *
     * @param bool $countOnly
     * @return array containing query and associated params.
     * @throws UnexpectedValueException
     */
    private function getQuizFeedbackQueryAndParams(bool $countOnly): array
    {
        $includeQuizFeedback = (
            count($this->filter['filterNames']) === 0 ||
            in_array('feedback', $this->filter['filterNames'], true)
        );

        if (
            !$includeQuizFeedback ||
            trim($this->filter['tag']) !== '' // Quiz feedback cannot have tags, so if a tag filter exists, return no results.
        ) {
            return [null, []]; // This indicates that the query should not be run because we know that no results will be returned.
        }

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

        $query = 'SELECT ';
        $params = [];

        if ($countOnly) {
            $query .= 'COUNT(*) ';
        } else {
            $query .= ("
                assess_attempt.attempt_id      AS id,
                :discriminatorQuizFeedback AS discriminator,
                assess_attempt.reviewed_at AS date
            ");

            $params['discriminatorQuizFeedback'] = self::DISCRIMINATOR_QUIZ_FEEDBACK;
        }

        $query .= ("
            FROM `assess_attempt`
            JOIN `assess` ON `assess_attempt`.`assess_id` = `assess`.`assess_id`
            JOIN `assessment` ON `assessment`.`id` = `assess`.`assessment_id`
            WHERE `assess_attempt`.`reviewed_at` IS NOT NULL
            AND `assess_attempt`.`user_id` = :userIdQuizFeedback
            AND `assess`.`mark_visibility` >= :markVisibility
            AND `assess_attempt`.`deleted_at` IS NULL
            AND `assessment`.`deleted_at` IS NULL
        ");
        $params['userIdQuizFeedback'] = $this->viewee->getId();
        $params['markVisibility']     = $visibility;

        if ($filterWorkType) {
            $query .= ("
                AND (
                    assessment.work_type_id IS NULL OR
                    assessment.work_type_id IN (
                        SELECT `id` FROM `lu_work_type`
                        WHERE `staff_or_instructor_visible_only` = 0
                        AND `deleted_at` IS NULL
                    )
                )
            ");
        }

        return [$query, $params];
    }


    /**
     * Validate that parameter 'filter' meets the API spec: https://api.schoolbox.com.au/#post-/api/register/-provider-
     *
     * @param array $filter
     * @return void
     */
    private static function validateFilter(array $filter): void
    {
        if (!array_key_exists('filterNames', $filter) || !is_array($filter['filterNames'])) {
            throw new InvalidArgumentException("Filter object must contain key 'filterNames'.");
        }

        foreach ($filter['filterNames'] as $filterName) {
            if (!in_array($filterName, self::FILTER_NAMES, true)) {
                throw new InvalidArgumentException(
                    "Filter 'filterNames' array should only contain strings from" .
                    ' {' . implode(', ', self::FILTER_NAMES) . '}'
                );
            }
        }

        if (!array_key_exists('tag', $filter) || !is_string($filter['tag'])) {
            throw new InvalidArgumentException("Filter object must contain 'tag'.");
        }
    }
}

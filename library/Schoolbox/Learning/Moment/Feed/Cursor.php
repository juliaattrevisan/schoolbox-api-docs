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

/**
 * Feed for a user's learning moment feed.
 *
 * This combines evidence and badges into the one cursored result.
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
     * Whether the badges module is enabled
     */
    private bool $badgesEnabled;

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
     */
    public function __construct(
        private IConnector $database,
        private User $viewee,
        private User $viewer,
        private ?Alaress_Date_Format $expiryDate,
        private int $limit,
        IConfigLoader $config,
        private ?ICodec $codec = null
    ) {
        $this->codec                  = $codec ?? new Base64();
        $this->learningMomentsEnabled = $config->get(ConfigMapper::NAME_LEARNING_MOMENTS_ENABLED)
            && $viewee->getLearningMomentsEnabled()
            && (!$viewer->getRole()->isStaff() || $viewer->getRole()->getAccessEvidence());
        $this->badgesEnabled          = $config->get(ConfigMapper::NAME_BADGES_ENABLED);
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
            $countQueries[] = $this->database->executeQuery(
                "SELECT COUNT(*) FROM evidence WHERE user_id = ? AND deleted_at IS NULL",
                [$this->viewee->getId()]
            );
        }
        if ($this->badgesEnabled) {
            $badgeCountQuery  = "SELECT COUNT(*) FROM badge_user WHERE recipient_id = ? AND deleted_at IS NULL";
            $badgeCountParams = [$this->viewee->getId()];
            if ($this->expiryDate) {
                $badgeCountQuery    .= ' AND (expires_at IS NULL OR expires_at >= ?)';
                $badgeCountParams[] = $this->expiryDate->getDatabaseDate();
            }
            $countQueries[] = $this->database->executeQuery($badgeCountQuery, $badgeCountParams);
        }
        if ($this->gradesEnabled) {
            [$dueWorkFeedbackCountQuery, $dueWorkFeedbackCountParams] = $this->getDueWorkFeedbackQuery([], countOnly: true);
            $countQueries[] = $this->database->executeQuery($dueWorkFeedbackCountQuery, $dueWorkFeedbackCountParams);
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
        $params    = [
            'limit' => $this->limit,
        ];
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
            $queries[]                       = '
                SELECT
                    id,
                    :discriminatorEvidence AS discriminator,
                    created_at             AS date
                FROM evidence
                WHERE user_id = :userIdEvidence AND deleted_at IS NULL
            ';
            $params['discriminatorEvidence'] = self::DISCRIMINATOR_EVIDENCE;
            $params['userIdEvidence']        = $this->viewee->getId();
        }
        if ($this->badgesEnabled) {
            $badgeQuery                        = '
                SELECT
                    id                       AS id,
                    :discriminatorBadgeAward AS discriminator,
                    awarded_at               AS date
                FROM badge_user
                WHERE recipient_id = :userIdBadgeAward AND deleted_at IS NULL
            ';
            $params['discriminatorBadgeAward'] = self::DISCRIMINATOR_BADGE_AWARD;
            $params['userIdBadgeAward']        = $this->viewee->getId();

            if ($this->expiryDate) {
                $badgeQuery                 .= ' AND (expires_at IS NULL OR expires_at >= :expiryBadgeAward)';
                $params['expiryBadgeAward'] = $this->expiryDate->getDatabaseDate();
            }
            $queries[] = $badgeQuery;
        }
        if ($this->gradesEnabled) {
            [$dueWorkFeedbackQuery, $params] = $this->getDueWorkFeedbackQuery($params, countOnly: false);
            $queries[] = $dueWorkFeedbackQuery;
        }

        if (count($queries) === 0) {
            return new Collection();
        }

        $query =
            'SELECT id, discriminator, date FROM (' . implode(' UNION ALL ', $queries) . "
            ) AS combined

            {$wherePart}
            ORDER BY date desc, discriminator asc, id desc
            LIMIT :limit
        ";

        return new Collection(
            $this->database->executeQuery($query, $params)->fetchAll()
        );
    }


    /**
     * Returns query to get due work feedback
     * @param array $params
     * @param bool  $countOnly
     * @return array
     */
    private function getDueWorkFeedbackQuery(array $params, bool $countOnly): array
    {
        // check work type
        $filterWorkType = false;
        // check mark_visibility
        if ($this->viewer->getRole()->isStaff()) {
            $visibility = IAssessable::MARK_VISIBILITY_PRIVATE;
        } elseif ($this->viewer->getRole()->isStudent()) {
            $visibility     = IAssessable::MARK_VISIBILITY_STUDENT;
            $filterWorkType = true;
        } elseif ($this->viewer->getRole()->isParent()) {
            $visibility     = IAssessable::MARK_VISIBILITY_PARENT_STUDENT;
            $filterWorkType = true;
        } else {
            throw new \UnexpectedValueException('Unexpected User: ' . $this->viewer->getId());
        }

        if ($countOnly) {
            $dueWorkFeedbackQuery = ' SELECT COUNT(*) ';
        } else {
            $dueWorkFeedbackQuery                   = '
                SELECT `submission_return`.`id` AS id,
                       :discriminatorDueWorkFeedback AS discriminator,
                       `submission_return`.`published_at` AS date ';
            $params['discriminatorDueWorkFeedback'] = self::DISCRIMINATOR_DUE_WORK_FEEDBACK;
        }

        $dueWorkFeedbackQuery .= '
                FROM `submission_return`
                    JOIN `submission_box` ON `submission_return`.`box_id` = `submission_box`.`id`
                    JOIN `assessment` ON `assessment`.`id` = `submission_box`.`assessment_id`
                WHERE `review_route` = :reviewRoute
                  AND `submission_return`.`published_at` IS NOT NULL
                  AND `submission_return`.`owner` = :userIdDueWorkFeedback
                  AND `submission_box`.`mark_visibility` >= :markVisibility
                  AND `submission_return`.`deleted_at` IS NULL
                  AND `assessment`.`deleted_at` IS NULL
            ';

        if ($filterWorkType) {
            $dueWorkFeedbackQuery .= '
                AND (
                 `assessment`.`work_type_id` IS NULL
                 OR
                 `assessment`.`work_type_id`
                 IN (SELECT `id` FROM `lu_work_type`
                                WHERE `staff_or_instructor_visible_only` = 0
                                  AND `deleted_at` IS NULL)
                )';
        }

        $params['userIdDueWorkFeedback'] = $this->viewee->getId();
        $params['markVisibility']        = $visibility;
        $params['reviewRoute']           = ReturnedWork::REVIEW_ROUTE_INSTRUCTOR;
        return [$dueWorkFeedbackQuery, $params];
    }
}

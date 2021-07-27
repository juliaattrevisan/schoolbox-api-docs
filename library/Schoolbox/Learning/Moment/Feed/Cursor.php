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
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
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
     * A connection to a database from which the result is retrieved.
     */
    private IConnector $database;


    /**
     * A user to retrieve the learning moment feed for.
     *
     * Usually, but not always, a student.
     */
    private User $user;


    /**
     * If provided: don't fetch any badge awards which expire before this date.
     */
    private ?Alaress_Date_Format $expiryDate;


    /**
     * The maximum number of results to fetch in one query.
     */
    private int $limit;


    /**
     * The codec for encoding/decoding a cursor.
     */
    private ICodec $codec;


    /**
     * Whether the learning moments module is enabled
     */
    private bool $learningMomentsEnabled;


    /**
     * Whether the badges module is enabled
     */
    private bool $badgesEnabled;


    /**
     * Constructor
     *
     * @param IConnector               $database
     * @param User                     $viewee
     * @param User                     $viewer
     * @param Alaress_Date_Format|null $expiryDate
     * @param int                      $limit
     * @param IConfigLoader            $config
     * @param ICodec|null              $codec
     */
    public function __construct(
        IConnector $database,
        User $viewee,
        User $viewer,
        ?Alaress_Date_Format $expiryDate,
        int $limit,
        IConfigLoader $config,
        ?ICodec $codec = null
    ) {
        $this->database               = $database;
        $this->user                   = $viewee;
        $this->expiryDate             = $expiryDate;
        $this->limit                  = $limit;
        $this->codec                  = $codec ?? new Base64();
        $this->learningMomentsEnabled = $config->get(ConfigMapper::NAME_LEARNING_MOMENTS_ENABLED)
            && $viewee->getLearningMomentsEnabled()
            && (!$viewer->getRole()->isStaff() || $viewer->getRole()->getAccessEvidence());
        $this->badgesEnabled          = $config->get(ConfigMapper::NAME_BADGES_ENABLED);
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
                [$this->user->getId()]
            );
        }
        if ($this->badgesEnabled) {
            $badgeCountQuery  = "SELECT COUNT(*) FROM badge_user WHERE recipient_id = ? AND deleted_at IS NULL";
            $badgeCountParams = [$this->user->getId()];
            if ($this->expiryDate) {
                $badgeCountQuery    .= ' AND (expires_at IS NULL OR expires_at >= ?)';
                $badgeCountParams[] =  $this->expiryDate->getDatabaseDate();
            }
            $countQueries[] = $this->database->executeQuery($badgeCountQuery, $badgeCountParams);
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
            $params['userIdEvidence']        = $this->user->getId();
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
            $params['userIdBadgeAward']        = $this->user->getId();

            if ($this->expiryDate) {
                $badgeQuery                 .= ' AND (expires_at IS NULL OR expires_at >= :expiryBadgeAward)';
                $params['expiryBadgeAward'] =  $this->expiryDate->getDatabaseDate();
            }
            $queries[] = $badgeQuery;
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
}

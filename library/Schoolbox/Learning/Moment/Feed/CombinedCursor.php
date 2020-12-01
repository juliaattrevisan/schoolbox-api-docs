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
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\User;

/**
 * Feed for the student's learning moment feed.
 *
 * This combines evidence and badges into the one cursored result.
 */
class CombinedCursor implements ICursor
{
    private IConnector $database;


    private User $user;


    private int $limit;


    private ICodec $codec;


    public function __construct(
        IConnector $database,
        User $user,
        int $limit,
        ?ICodec $codec = null
    ) {
        $this->database = $database;
        $this->user     = $user;
        $this->limit    = $limit;
        $this->codec    = $codec ?? new Base64();
    }


    public function next(Collection $collection) : ?string
    {
        // As usual: if the collection is empty, there is no next
        if ($collection->count() === 0) {
            return null;
        }

        // Find the last item in the collection: get a pointer to that
        // Expect `$last` to be an array
        return $this->codec->encode($collection->last());
    }


    public function current(Collection $collection) : ?string
    {
        // Just return null for now
        return null;
    }


    public function retrieveResultCount() : ?int
    {
        $count        = 0;
        $countQueries = [
            $this->database->executeQuery(
            "SELECT COUNT(*) FROM evidence WHERE user_id = ? AND deleted_at IS NULL",
                [$this->user->getId()]
            ),
            $this->database->executeQuery(
            "SELECT COUNT(*) FROM badge_user WHERE recipient_id = ? AND deleted_at IS NULL",
                [$this->user->getId()]
            ),
        ];
        foreach ($countQueries as $countQuery) {
            $count += (int)$countQuery->fetchColumn();
        }
        return $count;
    }


    public function retrieveResults(?string $encodedCursor) : Collection
    {
        // Don't need to know the details of the badge:
        // just what it points to
        // There's a possible issue in that `badge_user.badge_id` is not unique
        $params = [
            'userIdEvidence' => $this->user->getId(),
            'userIdBadge'    => $this->user->getId(),
            'limit'          => $this->limit,
        ];
        $wherePart = '';
        if (isset($encodedCursor)) {
            // the cursor contains 'date', 'id', 'discriminator' fields
            $cursor                   = $this->codec->decode($encodedCursor);
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
        $query  = "
            SELECT id, discriminator, date FROM (
                SELECT
                    id,
                    'evidence' AS discriminator,
                    created_at AS date
                FROM evidence
                WHERE user_id = :userIdEvidence AND deleted_at IS NULL

                UNION ALL

                SELECT
                    badge_id   AS id,
                    'badge'    AS discriminator,
                    awarded_at AS date
                FROM badge_user
                WHERE recipient_id = :userIdBadge AND deleted_at IS NULL
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

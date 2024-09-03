<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Forum;

use Alaress_Date_Format;
use Schoolbox\Forum\Post;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;

/**
 * Index builder for forum threads and posts.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * This builder works with forum post objects.
     *
     * @param object $item
     * @return int|string|null
     */
    public function extractItemId(object $item): int|string|null
    {
        if ($item instanceof Post) {
            return $item->getId();
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, int|string|null $itemId): iterable
    {
        $queryString = "
            SELECT
                fp.postID as id,
                ft.title,
                fp.modified AS date,
                fp.userID AS created_by,
                ft.categoryID AS folder_id,
                fp.body AS fulltext_index
            FROM forumThread ft
            LEFT JOIN forumPost fp ON fp.threadID = ft.threadID
            WHERE fp.deleted = 'false'
            AND ft.deleted = 'false'
        ";
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND fp.modified >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND fp.modified <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding forum posts...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d forum posts', $query->rowCount()));
        foreach ($query as $row) {
            $index = $this->buildIndex(
                (string)$row['id'],
                $row['title'],
                Hydrator::BASE_TYPE,
                $row['date'],
                true,
                $row['created_by'],
                $row['folder_id'],
            );
            $index = $this->addFulltextIndex($index, $row['fulltext_index'], self::INDEX_STRATEGY_HTML);
            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, int|string|null $itemId): iterable
    {
        // Ignore deleted forum posts for incremental updates to the search_index table
        // (e.g. on runs such as 'php tools/search/buildIndex.php -p --from="-10 min"').
        // This is done because searching for and attempting to remove from the search_index table all deleted forum
        // posts is wasteful when we only need to remove ones deleted in the past 10 minutes.
        // An overnight full rebuild of the search_index table ensures that the search_index table will never be more
        // than a day out of date.
        if ($fromDate !== null || $toDate !== null) {
            return [];
        }

        // Forum posts or threads don't have deletion dates:
        // just delete all indices for deleted records regardless of date
        $queryString = "SELECT
            fp.postID as id
            FROM forumThread ft
            LEFT JOIN forumPost fp ON fp.threadID = ft.threadID
            WHERE fp.deleted = 'true' OR ft.deleted = 'true'
        ";
        $query = $this->connector->prepareQuery($queryString);
        $query->execute();
        $this->logger->info(sprintf('Deleting search indices for %d forum posts', $query->rowCount()));
        foreach ($query as $row) {
            yield $this->buildIndex(
                (string)$row['id'],
                null,
                Hydrator::BASE_TYPE,
                null,
                true,
                null,
                null,
            );
        }
    }
}

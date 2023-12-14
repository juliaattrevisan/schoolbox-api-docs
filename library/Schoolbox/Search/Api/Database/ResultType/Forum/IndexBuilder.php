<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Forum;

use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;

/**
 * Index builder for files.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * @param Alaress_Date_Format|null $toDate
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate): iterable
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
            $queryString        .= ' AND fp.modified >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString      .= ' AND fp.modified <= :toDate';
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

            // In the forum query param builder, this is a text index:
            // it's a fulltext index here, because having title+body as fulltext
            // indices is more in line with other searchables.
            $index = $this->addFulltextIndex($index, $row['title'], self::INDEX_STRATEGY_TEXT);
            $index = $this->addFulltextIndex($index, $row['fulltext_index'], self::INDEX_STRATEGY_HTML);
            yield $index;
        }
    }

    /**
     * @param Alaress_Date_Format|null $toDate
     * @inheritdoc
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate): iterable
    {
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

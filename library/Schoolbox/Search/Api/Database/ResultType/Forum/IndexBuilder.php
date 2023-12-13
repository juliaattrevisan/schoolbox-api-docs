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
     * @inheritdoc
     */
    public function buildUpdates(Alaress_Date_Format $date): iterable
    {
        $query = $this->connector->prepareQuery("
            SELECT
                fp.postID as id,
                ft.title,
                fp.modified AS date,
                fp.userID AS created_by,
                ft.categoryID AS folder_id,
                fp.body AS fulltext_index
            FROM forumThread ft
            LEFT JOIN forumPost fp ON fp.threadID = ft.threadID
            WHERE fp.modified >= :date
            AND fp.deleted = 'false'
            AND ft.deleted = 'false'
        ");
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
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
            );
            $index = $this->addFolder($index, $row['folder_id']);
            $index = $this->addFulltextIndex($index, $row['fulltext_index']);
            $index = $this->addTextIndex($index, $row['title']);
            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(Alaress_Date_Format $date): iterable
    {
        // Forum posts or threads don't have deletion dates:
        $query = "SELECT
            fp.postID as id
            FROM forumThread ft
            LEFT JOIN forumPost fp ON fp.threadID = ft.threadID
            WHERE fp.deleted = 'true' OR ft.deleted = 'true'
        ";
        return $this->connector->executeQuery($query)->fetchColumn();
    }
}

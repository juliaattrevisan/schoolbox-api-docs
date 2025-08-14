<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Forum;

use Alaress_Date_Format;
use Schoolbox\Forum\Post;
use Schoolbox\Forum\Thread;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;
use Schoolbox\Search\Api\Database\Index\BuildType;

/**
 * Index builder for forum threads and posts.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * This builder works with forum post objects, and threads on deletion.
     *
     * @param object    $subject
     * @param BuildType $buildType
     * @return bool
     */
    protected function accepts(object $subject, BuildType $buildType): bool
    {
        return match ($buildType) {
            BuildType::DELETE => $subject instanceof Post || $subject instanceof Thread,
            default           => $subject instanceof Post,
        };
    }

    /**
     * @inheritdoc
     */
    protected function augmentQueryParams(object $subject, BuildType $buildType, string $baseQuery, array $baseParams): array
    {
        if ($subject instanceof Post) {
            return [
                $baseQuery . ' AND fp.postID = :id',
                $baseParams + ['id' => $subject->getId()],
            ];
        }
        if ($subject instanceof Thread) {
            return [
                $baseQuery . ' AND ft.threadID = :id',
                $baseParams + ['id' => $subject->getId()],
            ];
        }
        return [$baseQuery, $baseParams];
    }

    /**
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
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
            WHERE fp.deleted_at IS NULL
            AND ft.deleted_at IS NULL
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
        if ($subject) {
            if (!$this->accepts($subject, BuildType::UPDATE)) {
                return;
            }
            [$queryString, $params] = $this->augmentQueryParams($subject, BuildType::UPDATE, $queryString, $params);
        }
        // Set max_statement_time at the query level to ensure no timeouts even if global max_statement_time is set.
        // The query will not work as expected without the CAST, even though a float value is supplied.
        $queryString = "SET STATEMENT max_statement_time = CAST(:maxStatementTime AS DECIMAL(4,4)) FOR\n$queryString";
        $params['maxStatementTime'] = BaseBuilder::QUERY_LEVEL_MAX_STATEMENT_TIME;
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding forum posts...');
        $t0 = microtime(true);
        $query->execute($params);
        $t1 = microtime(true);
        $this->logger->debug('Done.  Time taken: ' . round($t1 - $t0, 4) . ' seconds.');
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
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        // Select all search_index rows linked to deleted forum posts.
        $queryString = '
            SELECT si.identifier, si.type
            FROM search_index si
            JOIN forumPost fp ON si.identifier = fp.postID
            WHERE search_index.type = "forum"
            AND fp.deleted_at IS NOT NULL
        ';
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND fp.deleted_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND fp.deleted_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            if (!$this->accepts($subject, BuildType::DELETE)) {
                return;
            }
            [$queryString, $params] = $this->augmentQueryParams($subject, BuildType::DELETE, $queryString, $params);
        }
        $query = $this->connector->prepareQuery($queryString, $params);
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

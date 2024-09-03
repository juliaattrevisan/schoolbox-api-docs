<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\News;

use Alaress_Date_Format;
use Schoolbox\Comms\News\Model;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;

/**
 * Index builder for news.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * This builder works with news article objects.
     *
     * @param object $subject
     * @return int|string|null
     */
    public function extractItemId(object $subject): int|string|null
    {
        if ($subject instanceof Model) {
            return $subject->getId();
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        // Include all articles that are currently published.
        $queryString = '
            SELECT
                n.id,
                n.title,
                n.publish_at AS date,
                n.author_id AS created_by,
                n.body AS fulltext_index1,
                n.blurb AS fulltext_index2
            FROM comms_news_article n
            WHERE ' . $this->getSqlConditionForCurrentlyPublished() . '
        ';
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND n.updated_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND n.updated_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding news articles...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d news articles', $query->rowCount()));
        foreach ($query as $row) {
            $index = $this->buildIndex(
                (string)$row['id'],
                $row['title'],
                Hydrator::BASE_TYPE,
                $row['date'],
                true,
                $row['created_by'],
                null,
            );
            $index = $this->addFulltextIndex($index, $row['fulltext_index1'], self::INDEX_STRATEGY_HTML);
            $index = $this->addFulltextIndex($index, $row['fulltext_index2'], self::INDEX_STRATEGY_HTML);
            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        // Remove all articles that are not currently published.
        $queryString = 'SELECT
            n.id
            FROM comms_news_article n
            WHERE NOT ' . $this->getSqlConditionForCurrentlyPublished() . '
        ';
        $params      = [];
        if ($fromDate) {
            $queryString .= ' AND n.deleted_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND n.deleted_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        $query = $this->connector->prepareQuery($queryString);
        $query->execute($params);
        $this->logger->info(sprintf('Deleting search indices for %d news articles', $query->rowCount()));
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

    /**
     * Return an SQL condition that will be true for news articles that are currently published,
     * and false for those that are not.
     * See News\Mapper->getPredicatePublished().
     *
     * Notes:
     *  - this function assumes that the comms_news_article database table can be referenced using alias 'n'.
     *  - this function ignores expired_at because archived news articles should be searchable.
     *
     * @returns string
     */
    private function getSqlConditionForCurrentlyPublished(): string
    {
        return (
            "(
                n.submitted_at IS NOT NULL AND
                n.rejected_at IS NULL AND
                n.accepted_at IS NOT NULL AND
                (n.publish_at IS NOT NULL AND n.publish_at <= NOW()) AND
                n.deleted_at IS NULL
            )"
        );
    }
}

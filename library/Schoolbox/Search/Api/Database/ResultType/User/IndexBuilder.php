<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\User;

use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;
use Schoolbox\User;

/**
 * Index builder for users.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * This builder works with user objects.
     *
     * @param object $subject
     * @return int|string|null
     */
    public function extractItemId(object $subject): int|string|null
    {
        if ($subject instanceof User) {
            return $subject->getId();
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        $queryString = "
            SELECT
                u.id,
                u.updated_at,
                u.username,
                u.firstname,
                u.preferred_name,
                u.lastname,
                CONCAT_WS(' ', u.firstname, u.lastname) AS nameFormat1,
                CONCAT_WS(' ', u.title, u.firstname, u.lastname) AS nameFormat2,
                CONCAT_WS(' ', u.title, u.preferred_name, u.lastname) AS nameFormat3,
                CONCAT_WS(' ', u.preferred_name, u.lastname) AS nameFormat4,
                CONCAT_WS(' ', u.title, u.lastname) AS nameFormat5,
                CONCAT('" . Hydrator::BASE_TYPE . ".', r.group_type) AS type
            FROM `user` u
            LEFT JOIN `groups` AS r ON u.group_id = r.id
            WHERE u.deleted_at IS NULL
        ";
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND u.updated_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND u.updated_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            $itemId = $this->extractItemId($subject);
            if (!isset($itemId)) {
                return;
            }
            $queryString .= ' AND u.id = :id';
            $params['id'] = $itemId;
        }
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding users...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d users', $query->rowCount()));
        foreach ($query as $row) {
            $index = $this->buildIndex(
                (string)$row['id'],
                $row['nameFormat1'],
                $row['type'],
                $row['updated_at'],
                false,
                null,
                null,
            );
            $index = $this->addFulltextIndex($index, $row['username'], self::INDEX_STRATEGY_TEXT);
            $index = $this->addFulltextIndex($index, $row['firstname'], self::INDEX_STRATEGY_TEXT);
            $index = $this->addFulltextIndex($index, $row['preferred_name'], self::INDEX_STRATEGY_TEXT);
            $index = $this->addFulltextIndex($index, $row['lastname'], self::INDEX_STRATEGY_TEXT);
            $index = $this->addFulltextIndex($index, $row['nameFormat2'], self::INDEX_STRATEGY_TEXT);
            $index = $this->addFulltextIndex($index, $row['nameFormat3'], self::INDEX_STRATEGY_TEXT);
            $index = $this->addFulltextIndex($index, $row['nameFormat4'], self::INDEX_STRATEGY_TEXT);
            $index = $this->addFulltextIndex($index, $row['nameFormat5'], self::INDEX_STRATEGY_TEXT);
            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        $queryString = "SELECT
            u.id,
            CONCAT('" . Hydrator::BASE_TYPE . ".', r.group_type) AS type
            FROM user u
            LEFT JOIN `groups` AS r ON u.group_id = r.id
            WHERE u.deleted_at IS NOT NULL
        ";
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND u.deleted_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND u.deleted_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            $itemId = $this->extractItemId($subject);
            if (!isset($itemId)) {
                return;
            }
            $queryString .= ' AND u.id = :id';
            $params['id'] = $itemId;
        }
        $query = $this->connector->prepareQuery($queryString);
        $query->execute($params);
        $this->logger->info(sprintf('Deleting search indices for %d users', $query->rowCount()));
        foreach ($query as $row) {
            yield $this->buildIndex(
                (string)$row['id'],
                null,
                $row['type'],
                null,
                false,
                null,
                null,
            );
        }
    }
}

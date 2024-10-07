<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\IStatement;
use Alaress_Date_Format;
use Schoolbox\Search\Index;

/**
 * Helper class for persisting search index updates for a single subject.
 */
class Persistor
{
    /**
     * SQL for updating the search index.
     */
    private const UPDATE_SQL = <<<SQL
        INSERT INTO search_index (
            type,
            identifier,
            title,
            date,
            is_relevance_date_affected,
            created_by,
            folder_id,
            fulltext_index
        ) VALUES (
            :type,
            :identifier,
            :title,
            :date,
            :isRelevanceDateAffected,
            :createdBy,
            :folderId,
            :fulltextIndex
        ) ON DUPLICATE KEY UPDATE
            title                      = :title,
            date                       = :date,
            is_relevance_date_affected = :isRelevanceDateAffected,
            created_by                 = :createdBy,
            folder_id                  = :folderId,
            fulltext_index             = :fulltextIndex
    SQL;

    /**
     * SQL for deleting a result from the search index.
     */
    private const DELETE_SQL = <<<SQL
        DELETE FROM search_index
        WHERE type = :type
        AND identifier = :identifier
    SQL;

    /**
     * SQL for clearing all indices from the search index.
     *
     * Note from original implementation:
     * > This used to TRUNCATE instead of DELETE:
     * > whilst TRUNCATE should be faster, it is susceptible to
     * > crashing the database server for reasons I haven't
     * > been able to determine.
     * > It must acquire a lock, as it deletes and recreates the
     * > table: this lock isn't always acquirable.
     */
    private const CLEAR_ALL_INDICES_SQL = 'DELETE FROM search_index';

    /**
     * SQL for clearing indices of a given type from the search index.
     */
    private const CLEAR_INDICES_OF_TYPE_SQL = 'DELETE FROM search_index WHERE type = :type OR type LIKE :typeLike';

    /**
     * Query for updating the search index, only created on demand.
     */
    private ?IStatement $updateQuery = null;

    /**
     * Query for deleting the search index, only created on demand.
     */
    private ?IStatement $deleteQuery = null;

    /**
     * Query for clearing all indices, only created on demand.
     */
    private ?IStatement $clearAllIndicesQuery = null;

    /**
     * Query for clearing indices of type, only created on demand.
     */
    private ?IStatement $clearIndicesOfTypeQuery = null;

    /**
     * Constructor
     *
     * @param IBuilder   $builder      The search index builder: used for building search indices for the subject.
     * @param IConnector $dbConnection Database connection: used for persisting search indices.
     */
    public function __construct(private IBuilder $builder, private IConnector $dbConnection)
    {
    }

    /**
     * Build indices to update.
     *
     * @see IBuilder::buildUpdates()
     *
     * @param Alaress_Date_Format|null $fromDate
     * @param Alaress_Date_Format|null $toDate
     * @param object|null              $subject
     * @return iterable
     */
    public function buildUpdates(
        ?Alaress_Date_Format $fromDate,
        ?Alaress_Date_Format $toDate,
        ?object $subject,
    ): iterable {
        return $this->builder->buildUpdates($fromDate, $toDate, $subject);
    }

    /**
     * Build indices to update.
     *
     * @see IBuilder::buildDeletes()
     *
     * @param Alaress_Date_Format|null $fromDate
     * @param Alaress_Date_Format|null $toDate
     * @param object|null              $subject
     * @return iterable
     */
    public function buildDeletes(
        ?Alaress_Date_Format $fromDate,
        ?Alaress_Date_Format $toDate,
        ?object $subject,
    ): iterable {
        return $this->builder->buildDeletes($fromDate, $toDate, $subject);
    }

    /**
     * Persist changes to the search index for `$subject`.
     *
     * This persists updates to the index first, then deletions.
     *
     * @param object $subject The object to persist updates for.
     * @return void
     */
    public function persist(object $subject): void
    {
        foreach ($this->builder->buildUpdates(null, null, $subject) as $index) {
            $this->updateIndex($index);
        }
        foreach ($this->builder->buildDeletes(null, null, $subject) as $index) {
            $this->deleteIndex($index);
        }
    }

    /**
     * Updates an index created by `$this->builder`.
     *
     * Mapper saves will error if the index already exists:
     * thus, use a direct `REPLACE` query.
     *
     * @param Index\Model $index
     * @return void
     */
    public function updateIndex(Index\Model $index): void
    {
        $this->updateQuery ??= $this->dbConnection->prepareQuery(self::UPDATE_SQL);
        $this->updateQuery->execute([
            'type'                    => $index->getType(),
            'identifier'              => $index->getIdentifier(),
            'title'                   => $index->getTitle(),
            'date'                    => $index->getDate()?->getDatabase(),
            'isRelevanceDateAffected' => $index->getIsRelevanceDateAffected() ? 1 : 0,
            'createdBy'               => $index->getCreatedBy(),
            'folderId'                => $index->getFolderId(),
            'fulltextIndex'           => $index->getFulltextIndex(),
        ]);
    }

    /**
     * Deletes an index created by `$this->builder`.
     *
     * @param Index\Model $index
     * @return void
     */
    public function deleteIndex(Index\Model $index): void
    {
        $this->deleteQuery ??= $this->dbConnection->prepareQuery(self::DELETE_SQL);
        $this->deleteQuery->execute([
            'identifier' => $index->getIdentifier(),
            'type'       => $index->getType(),
        ]);
    }

    /**
     * Clear all indices from the search index.
     *
     * @return void
     */
    public function clearAllIndices(): void
    {
        $this->clearAllIndicesQuery ??= $this->dbConnection->prepareQuery(self::CLEAR_ALL_INDICES_SQL);
        $this->clearAllIndicesQuery->execute();
    }

    /**
     * Clear all indices of a given type from the search index.
     *
     * An index's type may either be:
     * * an exact match for `$type`
     * * a value which is prefixed with `$type . '.'`
     *
     * @param string $type
     * @return void
     */
    public function clearIndicesOfType(string $type): void
    {
        $this->clearIndicesOfTypeQuery ??= $this->dbConnection->prepareQuery(self::CLEAR_INDICES_OF_TYPE_SQL);
        $this->clearIndicesOfTypeQuery->execute([
            'type'     => $type,
            'typeLike' => $this->dbConnection->getQuoter()->escapeForLike($type . '.') . '%',
        ]);
    }
}

<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\IStatement;
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
        REPLACE INTO search_index (
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
        )
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
     * Query for updating the search index, only created on demand.
     */
    private ?IStatement $updateQuery = null;

    /**
     * Query for deleting the search index, only created on demand.
     */
    private ?IStatement $deleteQuery = null;

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
     * Persist changes to the search index for `$subject`.
     *
     * This persists updates to the index first, then deletions.
     *
     * @param object $subject The object to persist updates for.
     * @return void
     * @throws \Schoolbox\Mapper\Exception
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
    private function updateIndex(Index\Model $index): void
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
    private function deleteIndex(Index\Model $index): void
    {
        $this->deleteQuery ??= $this->dbConnection->prepareQuery(self::DELETE_SQL);
        $this->deleteQuery->execute([
            'identifier' => $index->getIdentifier(),
            'type'       => $index->getType(),
        ]);
    }
}

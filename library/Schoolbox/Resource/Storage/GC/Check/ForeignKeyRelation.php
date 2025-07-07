<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GC\Check;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query\IQuery;
use Psr\Log\LoggerInterface;

/**
 * Check for whether a hash is found in any field which is a foreign key onto
 * the `storage` table.
 */
class ForeignKeyRelation implements Check
{
    private const SELECT_FK_RELATIONS_QUERY = <<<SQL
        select
            fks.TABLE_NAME             as foreign_table,
            kcu.COLUMN_NAME            as foreign_column,
            fks.REFERENCED_TABLE_NAME  as primary_table,
            kcu.REFERENCED_COLUMN_NAME as primary_column
        from information_schema.REFERENTIAL_CONSTRAINTS fks
        inner join information_schema.KEY_COLUMN_USAGE kcu
            on  fks.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
            and fks.TABLE_NAME        = kcu.TABLE_NAME
            and fks.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
        where fks.CONSTRAINT_SCHEMA        = :schema
          and fks.UNIQUE_CONSTRAINT_SCHEMA = :schema
          and fks.REFERENCED_TABLE_NAME    = :table
        order by fks.TABLE_NAME, kcu.COLUMN_NAME
        SQL;

    /**
     * Constructor
     *
     * @param IConnector      $db     Database connection
     * @param string          $schema The database schema which `$db` uses: required for building list of foreign keys.
     * @param string          $table  The table which stores storage records.
     * @param LoggerInterface $logger Logger
     */
    public function __construct(
        private IConnector $db,
        private string $schema,
        private string $table,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Filter out hashes from `$previous` which are referenced in any foreign
     * key relation.
     *
     * @param array<string> $previous
     * @return array<string>
     */
    public function getStale(array $previous): array
    {
        $foreignKeyQueries = $this->buildForeignKeyQueries();

        $stale = [];
        foreach ($previous as $hash) {
            $isStale = true;
            foreach ($foreignKeyQueries as $foreignKeyQuery) {
                $foreignKeyQuery->execute(['hash' => $hash]);
                $count = $foreignKeyQuery->fetchColumn();
                if ($count > 0) {
                    $isStale = false;
                    break;
                }
            }
            if ($isStale) {
                $stale[] = $hash;
            }
        }

        return $stale;
    }

    /**
     * Builds a list of queries for finding foreign key relations to a hash.
     *
     * @return array<IQuery> A list of queries, each of which accepts a storage
     *                       hash as its `:hash` parameter.
     */
    private function buildForeignKeyQueries(): array
    {
        $query = $this->db->prepareQuery(self::SELECT_FK_RELATIONS_QUERY);
        $query->execute(['schema' => $this->schema, 'table' => $this->table]);

        $foreignKeyQueries = [];
        foreach ($query as $row) {
            $this->logger->debug(
                sprintf(
                    'Found FK relation: %s.%s => %s.%s',
                    $row['foreign_table'],
                    $row['foreign_column'],
                    $row['primary_table'],
                    $row['primary_column'],
                ),
            );
            if ($row['primary_column'] !== 'hash') {
                $this->logger->notice(
                    sprintf(
                        'FK relation "%s.%s => %s.%s" does not reference %s.hash: consider updating schema',
                        $row['foreign_table'],
                        $row['foreign_column'],
                        $row['primary_table'],
                        $row['primary_column'],
                        $row['primary_table'],
                    ),
                );
            }
            $foreignKeyQueries[] = $this->db->prepareQuery("
                SELECT COUNT(*)
                FROM {$this->db->getQuoter()->quoteIdentifier($row['foreign_table'])} f
                INNER JOIN {$this->db->getQuoter()->quoteIdentifier($row['primary_table'])} s
                    ON f.{$this->db->getQuoter()->quoteIdentifier($row['foreign_column'])}
                       = s.{$this->db->getQuoter()->quoteIdentifier($row['primary_column'])}
                WHERE s.hash = :hash
            ");
        }

        return $foreignKeyQueries;
    }
}

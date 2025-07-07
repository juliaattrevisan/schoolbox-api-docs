<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GC\Check;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\IStatement;
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
        $tableColumnList = $this->buildTableColumnList();
        $stale           = $previous;

        foreach ($tableColumnList as $table => $columns) {
            if (empty($stale)) {
                $this->logger->info('No stale rows left to check: returning');
                return $stale;
            }

            if (str_starts_with($table, '_')) {
                $this->logger->info(sprintf('Skipping deprecated table: %s', $table));
                continue;
            }

            $countQuery = $this->db->executeQuery(
                "SELECT COUNT(*) FROM {$this->db->getQuoter()->quoteIdentifier($table)}",
            );
            $count = (int)$countQuery->fetchColumn();
            $this->logger->info(sprintf('Searching %d rows in table: %s', $count, $table));
            if ($count <= 0) {
                continue;
            }
            foreach ($columns as $columnData) {
                $column = $columnData['foreign_column'];
                if (str_starts_with($column, '_')) {
                    $this->logger->info(sprintf('. skipping deprecated column: %s.%s', $table, $column));
                    continue;
                }

                // Build query for this column, exclude non-stale
                $query      = $this->buildFindHashQuery($table, $columnData);
                $staleCount = count($stale);
                $newStale   = [];
                $this->logger->info(sprintf('. searching column %s.%s... (%d hashes)', $table, $column, $staleCount));
                foreach ($stale as $hash) {
                    $query->execute(['hash' => $hash]);
                    $count = (int)$query->fetchColumn();
                    if ($count <= 0) {
                        $newStale[] = $hash;
                    }
                }

                // Report on usage, and update the stale list with the found
                // hashes excluded
                $newStaleCount = count($newStale);
                $this->logger->info(
                    sprintf(
                        '.. %d of %d non-stale hashes found in %s.%s: %d remaining',
                        ($staleCount - $newStaleCount),
                        $staleCount,
                        $table,
                        $column,
                        $newStaleCount,
                    )
                );
                $stale = $newStale;
            }
        }
        return $stale;
    }

    /**
     * Builds a list of tables and columns which represent text fields.
     *
     * @return array<string, array<array>>
     */
    private function buildTableColumnList(): array
    {
        $query = $this->db->prepareQuery(self::SELECT_FK_RELATIONS_QUERY);
        $query->execute(['schema' => $this->schema, 'table' => $this->table]);

        $tableColumnList = [];
        foreach ($query as $row) {
            $table  = $row['foreign_table'];
//            $column = $row['foreign_column'];
            $tableColumnList[$table] ??= [];
            $tableColumnList[$table][] = $row;
//            $this->logger->debug(
//                sprintf(
//                    'Found FK relation: %s.%s => %s.%s',
//                    $row['foreign_table'],
//                    $row['foreign_column'],
//                    $row['primary_table'],
//                    $row['primary_column'],
//                ),
//            );
//            if ($row['primary_column'] !== 'hash') {
//                $this->logger->notice(
//                    sprintf(
//                        'FK relation "%s.%s => %s.%s" does not reference %s.hash: consider updating schema',
//                        $row['foreign_table'],
//                        $row['foreign_column'],
//                        $row['primary_table'],
//                        $row['primary_column'],
//                        $row['primary_table'],
//                    ),
//                );
//            }
//            $foreignKeyQueries[] = $this->db->prepareQuery("
//                SELECT COUNT(*)
//                FROM {$this->db->getQuoter()->quoteIdentifier($row['foreign_table'])} f
//                INNER JOIN {$this->db->getQuoter()->quoteIdentifier($row['primary_table'])} s
//                    ON f.{$this->db->getQuoter()->quoteIdentifier($row['foreign_column'])}
//                       = s.{$this->db->getQuoter()->quoteIdentifier($row['primary_column'])}
//                WHERE s.hash = :hash
//            ");
        }

        return $tableColumnList;
    }

//    /**
//     * Builds a list of queries for finding foreign key relations to a hash.
//     *
//     * @return array<IQuery> A list of queries, each of which accepts a storage
//     *                       hash as its `:hash` parameter.
//     */
//    private function buildForeignKeyQueries(): array
//    {
//        $query = $this->db->prepareQuery(self::SELECT_FK_RELATIONS_QUERY);
//        $query->execute(['schema' => $this->schema, 'table' => $this->table]);
//
//        $foreignKeyQueries = [];
//        foreach ($query as $row) {
//            $this->logger->debug(
//                sprintf(
//                    'Found FK relation: %s.%s => %s.%s',
//                    $row['foreign_table'],
//                    $row['foreign_column'],
//                    $row['primary_table'],
//                    $row['primary_column'],
//                ),
//            );
//            if ($row['primary_column'] !== 'hash') {
//                $this->logger->notice(
//                    sprintf(
//                        'FK relation "%s.%s => %s.%s" does not reference %s.hash: consider updating schema',
//                        $row['foreign_table'],
//                        $row['foreign_column'],
//                        $row['primary_table'],
//                        $row['primary_column'],
//                        $row['primary_table'],
//                    ),
//                );
//            }
//            $foreignKeyQueries[] = $this->db->prepareQuery("
//                SELECT COUNT(*)
//                FROM {$this->db->getQuoter()->quoteIdentifier($row['foreign_table'])} f
//                INNER JOIN {$this->db->getQuoter()->quoteIdentifier($row['primary_table'])} s
//                    ON f.{$this->db->getQuoter()->quoteIdentifier($row['foreign_column'])}
//                       = s.{$this->db->getQuoter()->quoteIdentifier($row['primary_column'])}
//                WHERE s.hash = :hash
//            ");
//        }
//
//        return $foreignKeyQueries;
//    }

    /**
     * Build a query for finding usage of a hash.
     *
     * @param string $table
     * @param array  $columnData
     * @return IStatement A statement which accepts a storage hash as its
     *                    `hash` parameter.
     */
    private function buildFindHashQuery(string $table, array $columnData): IStatement
    {
        return $this->db->prepareQuery("
            SELECT COUNT(*)
            FROM {$this->db->getQuoter()->quoteIdentifier($table)} f
            INNER JOIN {$this->db->getQuoter()->quoteIdentifier($columnData['primary_table'])} s
                ON f.{$this->db->getQuoter()->quoteIdentifier($columnData['foreign_column'])}
                   = s.{$this->db->getQuoter()->quoteIdentifier($columnData['primary_column'])}
            WHERE s.hash = :hash
        ");
    }
}

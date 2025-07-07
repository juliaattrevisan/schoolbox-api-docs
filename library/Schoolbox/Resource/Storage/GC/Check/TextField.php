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
 * Check for whether the storage hash is contained within any `TEXT` field.
 */
class TextField implements Check
{
    /**
     * Query for finding text fields to search through.
     */
    private const SELECT_TEXT_FIELDS_QUERY = <<<SQL
        SELECT c.TABLE_NAME AS `table`,
               c.COLUMN_NAME AS `column`
        FROM information_schema.COLUMNS c
        INNER JOIN information_schema.TABLES t ON c.TABLE_NAME = t.TABLE_NAME
        WHERE c.TABLE_SCHEMA = :schema
        AND   INSTR(c.DATA_TYPE, 'text')
        AND   t.TABLE_TYPE = 'BASE TABLE'
        ORDER BY c.TABLE_NAME, c.COLUMN_NAME
        SQL;

    /**
     * List of text fields to never search through.
     */
    private const FIELD_BLACKLIST = [
        'search_index' => ['fulltext_index'],
    ];

    /**
     * Constructor
     *
     * @param IConnector      $db     Database connection
     * @param string          $schema The database schema which `$db` uses: required for building list of foreign keys.
     * @param LoggerInterface $logger Logger
     */
    public function __construct(
        private IConnector $db,
        private string $schema,
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
            foreach ($columns as $column) {
                if (str_starts_with($column, '_')) {
                    $this->logger->info(sprintf('. skipping deprecated column: %s.%s', $table, $column));
                    continue;
                }

                // Build query for this column, exclude non-stale
                $query      = $this->buildFindHashQuery($table, $column);
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
     * @return array<string, array<string>>
     */
    private function buildTableColumnList(): array
    {
        $query = $this->db->prepareQuery(self::SELECT_TEXT_FIELDS_QUERY);
        $query->execute(['schema' => $this->schema]);

        $tableColumnList = [];
        foreach ($query as $row) {
            $table  = $row['table'];
            $column = $row['column'];

            // Exclude blacklisted fields
            if (isset(self::FIELD_BLACKLIST[$table]) && in_array($column, self::FIELD_BLACKLIST[$table])) {
                $this->logger->debug(sprintf('Excluding blacklisted column: %s.%s', $table, $column));
                continue;
            }

            $tableColumnList[$table] ??= [];
            $tableColumnList[$table][] = $column;
        }

        return $tableColumnList;
    }

    /**
     * Build a query for finding usage of a hash.
     *
     * @param string $table
     * @param string $column
     * @return IStatement A statement which accepts a storage hash as its
     *                    `hash` parameter.
     */
    private function buildFindHashQuery(string $table, string $column): IStatement
    {
        return $this->db->prepareQuery("
            SELECT COUNT(*)
            FROM {$this->db->getQuoter()->quoteIdentifier($table)} t
            WHERE INSTR({$this->db->getQuoter()->quoteIdentifier($column)}, :hash) > 0
        ");
    }
}

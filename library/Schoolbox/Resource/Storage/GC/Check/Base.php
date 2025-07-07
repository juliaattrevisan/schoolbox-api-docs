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
 * Base check for garbage-collectable storage rows.
 */
abstract class Base implements Check
{
    /**
     * Constructor
     *
     * @param IConnector      $db     Database connection
     * @param LoggerInterface $logger Logger
     */
    public function __construct(
        protected IConnector $db,
        protected LoggerInterface $logger,
    ) {
    }

    /**
     * Filter out hashes from `$previous` which are found within any table
     * columns this check finds.
     *
     * @param array<string> $previous
     * @return array<string>
     */
    public function getStale(array $previous): array
    {
        $tableColumnList = $this->buildTableColumnList();
        $stale           = $previous;

        foreach ($tableColumnList as $table => $columnsData) {
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
            if ($count <= 0) {
                $this->logger->info(sprintf('Skipping empty table: %s...', $table));
                continue;
            }
            $this->logger->info(sprintf('Searching %d rows in table: %s...', $count, $table));
            foreach ($columnsData as $columnData) {
                $columns = [];
                foreach ($columnData['columns'] as $column) {
                    if (str_starts_with($column, '_')) {
                        $this->logger->info(sprintf('. skipping deprecated column: %s', $column));
                        continue;
                    }
                    $columns[] = $column;
                }

                // Build query for this column, exclude non-stale
                $query      = $this->buildFindHashQuery($table, $columns, $columnData['extra'] ?? null);
                $staleCount = count($stale);
                $newStale   = [];
                $this->logger->info(
                    sprintf(
                        '. searching columns (%s)... (%d hashes)',
                        implode(',', $columns),
                        $staleCount,
                    )
                );
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
                        '.. %d of %d non-stale hashes found: %d remaining',
                        ($staleCount - $newStaleCount),
                        $staleCount,
                        $newStaleCount,
                    )
                );
                $stale = $newStale;
            }
        }
        return $stale;
    }

    /**
     * Builds a list of tables and column data, which references a table and
     * column within which a storage hash may be found.
     *
     * @return array<string, array<array{columns: array<string>, extra?: array}>>
     *     key:   table name
     *     value: a list of column data sets, each to be passed to
     *            `buildFindHashQuery`.
     *            Each must contain at least `columns` (the columns in `table`
     *            to search within), and may contain any supplementary
     *            information required to build a query in `extra`.
     */
    abstract protected function buildTableColumnList(): array;

    /**
     * Build a query which finds usages of a hash.
     *
     * @param string     $table   The table to search within.
     * @param array      $columns The columns to search within.
     * @param array|null $extra   Any information about the relation of the
     *                            table to the hash which may be relevant.
     * @return IStatement A statement which accepts a storage hash as its `hash`
     *                    parameter.
     */
    abstract protected function buildFindHashQuery(string $table, array $columns, ?array $extra): IStatement;
}

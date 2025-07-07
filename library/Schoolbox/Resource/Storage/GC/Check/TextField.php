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
 * Check for whether the storage hash is contained within any `TEXT` field.
 */
class TextField implements Check
{
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
        $queries = $this->buildQueries();
        $stale   = [];
        foreach ($previous as $hash) {
            $isStale = true;
            foreach ($queries as $foreignKeyQuery) {
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
     * Builds a list of queries for finding usages of a hash within character
     * fields.
     *
     * @return array<IQuery> A list of queries, each of which accepts a storage
     *                       hash as its `:hash` parameter.
     */
    private function buildQueries(): array
    {
        $query = $this->db->prepareQuery(self::SELECT_TEXT_FIELDS_QUERY);
        $query->execute(['schema' => $this->schema]);

        $queries = [];
        foreach ($query as $row) {
            $table  = $row['table'];
            $column = $row['column'];
            $this->logger->debug(
                sprintf(
                    'Searching in text field: %s.%s',
                    $table,
                    $column,
                ),
            );
            $queries[] = $this->db->prepareQuery("
                SELECT COUNT(*)
                FROM {$this->db->getQuoter()->quoteIdentifier($table)} t
                WHERE INSTR({$this->db->getQuoter()->quoteIdentifier($column)}, :hash) > 0
            ");
        }

        return $queries;
    }
}

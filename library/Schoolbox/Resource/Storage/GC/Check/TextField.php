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
class TextField extends Base
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
        AND   (INSTR(c.DATA_TYPE, 'text') OR INSTR(c.DATA_TYPE, 'char'))
        AND   t.TABLE_TYPE = 'BASE TABLE'
        ORDER BY c.TABLE_NAME, c.COLUMN_NAME
        SQL;

    /**
     * Query for finding a foreign key relation onto the `storage` table for
     * a given column.
     */
    private const SELECT_FK_RELATION_QUERY = <<<SQL
        select COUNT(*)
        from information_schema.REFERENTIAL_CONSTRAINTS fks
        inner join information_schema.KEY_COLUMN_USAGE kcu
            on  fks.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
            and fks.TABLE_NAME        = kcu.TABLE_NAME
            and fks.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
        where fks.CONSTRAINT_SCHEMA        = :schema
          and fks.UNIQUE_CONSTRAINT_SCHEMA = :schema
          and fks.REFERENCED_TABLE_NAME    = :primaryTable
          AND fks.TABLE_NAME               = :foreignTable
          AND kcu.COLUMN_NAME              = :foreignColumn
        SQL;

    /**
     * List of text fields to never search through.
     *
     * @var array<string, (array<string>|true)>
     *     key:   a table name
     *     value: either a list of columns to skip, or `true` to skip all in
     *            this table
     */
    private const FIELD_BLACKLIST = [
        'search_index'      => true,
        'storage'           => true,
        'storage_transform' => true,
    ];

    /**
     * Constructor
     *
     * @param IConnector      $db     Database connection
     * @param LoggerInterface $logger Logger
     * @param string          $schema The database schema which `$db` uses: required for determining if a column has a
     *                                foreign key onto the storage table, so that it can be skipped.
     * @param string          $table  The table which stores storage records.
     */
    public function __construct(
        IConnector $db,
        LoggerInterface $logger,
        private string $schema,
        private string $table,
    ) {
        parent::__construct($db, $logger);
    }

    /**
     * Builds a list of tables and columns which represent text fields.
     */
    protected function buildTableColumnList(): array
    {
        $columnsQuery = $this->db->prepareQuery(self::SELECT_TEXT_FIELDS_QUERY);
        $columnsQuery->execute(['schema' => $this->schema]);

        $tableColumnList   = [];
        $blacklistedTables = [];
        $foreignKeysQuery  = $this->db->prepareQuery(self::SELECT_FK_RELATION_QUERY);
        foreach ($columnsQuery as $row) {
            $table  = $row['table'];
            $column = $row['column'];

            // Exclude blacklisted tables and fields,
            // as well as the storage table itself
            if ($table === $this->table) {
                if (!isset($blacklistedTables[$table])) {
                    $this->logger->debug(sprintf('Excluding storage table: %s', $table));
                    $blacklistedTables[$table] = true;
                }
            }
            if (isset(self::FIELD_BLACKLIST[$table])) {
                if (self::FIELD_BLACKLIST[$table] === true) {
                    // Only output blacklisted table once
                    if (!isset($blacklistedTables[$table])) {
                        $this->logger->debug(sprintf('Excluding blacklisted table: %s', $table));
                        $blacklistedTables[$table] = true;
                    }
                    continue;
                }
                if (in_array($column, self::FIELD_BLACKLIST[$table])) {
                    $this->logger->debug(sprintf('Excluding blacklisted column: %s.%s', $table, $column));
                    continue;
                }
            }

            // Exclude fields already in a foreign key relation
            $foreignKeysQuery->execute([
                'schema'        => $this->schema,
                'primaryTable'  => $this->table,
                'foreignTable'  => $table,
                'foreignColumn' => $column,
            ]);
            $foreignKeysCount = (int)$foreignKeysQuery->fetchColumn();
            if ($foreignKeysCount > 0) {
                $this->logger->debug(sprintf('Excluding foreign key column: %s.%s', $table, $column));
                continue;
            }

            $tableColumnList[$table][0] ??= [
                'columns' => [],
            ];
            $tableColumnList[$table][0]['columns'][] = $column;
        }

        return $tableColumnList;
    }

    /**
     * Build a query for finding usage of a hash.
     *
     * @param string $table      The table to search within.
     * @param array  $columnData `column`: the column to search within
     * @return IStatement A statement which accepts a storage hash as its `hash`
     *                    parameter.
     */
    protected function buildFindHashQuery(string $table, array $columns, ?array $extra): IStatement
    {
        // Build an AND statement for each column
        $whereParts = [];
        foreach ($columns as $column) {
            $whereParts[] = "INSTR({$this->db->getQuoter()->quoteIdentifier($column)}, :hash) > 0";
        }
        $wherePart = implode(' OR ', $whereParts);

        return $this->db->prepareQuery("
            SELECT COUNT(*)
            FROM {$this->db->getQuoter()->quoteIdentifier($table)} t
            WHERE {$wherePart}
        ");
    }
}

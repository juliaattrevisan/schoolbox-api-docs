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
        IConnector $db,
        LoggerInterface $logger,
        private string $schema
    ) {
        parent::__construct($db, $logger);
    }

    /**
     * Builds a list of tables and columns which represent text fields.
     *
     * @return array<string, array<array{column: string}>>
     */
    protected function buildTableColumnList(): array
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
            $tableColumnList[$table][] = $row;
        }

        return $tableColumnList;
    }

    /**
     * Build a query for finding usage of a hash.
     *
     * @param string $table
     * @param array $columnData
     * @return IStatement A statement which accepts a storage hash as its `hash`
     *                    parameter.
     */
    protected function buildFindHashQuery(string $table, array $columnData): IStatement
    {
        return $this->db->prepareQuery("
            SELECT COUNT(*)
            FROM {$this->db->getQuoter()->quoteIdentifier($table)} t
            WHERE INSTR({$this->db->getQuoter()->quoteIdentifier($columnData['column'])}, :hash) > 0
        ");
    }
}

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
class ForeignKeyRelation extends Base
{
    private const SELECT_FK_RELATIONS_QUERY = <<<SQL
        select
            fks.TABLE_NAME             as `table`,
            kcu.COLUMN_NAME            as `column`,
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
     * @param LoggerInterface $logger Logger
     * @param string          $schema The database schema which `$db` uses: required for building list of foreign keys.
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
     * Builds a list of tables and columns which represent foreign key
     * relations.
     */
    protected function buildTableColumnList(): array
    {
        $query = $this->db->prepareQuery(self::SELECT_FK_RELATIONS_QUERY);
        $query->execute(['schema' => $this->schema, 'table' => $this->table]);

        $tableColumnList = [];
        foreach ($query as $row) {
            $table = $row['table'];
            $tableColumnList[$table] ??= [];
            $tableColumnList[$table][] = [
                'columns' => [$row['column']],
                'extra'   => [
                    'primary_table'  => $row['primary_table'],
                    'primary_column' => $row['primary_column'],
                ],
            ];
        }

        return $tableColumnList;
    }

    /**
     * Build a query for finding usage of a hash.
     *
     * @param string $table      The table to search within.
     * @param array $columnData `column`:         the column on `$table` to search within
     *                          `primary_table`:  the name of the storage table
     *                           primary_column`: the column on `primary_table` that `column` joins on:
     *                                            not always `hash`
     * @return IStatement A statement which accepts a storage hash as its `hash`
     *                    parameter.
     */
    protected function buildFindHashQuery(string $table, array $columnData): IStatement
    {
        // Notice if foreign key doesn't join onto storage.hash
        if ($columnData['primary_column'] !== 'hash') {
            $this->logger->notice(
                sprintf(
                    'FK relation "%s.%s => %s.%s" does not reference %s.hash: consider updating schema',
                    $table,
                    $columnData['column'],
                    $columnData['primary_table'],
                    $columnData['primary_column'],
                    $columnData['primary_table'],
                ),
            );
        }
        return $this->db->prepareQuery("
            SELECT COUNT(*)
            FROM {$this->db->getQuoter()->quoteIdentifier($table)} f
            INNER JOIN {$this->db->getQuoter()->quoteIdentifier($columnData['primary_table'])} s
                ON f.{$this->db->getQuoter()->quoteIdentifier($columnData['column'])}
                   = s.{$this->db->getQuoter()->quoteIdentifier($columnData['primary_column'])}
            WHERE s.hash = :hash
        ");
    }
}

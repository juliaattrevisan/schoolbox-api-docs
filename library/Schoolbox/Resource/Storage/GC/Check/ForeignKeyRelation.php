<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GC\Check;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\IStatement;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Check for whether a hash is found in any field which is a foreign key onto
 * the `storage` table.
 */
class ForeignKeyRelation extends Base
{
    /**
     * Query for fetching foreign key relations to the `storage` table.
     *
     * Returns these rows:
     * * `table`: the table which joins onto `storage`
     * * `column`: the column in `table` which joins onto `storage`
     * * `primary_table`: the table which the foreign key joins onto
     *    (should always be `storage`)
     * * `primary_column`: the column in `primary_table` which the foreign key
     *    joins onto
     *    (should always be `hash`, but is not guaranteed to be)
     */
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
     * List of foreign key relations to never query.
     *
     * @var array<string, array<string>>
     *     key:   a table name
     *     value: a list of columns to skip
     */
    private const FIELD_BLACKLIST = [
        // These table's data are wholly reliant on the data in `storage`:
        // if the storage row is deleted, the deletion should cascade to these
        // joins.
        'storage_transform' => ['hash'],
        'video_queue'       => ['hash'],
    ];

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
            $table  = $row['table'];
            $column = $row['column'];

            // Exclude blacklisted foreign keys
            if (isset(self::FIELD_BLACKLIST[$table]) && in_array($column, self::FIELD_BLACKLIST[$table])) {
                $this->logger->debug(sprintf('Excluding blacklisted foreign key: %s.%s', $table, $column));
                continue;
            }

            $tableColumnList[$table] ??= [];
            $tableColumnList[$table][] = [
                'columns' => [$column],
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
     * @param array{primary_table: string, primary_column: string}|null $extra
     */
    protected function buildFindHashQuery(string $table, array $columns, ?array $extra): IStatement
    {
        // Use the first column only:
        // if there's more than one column, cannot build the query
        if (count($columns) !== 1) {
            throw new InvalidArgumentException(
                sprintf('$columns expects exactly one column: %d given', count($columns)),
            );
        }
        [$column] = $columns;

        // Both `primary_table` and `primary_column` must be set:
        // otherwise, cannot build the query
        if (!isset($extra['primary_table'], $extra['primary_column'])) {
            throw new InvalidArgumentException(
                '`buildTableColumnList` did not set `primary_table` and `primary_column`',
            );
        }

        // Notice if foreign key doesn't join onto storage.hash:
        // this is a schema change which could be made
        if ($extra['primary_column'] !== 'hash') {
            $this->logger->notice(
                sprintf(
                    'FK relation "%s.%s => %s.%s" does not reference %s.hash: consider updating schema',
                    $table,
                    $column,
                    $extra['primary_table'],
                    $extra['primary_column'],
                    $extra['primary_table'],
                ),
            );
        }
        return $this->db->prepareQuery("
            SELECT COUNT(*)
            FROM {$this->db->getQuoter()->quoteIdentifier($table)} f
            INNER JOIN {$this->db->getQuoter()->quoteIdentifier($extra['primary_table'])} s
                ON f.{$this->db->getQuoter()->quoteIdentifier($column)}
                   = s.{$this->db->getQuoter()->quoteIdentifier($extra['primary_column'])}
            WHERE s.hash = :hash
        ");
    }
}

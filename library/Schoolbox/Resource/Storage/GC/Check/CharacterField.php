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
 * Check for whether the storage hash is contained within a prescribed set of
 * `CHAR` or `VARCHAR` fields.
 */
class CharacterField extends Base
{
    /**
     * Prescribed set of `CHAR` or `VARCHAR` fields to check.
     * * key: table name
     * * value: list of column names
     *
     * @var array<string, array<array{columns: array<string>}>>
     */
    private const FIELDS = [
        // Notes:
        // * any field which contains `hash` should maybe be a FK?
        // * any field which contains `url` or `link` should be checked?
        'bookmarks'                    => [['columns' => ['url']]],
        'component_config_definition'  => [['columns' => ['default_value']]],
        'component_config_instance'    => [['columns' => ['value']]],
        'link'                         => [['columns' => ['url']]],
        'menu_side'                    => [['columns' => ['url']]],
        'menu_top'                     => [['columns' => ['url']]],
        'profile_button'               => [['columns' => ['url']]],
        'rubric_descriptor'            => [['columns' => ['description']]],
        'sis_user_profile'             => [['columns' => ['portrait_hash']]],
        'socialstream_post'            => [['columns' => ['thumbnail', 'url']]],
        'sportsfixtures_division'      => [['columns' => ['url']]],
        'sportsfixtures_import_errors' => [['columns' => ['hash']]],
        'sportsfixtures_sport'         => [['columns' => ['url']]],
        'sportsfixtures_team'          => [['columns' => ['url']]],
        'summative_report'             => [['columns' => ['footer']]],
        'task'                         => [['columns' => ['link']]],
        'tile'                         => [['columns' => ['link']]],
        'ups_log'                      => [['columns' => ['hash']]],
    ];

    /**
     * Query for fetching the length of the data which is searched.
     */
    private const SELECT_SEARCH_DATA_LENGTH_QUERY = <<<SQL
        SELECT
            t.TABLE_ROWS * c.CHARACTER_OCTET_LENGTH AS `search_data_length`
        FROM information_schema.TABLES t
        INNER JOIN information_schema.COLUMNS c
            ON  t.TABLE_SCHEMA = c.TABLE_SCHEMA
            AND t.TABLE_NAME   = c.TABLE_NAME
        WHERE t.TABLE_SCHEMA = :schema
          AND t.TABLE_NAME   = :table
          AND c.COLUMN_NAME  = :column
        SQL;

    /**
     * Constructor
     *
     * @param IConnector      $db     Database connection
     * @param LoggerInterface $logger Logger
     * @param string          $schema The database schema which `$db` uses: required for building list of foreign keys.
     */
    public function __construct(
        IConnector $db,
        LoggerInterface $logger,
        private string $schema,
    ) {
        parent::__construct($db, $logger);
    }

    /**
     * Builds a list of tables and columns which represent character fields
     * which may contain storage hashes, but are not foreign keys.
     */
    protected function buildTableColumnList(): array
    {
        $query = $this->db->prepareQuery(self::SELECT_SEARCH_DATA_LENGTH_QUERY);

        // search_data_length: consider TABLES.TABLE_ROWS * COLUMNS.CHARACTER_OCTET_LENGTH
        $fields = self::FIELDS;
        foreach ($fields as $table => &$tableData) {
            foreach ($tableData as &$data) {
                $data['extra']['search_data_length'] = 0;
                foreach ($data['columns'] as $column) {
                    $query->execute([
                        'schema' => $this->schema,
                        'table'  => $table,
                        'column' => $column,
                    ]);
                    $row = $query->fetch();
                    $data['extra']['search_data_length'] += $row['search_data_length'];
                }
            }
        }
        return $fields;
    }

    /**
     * @inheritdoc
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

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
 * Check for whether the storage hash is contained within any `TEXT`, `CHAR` or
 * `VARCHAR` field, with sensible exclusions:
 * * the `storage` and `storage_transform` tables themselves
 * * fields which already have a foreign key relation to `storage`
 *   (these are processed by `ForeignKeyRelation`)
 * * ephemeral tables (e.g. `search_index`)
 */
class TextField extends Base
{
    /**
     * Query for finding text fields to search through.
     */
    private const SELECT_TEXT_FIELDS_QUERY = <<<SQL
        SELECT c.TABLE_NAME AS `table`,
               c.COLUMN_NAME AS `column`,
               t.DATA_LENGTH AS `search_data_length`
        FROM information_schema.COLUMNS c
        INNER JOIN information_schema.TABLES t ON c.TABLE_NAME = t.TABLE_NAME
        WHERE c.TABLE_SCHEMA = :schema
        AND   INSTR(c.DATA_TYPE, 'text')
        AND   t.TABLE_TYPE = 'BASE TABLE'
        ORDER BY c.TABLE_NAME, c.COLUMN_NAME
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
        // storage and storage_transform will never be considered usages of a
        // storage row themselves.
        'storage'           => true,
        'storage_transform' => true,

        // Nor will video transcodes in video_queue
        'video_queue' => true,

        // search_index is derived from other data:
        // if the other data is deleted, then search_index will pick up on that.
        'search_index' => true,

        // The following tables are never expected to contain storage hashes.
        'assess_luQuestionType'       => true,
        'component_config_definition' => true,
        'component_config_instance'   => true,
        'error'                       => true,
        'msg_source'                  => true,
        'dropbox'                     => true,
        'etherpad_pad'                => true,
        'user_assume_log'             => true,
        'submission_mark_type'        => true,
        'luCampus'                    => true,
        'luResidentialHouse'          => true,
        'lu_resource_moderation_type' => true,
        'eotc_status'                 => true,
        'eotc_config'                 => true,
        'plagscan_document'           => true,
        'rubric_descriptor'           => true,
        'assess_question_category'    => true,
        'calendar_connector'          => true,
        'course_builder_config'       => true,
        'lu_pastoral_type'            => true,
        'lu_pastoral_severity'        => true,
        'pastoral_access'             => true,
        'pastoral_tags'               => true,
        'term'                        => true,
        'user_password_reset_request' => true,
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

            // Is this table or field blacklisted?
            /** @var array|true|null $blacklist - required for PHPStan */
            $blacklist = self::FIELD_BLACKLIST[$table] ?? null;
            if (isset($blacklist)) {
                if ($blacklist === true) {
                    // Only output blacklisted table once
                    if (!isset($blacklistedTables[$table])) {
                        $this->logger->debug(sprintf('Excluding blacklisted table: %s', $table));
                        $blacklistedTables[$table] = true;
                    }
                    continue;
                }
                if (in_array($column, $blacklist)) {
                    $this->logger->debug(sprintf('Excluding blacklisted column: %s.%s', $table, $column));
                    continue;
                }
            }

            $tableColumnList[$table][0] ??= [
                'columns' => [],
                'extra'   => [
                    'search_data_length' => $row['search_data_length'],
                ]
            ];
            $tableColumnList[$table][0]['columns'][] = $column;
        }

        return $tableColumnList;
    }

    /**
     * @inheritdoc
     */
    protected function buildFindHashQuery(string $table, array $columns, ?array $extra): IStatement
    {
        if (empty($columns)) {
            throw new InvalidArgumentException('$columns is empty');
        }

        // Build a search within each column for the hash's value.
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

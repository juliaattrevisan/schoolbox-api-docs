<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GC\Check;

use Alaress\Database\Connector\IConnector;
use Alaress_Byte_Format;
use Psr\Log\LoggerInterface;

/**
 * Check for whether the storage hash is contained within any `TEXT`, `CHAR` or
 * `VARCHAR` field, with sensible exclusions:
 * * the `storage` and `storage_transform` tables themselves
 * * fields which already have a foreign key relation to `storage`
 *   (these are processed by `ForeignKeyRelation`)
 * * ephemeral tables (e.g. `search_index`)
 *
 * This uses a new method for finding stale hashes:
 * * on init, this searches through all text column field values, and extracts
 *   hashes out of them: this then builds an index of hashes found
 *   * this process is slow as it must process all data, but only needs to run
 *     once during the whole process
 * * the stale hash check then needs to only cross-reference its hashes against
 *   the hashes in use
 *
 * This should work around the slow query issue in `TextField`: it ran a
 * `SELECT` statement for every hash * every text column.
 */
class HashIndexedTextField implements Check
{
    /**
     * Query for finding text fields to search through.
     */
    private const SELECT_TEXT_FIELDS_QUERY = <<<SQL
        SELECT c.TABLE_NAME AS `table`,
               c.COLUMN_NAME AS `column`,
               t.DATA_LENGTH AS `search_data_length`
        FROM information_schema.COLUMNS c
        INNER JOIN information_schema.TABLES t
            ON  c.TABLE_SCHEMA = t.TABLE_SCHEMA
            AND c.TABLE_NAME   = t.TABLE_NAME
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
     * Regular expression used to find hash values in text fields.
     *
     * It's assumed that any 40-character hex sequence is a hash.
     */
    private const HASH_REGEXP = '/[0-9a-f]{40}/i';

    /**
     * Hashes which have been determined to exist in Schoolbox data.
     *
     * @var array<string, true> key: the hash, value: always true if it exists
     */
    private ?array $hashIndex = null;

    /**
     * Constructor
     *
     * @param IConnector      $db     Database connection
     * @param LoggerInterface $logger Logger
     * @param string          $schema The database schema which `$db` uses: required for building list of foreign keys.
     * @param string          $table  The table which stores storage records.
     */
    public function __construct(
        private IConnector $db,
        private LoggerInterface $logger,
        private string $schema,
        private string $table,
    ) {
    }

    public function getStale(array $previous): array
    {
        $stale = $previous;
        if (empty($stale)) {
            $this->logger->info('No stale rows left to check: returning');
            return $stale;
        }

        if (isset($this->hashIndex)) {
            $this->logger->info('Reusing hash index from previous run');
        } else {
            $this->logger->info('Building hash index: this may take a while...');
            $time = microtime(true);
            $this->hashIndex ??= $this->buildHashIndex();
            $this->logger->info(sprintf('. hash index built in %0.3fs', microtime(true) - $time));
        }

        $staleCount = count($stale);
        $newStale   = array_filter(
            $stale,
            fn ($hash) => !isset($this->hashIndex[strtolower($hash)]),
        );
        $newStaleCount = count($newStale);
        $this->logger->info(
            sprintf(
                '. %d of %d non-stale hashes found: %d remaining',
                ($staleCount - $newStaleCount),
                $staleCount,
                $newStaleCount,
            )
        );
        return $newStale;
    }

    /**
     * Builds the index of hashes which are in use in the Schoolbox instance.
     *
     * @return array<string, true>
     */
    private function buildHashIndex(): array
    {
        $hashIndex       = [];
        $tableColumnList = $this->buildTableColumnList();
        foreach ($tableColumnList as $table => $columnsData) {
            if (str_starts_with($table, '_')) {
                $this->logger->debug(sprintf('Skipping deprecated table: %s', $table));
                continue;
            }

            $countQuery = $this->db->executeQuery(
                "SELECT COUNT(*) FROM {$this->db->getQuoter()->quoteIdentifier($table)}",
            );
            $count = (int)$countQuery->fetchColumn();
            if ($count <= 0) {
                $this->logger->debug(sprintf('Skipping empty table: %s...', $table));
                continue;
            }
            $this->logger->debug(
                sprintf(
                    'Building hash index for %d rows in table: %s (size: %s)...',
                    $count,
                    $table,
                    isset($columnData['extra']['search_data_length'])
                        ? Alaress_Byte_Format::bytesToString($columnData['extra']['search_data_length'], renderIec: true)
                        : 'unknown',
                )
            );
            $columns = [];
            foreach ($columnsData as $columnData) {
                foreach ($columnData['columns'] as $column) {
                    if (str_starts_with($column, '_')) {
                        $this->logger->debug(sprintf('. skipping deprecated column: %s', $column));
                        continue;
                    }
                    $columns[] = $column;
                }
            }

            if (empty($columns)) {
                $this->logger->debug(sprintf('. skipping table with no text columns: %s...', $table));
                continue;
            }

            // Now, build the hash index for all columns
            foreach ($columns as $column) {
                $this->logger->debug(sprintf('. building hash index for column: %s...', $column));
                $query = $this->db->prepareQuery("
                    SET STATEMENT max_statement_time = 0 FOR
                    SELECT {$this->db->getQuoter()->quoteIdentifier($column)} c
                    FROM {$this->db->getQuoter()->quoteIdentifier($table)}
                    WHERE {$this->db->getQuoter()->quoteIdentifier($column)} IS NOT NULL
                ");

                $query->execute();
                $columnHashes = [];
                foreach ($query as $row) {
                    $value = $row['c'];
                    if (preg_match_all(self::HASH_REGEXP, $value, $matches)) {
                        foreach ($matches[0] as $match) {
                            $columnHashes[strtolower($match)] = true;
                        }
                    }
                }
                $this->logger->debug(sprintf('.. %d hashes found', count($columnHashes)));
                $hashIndex += $columnHashes;
            }
        }
        return $hashIndex;
    }


    /**
     * Builds a list of tables and columns which represent text fields.
     */
    private function buildTableColumnList(): array
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
}

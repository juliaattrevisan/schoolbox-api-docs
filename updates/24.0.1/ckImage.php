#!/usr/bin/php
<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        http://schoolbox.education/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Importer\InlineImageConverter;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * CLI Tool
 *
 * Copy this to /tools folder
 *
 * To redirect log output to a file(s):
 *     STDOUT is file descriptor 1
 *     STDERR is file descriptor 2; our logger output ends up going here
 *
 *     Example: php example.php 2> log.txt
 */
$app = new class extends CliTool\App {
    /**
     * array to contain the known list of tables.columns that can contain ckeditor data
     * @var array[string][string] // table[column]
     */
    private const CK_FIELDS = [
        'assessment' => [
            'description',
        ],
        'comms_news_article' => [
            'body',
        ],
        'event' => [
            'detail',
        ],
        'evidence' => [
            'body',
        ],
        'forumPost' => [
            'body',
        ],
        'pastoral' => [
            'body_private',
            'body_public',
        ],
        'textBox' => [
            'body',
        ],
        'wikiRevision' => [
            'body',
        ],
    ];

    /**
     * So we don't miss or alter the search param without specific intention
     */
    private const BASE64_SEARCH = '%;base64,%';

    /**
     * Direct DB connection, so we don't need to use mappers
     * @var IConnector|null
     */
    private ?IConnector $database = null;

    /**
     * Extract image and send to storage tool
     *
     * @var InlineImageConverter|null
     */
    private ?InlineImageConverter $inlineImageConverter = null;

    /**
     * Counters to keep track of the run
     * @var int
     */
    private int $tables = 0;
    private int $tablesSkipped = 0;
    private int $processed = 0;
    private int $item = 0;
    private int $errors = 0;
    private int $falsePositive = 0;

    /**
     * @inheritdoc
     */
    public function run(): void
    {
        $storageMapper = new StorageMapper();

        $this->database = $this->getDatabaseInternal();
        $this->inlineImageConverter = new InlineImageConverter($storageMapper, $this->getLogger());

        // scan/process all defined known tables / columns
        foreach (self::CK_FIELDS as $table => $columns) {
            $data = $this->findBase64($table, $columns);
            if ($data->rowCount()) {
                $this->tables++;
                $this->extractImage($table, $data);
            } else {
                $this->tablesSkipped++;
                $this->getLogger()->debug(sprintf("Table '%s' skipped as no base64 data was found", $table));
            }
        }
        $this->getLogger()->info("CKEditor image to storage extract Complete; Stats:");
        $stats = [
            'tables' => $this->tables,
            'tablesSkipped' => $this->tablesSkipped,
            'items' => $this->item,
            'processed' => $this->processed,
            'errors' => $this->errors,
            'falsePositive' => $this->falsePositive
        ];
        $this->getLogger()->info(json_encode($stats));
    }

    /**
     * Get the primary key for the db table, so when we update the fields, we can re inject correctly.
     *
     * @param string $table
     * @return string
     */
    private function getPrimKey(string $table): string
    {
        $query = $this->database->prepareQuery(
            "SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY'"
        );
        $query->execute();
        return $query->fetchColumnArray('Column_name')[0];
    }

    /**
     * Find and return entries that contain base64 data
     *
     * @param string $table
     * @param array $columns
     * @return Query\Base|Query\IQuery
     */
    private function findBase64(string $table, array $columns): Query\Base|Query\IQuery
    {
        // get primary
        $primary = $this->getPrimKey($table);

        // get all entries that contain base64
        $pb = $this->database->buildPredicateBuilder($table);
        foreach ($columns as $column) {
            $pb = $pb->like($column, self::BASE64_SEARCH);
        }

        $selectColumns = implode(',', $columns);
        $query = $this->database->prepareQuery(
            "SELECT $primary, $selectColumns  FROM $table WHERE {$pb->getWhereUnion()}"
        );
        $params = $pb->getParams();
        $query->execute($params);
        return $query;
    }

    /**
     * Extract base64 and update table field data
     *
     * @param string $table
     * @param Query\Base|Query\IQuery $tableData
     * @return void
     */
    private function extractImage(string $table, Query\Base|Query\IQuery $tableData): void
    {
        $primary = $this->getPrimKey($table);
        while ($row = $tableData->fetch()) {
            foreach ($row as $column => $value) {
                if ($column === $primary) {
                    continue;
                }

                // record this item that needs updating
                $this->item++;

                $this->getLogger()->info(sprintf("Extracting image from %s.%s (%s: %s)", $table, $column, $primary, $row[$primary]));
                try {
                    $newValue = $this->inlineImageConverter->convert((string)$value);
                } catch (RuntimeException $e) {
                    $this->getLogger()->error("Image Extraction Failed");
                    $this->getLogger()->error($e->getMessage());
                    $this->getLogger()->debug($e->getTraceAsString());

                    // record this failure
                    $this->errors++;

                    continue;
                }
                if ($newValue !== $value) {
                    $statement = $this->database->executeQuery(
                        "UPDATE $table SET $column = :value WHERE $primary = :key",
                        [
                            'value' => $newValue, 'key' => $row[$primary]
                        ]
                    );

                    // record this success
                    $this->processed++;
                    $this->getLogger()->info(sprintf("Successfully extracted image from %s.%s (%s: %s)", $table, $column, $primary, $row[$primary]));
                } else {
                    // record this false positive
                    $this->falsePositive++;
                    $this->getLogger()->info(sprintf("False positive, found '%s' in %s.%s (%s: %s)", self::BASE64_SEARCH, $table, $column, $primary, $row[$primary]));
                }
            }
        }
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};
/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options, bypassMaintenanceModeCheck: true))->runApp();

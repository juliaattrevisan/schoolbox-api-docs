#!/usr/bin/php
<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        http://schoolbox.education/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Schoolbox\Application\CliTool;
use League\Flysystem\Filesystem;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Resource\Storage\Sanitizer;


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
     * @var array[table][column]
     */
    private $CK_FIELDS = [
        'assessment' => [
            'description'
        ],
        'comms_news_article' => [
            'body',
        ],
        'forumPost' => [
            'body'
        ],
        'pastoral' => [
            'body_private',
            'body_public',
        ],
        'textBox' => [
            'body'
        ],
    ];

    private ?IConnector $database = null;

    /**
     * This process needs to write to storage, thus it must run as `www-data`.
     */
    public function getRequiredProcessUser(): ?string
    {
        return 'www-data';
    }

    /**
     * @inheritdoc
     */
    public function run(): void
    {
        if ($this->perform()) {
            $this->getLogger()->info('Running in perform mode (-p): changes will be written to filesystem');
        } else {
            $this->getLogger()->info('Running in dry-run mode: use -p to write changes to filesystem');
        }
        $storageMapper = new StorageMapper();

        /** @var Filesystem $storageFilesystem */
        $storageFilesystem = $this->getDi()->get('filesystem.storage');
        $this->database = $this->getDatabaseInternal();

        // scan/process all defined known tables / columns
        foreach ($this->CK_FIELDS as $table => $columns) {
            new Alaress_Probe($this->findBase64($table, $columns));
        }
        //
        // scan / process all defined columns within a specified table
        //
        // scan / process a specified table,column
        //
        // process a specified table,column for a specified object ID.
    }

    private function getPrimKey(string $table)
    {
        $query = $this->database->prepareQuery(
            "SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY'"
        );
        $query->execute();
        return $query->fetchColumnArray('Column_name')[0];
    }

    private function findBase64(string $table, array $columns)
    {
        // get primary
        $primary = $this->getPrimKey($table);

        // get all entries that contain base64
        $pb = $this->database->buildPredicateBuilder($table);
        foreach ($columns as $column) {
            $pb = $pb->like($column, '%base64%');
        }

        $query = $this->database->prepareQuery(
            "SELECT * FROM $table WHERE {$pb->getWhereUnion()}"
        );
        $params = $pb->getParams();
        $query->execute($params);
        return $query->fetchAll();
    }
};
/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();

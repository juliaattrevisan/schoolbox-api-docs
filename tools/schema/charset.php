#!/usr/bin/php
<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

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
$app = new class extends CliTool\App
{

    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * --flag
     *
     * An example arg that's a boolean on/off
     */
    private const OPT_LONG_FLAG = 'flag';


    /**
     * --input
     *
     * An example arg that has an actual value, denoted by the colon
     */
    private const OPT_LONG_INPUT = 'input:';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FLAG,
            self::OPT_LONG_INPUT
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Finds all the tables (and their columns) and assign their charset to be utf8mb4 where they
were previously utf8. This schema drift is likely caused by failed updates somewhere in an
instance's distant past. 

Get a list of tables and their columns which aren't utf8mb4 by using the planning mode;
update them by using Perform

Usage: php charset.php [--perform]

HELP;
            exit;
        }

        $this->getLogger()->info('Scouring the universe');

        $manifest = $this->getManifest();

        print_r($manifest);

        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }

        exit;
    }


    /**
     * Get a list of tables, their columns, and their respective collations.
     * Table collations are in a 'collations' array key
     * Columns have their collations as an array value
     *
     * @return array
     */
    private function getManifest(): array
    {
        $tablesQuery = "
            SELECT TABLE_NAME, TABLE_COLLATION
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = 'schoolbox'
        ";

        $tables = $this->getDatabaseInternal()->executeQuery($tablesQuery, [])->fetchAll();

        $stack = [];

        foreach ($tables as $table) {
            $stack[$table['TABLE_NAME']] = [
                'collation' => $table['TABLE_COLLATION'],
                'columns' => []
            ];

            /* Every table should get its columns inspected too
             */
            $columnsQuery = "
                SELECT COLUMN_NAME, COLLATION_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE table_name = :table
            ";

            $columns = $this->getDatabaseInternal()->executeQuery($columnsQuery, [
                'table' => $table['TABLE_NAME']
            ])->fetchAll();

            foreach ($columns as $column) {
                $stack[$table['TABLE_NAME']]['columns'][$column['COLUMN_NAME']] = $column['COLLATION_NAME'];
            }
        }

        return $stack;
    }


    /**
     * FIXME Decide whether this script must be run as root
     *  By default, this returns false (see CliTool\App) but if you're using methods that require
     *  access to special folders (such as when you're creating files) you need to run this as root.
     *  Otherwise it'll fail with a generic error that might mislead you
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return parent::runAsRoot();
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();

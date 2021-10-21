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
    private const OPT_LONG_STRATEGIC = 'strategic';


    /**
     * --input
     *
     * An example arg that has an actual value, denoted by the colon
     */
    private const OPT_LONG_INPUT = 'input:';


    /**
     * Tables seem to have collation utf8mb4_unicode_ci
     */
    private const DESIRED_TABLE_COLLATION = 'utf8mb4_unicode_ci';


    /**
     * Columns generally seem to have collation utf8mb4_unicode_ci
     * But some have utf8mb4_general_ci
     */
    private const DESIRED_COLUMN_COLLATION = 'utf8mb4_unicode_ci';


    /**
     * Columns are meant to have this charset
     */
    private const DESIRED_COLUMN_CHARSET = 'utf8mb4';


    /**
     * Any collations containing this needle will flag as needing fixing
     */
    private const BROKEN_COLUMN_COLLATION_SUBSTR = 'utf8';


    /**
     * Used when the collation or charset is null/empty string for whatever reason
     */
    private const EMPTY_DATA = 'null/empty';


    /**
     * These column data types have a collation/charset combo to worry about
     * Everything else is a datetime/int/enum/etc. which don't have this issue
     */
    private const TEXT_DATA_TYPES = ['varchar', 'text', 'mediumtext', 'longtext', 'enum'];


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_STRATEGIC,
            self::OPT_LONG_INPUT
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {

        /* Some nice colours for diversity
         */
        $green = self::COLOUR_GREEN;
        $yellow = self::COLOUR_YELLOW;
        $red = self::COLOUR_RED;
        $grey = self::COLOUR_GREY;
        $white = self::COLOUR_WHITE;
        $darkGrey = self::COLOUR_DARK_GREY;

        /* Aliases for the heredoc
         */
        $desiredTableCollation = self::DESIRED_TABLE_COLLATION;
        $desiredColumnCollation = self::DESIRED_COLUMN_COLLATION;

        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Finds all the tables (and their columns) and assign their charset to be utf8mb4 where they
were previously utf8. This schema drift is likely caused by failed updates somewhere in an
instance's distant past. 

Get a list of tables and their columns which aren't utf8mb4 by using the planning mode;
update them by using Perform

Legend:
    {$green}This collation is correct
    {$yellow}This collation is null or empty, but maybe that's okay
    {$red}This collation is sort of utf8-ish, looks like it needs fixing
    {$grey}This collation remains unchanged

{$white}All table collations are supposed to be {$green}{$desiredTableCollation}
{$white}All column collations are supposed to be {$green}{$desiredColumnCollation}
{$white}
Usage: php charset.php [--perform]
  

HELP;
            exit;
        }

        $this->getLogger()->info('Scouring the universe');

        $manifest = $this->getManifest();

        /* Generate a human readable strategic overview
         */
        if ($this->getOpt(self::OPT_LONG_STRATEGIC)) {
            echo "\n\n";

            foreach ($manifest as $tableName => $tableData) {
                /* Investigate the table settings
                 */
                if ($tableData['collation'] === self::DESIRED_TABLE_COLLATION) {
                    /* This table collation is correct
                     */
                    echo "{$green}{$tableName} ({$tableData['collation']})\n";
                } elseif ('' === (string)$tableData['collation']) {
                    /* This table collation is unspecified (empty string)
                     * Is that something that needs to change?
                     */
                    echo "{$yellow}{$tableName} (" . self::EMPTY_DATA . ")\n";
                } elseif (strpos($tableData['collation'], self::BROKEN_COLUMN_COLLATION_SUBSTR) > -1) {
                    /* This table collation isutf8-ish, sort of
                     * They need correction
                     */
                    echo "{$red}{$tableName} ({$tableData['collation']})\n";
                } else {
                    /* This table collation is something else entirely
                     * Leave it be
                     */
                    echo "{$grey}{$tableName} ({$tableData['collation']})\n";
                }

                /* Investigate the column's settings
                 */
                foreach ($tableData['columns'] as $columnName => $columnData) {
                    echo str_pad("    {$white}{$columnName}", 50);

                    /* Investigate the data type
                     */
                    echo $this->renderCell($white, $columnData['dataType']);

                    /* Only textual ones matter
                     */
                    if (in_array($columnData['dataType'], self::TEXT_DATA_TYPES, true)) {
                        /* Investigate the column's collation settings
                         */
                        if ($columnData['collation'] === self::DESIRED_COLUMN_COLLATION) {
                            /* This column collation is correct
                             */
                            echo $this->renderCell($green, $columnData['collation']);
                        } elseif ('' === (string)$columnData['collation']) {
                            /* This column collation is unspecified (empty string)
                             * Is that something that needs to change?
                             */
                            echo $this->renderCell($yellow, self::EMPTY_DATA);
                        } elseif (strpos($columnData['collation'], self::BROKEN_COLUMN_COLLATION_SUBSTR) > -1) {
                            /* This column collation isutf8-ish, sort of
                             * They need correction
                             */
                            echo $this->renderCell($red, $columnData['collation']);
                        } else {
                            /* This column collation is something else entirely
                             * Leave it be
                             */
                            echo $this->renderCell($grey, $columnData['collation']);
                        }

                        /* Investigate the column's charset settings
                         */
                        if ($columnData['charset'] === self::DESIRED_COLUMN_CHARSET) {
                            /* This column charset is correct
                             */
                            echo $this->renderCell($green, $columnData['charset']);
                        } elseif ('' === (string)$columnData['charset']) {
                            /* This column charset is unspecified (empty string)
                             * Is that something that needs to change?
                             */
                            echo $this->renderCell($yellow, self::EMPTY_DATA);
                        } elseif (strpos($columnData['charset'], self::BROKEN_COLUMN_COLLATION_SUBSTR) > -1) {
                            /* This column charset isutf8-ish, sort of
                             * They need correction
                             */
                            echo $this->renderCell($red, $columnData['charset']);
                        } else {
                            /* This column charset is something else entirely
                             * Leave it be
                             */
                            echo $this->renderCell($grey, $columnData['charset']);
                        }
                    } else {
                        /* Other data types like int/datetime/decimal etc. come out here
                         * They don't have character encodings/charsets so they will be
                         * grey or dark grey (when null/empty)
                         */
                        if ('' === (string)$columnData['collation']) {
                            echo $this->renderCell($darkGrey, self::EMPTY_DATA);
                        } else {
                            echo $this->renderCell($grey, $columnData['collation']);
                        }

                        if ('' === (string)$columnData['charset']) {
                            echo $this->renderCell($darkGrey, self::EMPTY_DATA);
                        } else {
                            echo $this->renderCell($grey, $columnData['charset']);
                        }
                    }

                    echo "\n";
                }

                echo "\n";
            }
        }

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
            ORDER BY TABLE_NAME            
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
                SELECT DATA_TYPE, COLUMN_NAME, CHARACTER_SET_NAME, COLLATION_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE table_name = :table
                ORDER BY COLUMN_NAME
            ";

            $columns = $this->getDatabaseInternal()->executeQuery($columnsQuery, [
                'table' => $table['TABLE_NAME']
            ])->fetchAll();

            foreach ($columns as $column) {
                $stack[$table['TABLE_NAME']]['columns'][$column['COLUMN_NAME']] = [
                    'dataType' => $column['DATA_TYPE'],
                    'collation' => $column['COLLATION_NAME'],
                    'charset' => $column['CHARACTER_SET_NAME']
                ];
            }
        }

        return $stack;
    }


    /**
     * Return a colour-coded string for the collation and charset columns
     * @param string $colour
     * @param string $val
     * @return string
     */
    private function renderCell(string $colour, string $val): string
    {
        return str_pad("{$colour}{$val}" . self::COLOUR_WHITE, 32);
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

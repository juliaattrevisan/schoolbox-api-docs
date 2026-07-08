#!/usr/bin/env php
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
     * --strategic
     *
     * Get a list of columns and their metadata so you can see what's going on
     */
    private const OPT_LONG_STRATEGIC = 'strategic';


    /**
     * --tablesql
     *
     * Dump a list of sql statements grouped by table name
     * Used to try changes on a per-table basis
     */
    private const OPT_LONG_TABLESQL = 'tablesql';


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
    private const TEXT_DATA_TYPES = ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'enum', 'set'];


    /**
     * sql uses these strings to denote null/not null in column schema
     * Tripped over it a bit, because you need to specify DEFAULT specially
     */
    private const SQL_NULL = 'NULL';
    private const SQL_NOT_NULL = 'NOT NULL';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_STRATEGIC,
            self::OPT_LONG_TABLESQL,
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
        $desiredColumnCharset = self::DESIRED_COLUMN_CHARSET;

        /* At least one of the options must be provided for this to mean anything
         */
        $optionSelected = !\count(array_filter([
            $this->getOpt(self::OPT_LONG_HELP),
            $this->getOpt(self::OPT_LONG_STRATEGIC),
            $this->getOpt(self::OPT_LONG_TABLESQL),
            $this->perform()
        ]));

        if ($optionSelected || $this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Finds all the tables (and their columns) and assign their charset to be utf8mb4 where they
were previously utf8. This schema drift is likely caused by failed updates somewhere in an
instance's distant past.

Usage: php charset.php [--strategic] [--tablesql] [--perform]

1. Strategic View
Get a list of tables and their columns which aren't utf8mb4. Gives a broad overview of the
table collation, column collation + charset, column specification

Legend for the middle two columns in Strategic View:
    {$green}This collation is correct
    {$yellow}This collation is null or empty, but maybe it needs changing
    {$red}This collation is sort of utf8-ish, looks like it needs fixing
    {$white}This collation remains unchanged
    {$darkGrey}This collation is null or empty, but it's supposed to be that way

{$white}All table collations are supposed to be {$green}{$desiredTableCollation}
{$white}All column collations are supposed to be {$green}{$desiredColumnCollation}
{$white}All column charsets are supposed to be {$green}{$desiredColumnCharset}
{$white}
Usage: php charset.php --strategic

2. Planning View
If you want a set of sql to inflict changes, you can get it grouped by table name.

Usage: php charset.php --tablesql

3. Perform
Inflict all sql statements upon the db.

Usage: php charset.php --perform


HELP;
            exit;
        }

        $this->getLogger()->info('Scouring the universe');

        $manifest = $this->getManifest();

        /* A set of reporting info
         * Add some headings
         */
        $strategic = [
            $this->renderTableName($white, 'Table Name', 'Table Collation')
        ];

        $strategic[] =
            str_pad('', 120, '-') .
            "\n" .
            $this->renderColumn($white, 'Column Name') .
            $this->renderCell($grey, 'Collation') .
            $this->renderCell($grey, 'Charset') .
            $this->renderCell($grey, 'Data Type') .
            "\n" .
            str_pad('', 120, '-') .
            "\n";

        /* A set of sql statements which execute changes on tables and columns
         */
        $allSql = [];

        foreach ($manifest as $tableName => $tableData) {
            $rebuildTable = false;
            $sql = [];

            /* This is an sql that updates table collation
             */
            $sql[] = "ALTER TABLE `{$tableName}` COLLATE=" . self::DESIRED_TABLE_COLLATION;

            /* Investigate the table settings
             */
            if ($tableData['collation'] === self::DESIRED_TABLE_COLLATION) {
                /* This table collation is correct
                 */
                $strategic[] = $this->renderTableName($green, $tableName, $tableData['collation']);
            } elseif ('' === (string)$tableData['collation']) {
                /* This table collation is unspecified (empty string)
                 * Is that something that needs to change?
                 */
                $strategic[] = $this->renderTableName($yellow, $tableName, self::EMPTY_DATA);
            } elseif ($this->hasWrongCollation($tableData['collation'])) {
                /* This table collation is utf8-ish, sort of
                 * They need correction
                 */
                $strategic[] = $this->renderTableName($red, $tableName, $tableData['collation']);

                /* The table needed its collation changed, but this will already occur
                 * if any of its columns needs changing. Sometimes columns don't need changing
                 * but the table does. We need to remember this special circumstance and use
                 * this var in conjunction with $rebuildTableAlready
                 */
                $rebuildTable = true;
            } else {
                /* This table collation is something else entirely
                 * Leave it be
                 */
                $strategic[] = $this->renderTableName($white, $tableName, $tableData['collation']);
            }

            /* Investigate the column's settings
             */
            $rebuildTableAlready = false;

            foreach ($tableData['columns'] as $columnName => $columnData) {
                $strategicRow = '';

                /* Decide if this column needs to be rebuilt or not
                 */
                $rebuildColumn = false;

                $strategicRow .= $this->renderColumn($white, $columnName);

                /* Only textual ones matter
                 */
                if (in_array($columnData['dataType'], self::TEXT_DATA_TYPES, true)) {
                    /* Investigate the column's collation settings
                     */
                    if ($columnData['collation'] === self::DESIRED_COLUMN_COLLATION) {
                        /* This column collation is correct
                         */
                        $strategicRow .= $this->renderCell($green, $columnData['collation']);
                    } elseif ('' === (string)$columnData['collation']) {
                        /* This column collation is unspecified (empty string)
                         * Is that something that needs to change?
                         */
                        $strategicRow .= $this->renderCell($yellow, self::EMPTY_DATA);
                    } elseif ($this->hasWrongCollation($columnData['collation'])) {
                        /* This column collation is utf8-ish, sort of
                         * They need correction
                         */
                        $strategicRow .= $this->renderCell($red, $columnData['collation']);

                        /* This column needs to be rebuilt
                         */
                        $rebuildColumn = true;
                        $rebuildTableAlready = true;
                    } else {
                        /* This column collation is something else entirely
                         * Leave it be
                         */
                        $strategicRow .= $this->renderCell($grey, $columnData['collation']);
                    }

                    /* Investigate the column's charset settings
                     */
                    if ($columnData['charset'] === self::DESIRED_COLUMN_CHARSET) {
                        /* This column charset is correct
                         */
                        $strategicRow .= $this->renderCell($green, $columnData['charset']);
                    } elseif ('' === (string)$columnData['charset']) {
                        /* This column charset is unspecified (empty string)
                         * Is that something that needs to change?
                         */
                        $strategicRow .= $this->renderCell($yellow, self::EMPTY_DATA);
                    } elseif ($this->hasWrongCharset($columnData['charset'])) {
                        /* This column charset is utf8-ish, sort of
                         * They need correction
                         */
                        $strategicRow .= $this->renderCell($red, $columnData['charset']);

                        /* This column needs to be rebuilt
                         */
                        $rebuildColumn = true;
                        $rebuildTableAlready = true;
                    } else {
                        /* This column charset is something else entirely
                         * Leave it be
                         */
                        $strategicRow .= $this->renderCell($grey, $columnData['charset']);
                    }
                } else {
                    /* Other data types like int/datetime/decimal etc. come out here
                     * They don't have character encodings/charsets so they will be
                     * grey or dark grey (when null/empty)
                     */
                    if ('' === (string)$columnData['collation']) {
                        $strategicRow .= $this->renderCell($darkGrey, self::EMPTY_DATA);
                    } else {
                        $strategicRow .= $this->renderCell($grey, $columnData['collation']);
                    }

                    if ('' === (string)$columnData['charset']) {
                        $strategicRow .= $this->renderCell($darkGrey, self::EMPTY_DATA);
                    } else {
                        $strategicRow .= $this->renderCell($grey, $columnData['charset']);
                    }
                }

                /* Show the actual sql specification too, because these can be very long
                 * chuck it at the last column
                 */
                $strategicRow .= $this->renderCell($grey, $columnData['columnType']);

                /* If there was a problem with the column, just rebuild it
                 * with the correct collation and charset
                 */
                if ($rebuildColumn) {
                    /* Switch based on column type: enum, varchar, etc.
                     */
                    if (self::SQL_NOT_NULL === $columnData['nullable']) {
                        $sql[] = sprintf(
                            "CHANGE COLUMN `%s` `%s` %s CHARACTER SET '%s' COLLATE '%s' %s",
                            $columnName,
                            $columnName,
                            $columnData['columnType'],
                            self::DESIRED_COLUMN_CHARSET,
                            self::DESIRED_COLUMN_COLLATION,
                            $columnData['nullable'],
                        );
                    } else {
                        $sql[] = sprintf(
                            "CHANGE COLUMN `%s` `%s` %s CHARACTER SET '%s' COLLATE '%s' %s DEFAULT %s",
                            $columnName,
                            $columnName,
                            $columnData['columnType'],
                            self::DESIRED_COLUMN_CHARSET,
                            self::DESIRED_COLUMN_COLLATION,
                            $columnData['nullable'],
                            $columnData['default']
                        );
                    }

                    $allSql[$tableName] = implode(",\n ", $sql);
                }

                $strategicRow .= "\n";

                /* Stack it up
                 */
                $strategic[] = $strategicRow;
            }

            /* Sometimes the table needs updating, but none of its columns changed
             * You might inadvertently end up changing a table collation twice but
             * it really doesn't matter - this is a once-off mutation afaict
             *
             * Anyway. If you $rebuildColumn, then the table collation will already
             * have changed as part of the column update queries. In that case you
             * don't need to $rebuildTable as well, again.
             *
             * That means you only need to update the table collation specially,
             * in this explicit manner, when the table collation needed changing
             * but none of its columns changed.
             */
            if ($rebuildTable && !$rebuildTableAlready) {
                $allSql[$tableName] = $sql[0];
            }
        }

        /* Show the report
         */
        if ($this->getOpt(self::OPT_LONG_STRATEGIC)) {
            echo "\n";

            foreach ($strategic as $row) {
                echo $row;
            }

            echo "\n";
        }

        /* Show sql statements grouped by table name
         */
        if ($this->getOpt(self::OPT_LONG_TABLESQL)) {
            if (\count($allSql)) {
                echo "\n";

                foreach ($allSql as $tableName => $sql) {
                    echo "{$white}{$tableName}\n";
                    echo "    {$grey}{$sql};\n\n";
                }
            } else {
                $this->getLogger()->info('No sql changes required.');
            }
        }

        /* Do it (good luck)
         */
        if ($this->perform()) {
            if (\count($allSql)) {
                /* Turn off the foreigner key stuff
                 */
                $this->getDatabaseInternal()->executeQuery('SET foreign_key_checks = 0', []);

                foreach ($allSql as $tableName => $sql) {
                    $this->getLogger()->debug($sql);

                    try {
                        $this->getDatabaseInternal()->executeQuery($sql, []);
                    } catch (\Throwable $e) {
                        $this->getLogger()->warning($e->getMessage());
                    }
                }

                /* Turn on the foreigner key stuff
                 */
                $this->getDatabaseInternal()->executeQuery('SET foreign_key_checks = 1', []);
            } else {
                $this->getLogger()->info('No sql changes required; none performed');
            }
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
        /* Toggles between schoolbox and schoolboxdev db schemas
         * depending on live/dev instance - it's controlled by
         * something in the Instance Registry
         */
        $db = SQL_db;

        /* Only get the BASE TABLE tables in the 'schoolbox' db
         * (Ignore views and other table types like views)
         */
        $tablesQuery = "
            SELECT TABLE_NAME, TABLE_COLLATION
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = '{$db}'
            AND TABLE_TYPE = 'BASE TABLE'
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
                SELECT
                    COLUMN_NAME,
                    COLUMN_DEFAULT,
                    IS_NULLABLE,
                    DATA_TYPE,
                    COLUMN_TYPE,
                    CHARACTER_MAXIMUM_LENGTH,
                    CHARACTER_SET_NAME,
                    COLLATION_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = :table AND TABLE_SCHEMA = :database
                ORDER BY COLUMN_NAME
            ";

            $columns = $this->getDatabaseInternal()->executeQuery($columnsQuery, [
                'table' => $table['TABLE_NAME'],
                'database' => $db
            ])->fetchAll();

            foreach ($columns as $column) {
                $stack[$table['TABLE_NAME']]['columns'][$column['COLUMN_NAME']] = [
                    'default' => $column['COLUMN_DEFAULT'],
                    'nullable' => $column['IS_NULLABLE'] === 'YES' ? self::SQL_NULL : self::SQL_NOT_NULL,
                    'dataType' => $column['DATA_TYPE'],
                    'maximumLength' => $column['CHARACTER_MAXIMUM_LENGTH'],
                    'collation' => $column['COLLATION_NAME'],
                    'charset' => $column['CHARACTER_SET_NAME'],
                    'columnType' => $column['COLUMN_TYPE']
                ];
            }
        }

        return $stack;
    }


    /**
     * Render a colour-coded string for the table collation
     *
     * @param string $colour
     * @param string $tableName
     * @param string $val
     * @return string
     */
    private function renderTableName(string $colour, string $tableName, string $val): string
    {
        return "\n{$colour}{$tableName} ({$val})\n";
    }


    /**
     * Render an indented column name
     *
     * @param string $colour
     * @param string $columnName
     * @return string
     */
    private function renderColumn(string $colour, string $columnName): string
    {
        return str_pad("    {$colour}{$columnName}", 50);
    }


    /**
     * Return a colour-coded string for the collation and charset columns
     *
     * @param string $colour
     * @param string $val
     * @return string
     */
    private function renderCell(string $colour, string $val): string
    {
        return str_pad("{$colour}{$val}" . self::COLOUR_WHITE, 32);
    }


    /**
     * Decide if a collation was wrong
     *
     * @param string $collation
     * @return bool
     */
    private function hasWrongCollation(string $collation): bool
    {
        /* utf8mb4_bin should stay the way it is
         */
        if ('utf8mb4_bin' === $collation) {
            return false;
        }

        /* If the $collation was utf8-ish, but not utf8mb4
         * Then the collation needs fixing
         */
        if (strpos($collation, self::BROKEN_COLUMN_COLLATION_SUBSTR) > -1) {
            return true;
        }

        /* If the collation was an edgelord case, latin1_swedish_ci
         * This occurs in exactly one place: event_source
         */
        if ('latin1_swedish_ci' === $collation) {
            return true;
        }

        return false;
    }


    /**
     * Decide if a charset was wrong
     *
     * @param string $charset
     * @return bool
     */
    private function hasWrongCharset(string $charset): bool
    {
        /* If the $charset was utf8-ish, but not utf8mb4
         * Then the charset needs fixing
         */
        if (strpos($charset, self::BROKEN_COLUMN_COLLATION_SUBSTR) > -1) {
            return true;
        }

        /* If the $charset was an edgelord case, latin1
         * This occurs in exactly one place: event_source
         */
        if ('latin1' === $charset) {
            return true;
        }

        return false;
    }


    /**
     *
     * @return bool|null
     */
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
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();

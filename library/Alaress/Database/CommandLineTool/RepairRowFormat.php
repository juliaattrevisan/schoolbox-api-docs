<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\CommandLineTool;

use Schoolbox\Application\CliTool;

/**
 * Tool for repairing the row format of all rows in a database.
 *
 * Currently, only converts from "Compact" to "Dynamic", but could be extended
 * to convert from/to others.
 */
class RepairRowFormat extends CliTool\App
{
    // Help options
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

    // Constants for controlling the row format to migrate from/to
    private const ROW_FORMAT_FROM = 'Compact';
    private const ROW_FORMAT_TO   = 'Dynamic';

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP,];
    }

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP,];
    }

    /**
     * @inheritdoc
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            $file       = $this->getToolFilename();
            $green      = self::COLOUR_GREEN;
            $white      = self::COLOUR_WHITE;
            $yellow     = self::COLOUR_YELLOW;
            $formatFrom = self::ROW_FORMAT_FROM;
            $formatTo   = self::ROW_FORMAT_TO;
            echo <<<HELP
NAME:
    Row Format repair tool

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    A tool for repairing the row format of tables in the Schoolbox database.

    The row format for all rows should be "$formatTo": however for some tables
    on some instances this may be "$formatFrom". This tool finds all "$formatFrom"
    tables, and converts them to "$formatTo".

OPTIONS
    $yellow-h, --help$white
        Print this help and exit

    $yellow-p, --perform$white
        Actually perform the repair: otherwise, only print what it would do

HELP;
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }

        $db     = $this->getDatabaseInternal();
        $logger = $this->getLogger();

        if ($this->perform()) {
            $logger->notice('Running in perform mode: tables will be altered');
        } else {
            $logger->notice('Running in dry-run mode: provide the `-p` flag to alter tables');
        }

        $selectTablesToRepairQuery = $db->prepareQuery(
            "
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = :db AND ROW_FORMAT = :formatFrom
                ORDER BY TABLE_NAME
            "
        );

        $selectTablesToRepairQuery->execute(['db' => SQL_db, 'formatFrom' => self::ROW_FORMAT_FROM]);
        $tables = $selectTablesToRepairQuery->fetchColumnArray('TABLE_NAME');
        $selectTablesToRepairQuery->close();

        $logger->info(
            sprintf(
                '%d table(s) have ROW_FORMAT = "%s"',
                count($tables),
                self::ROW_FORMAT_FROM,
            )
        );
        if (empty($tables)) {
            $logger->notice('No tables require repair');
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }

        $successCount = 0;
        $failureCount = 0;
        foreach ($tables as $table) {
            $repairQuery = sprintf(
                'ALTER TABLE %s ROW_FORMAT=' . self::ROW_FORMAT_TO,
                $db->getQuoter()->quoteIdentifier($table),
            );
            if ($this->perform()) {
                $logger->info(
                    sprintf(
                        'Repairing table "%s": setting ROW_FORMAT = "%s"',
                        $table,
                        self::ROW_FORMAT_TO,
                    )
                );
                try {
                    $db->executeDirectly($repairQuery);
                    $successCount++;
                } catch (PDOException $e) {
                    $logger->error($e->getMessage());
                    $failureCount++;
                }
            } else {
                $logger->info(
                    sprintf(
                        'Table "%s" would be repaired: setting ROW_FORMAT = "%s"',
                        $table,
                        self::ROW_FORMAT_TO,
                    )
                );
                $successCount++;
            }
            $logger->debug($repairQuery);
        }

        $logger->notice(
            $this->perform()
                ? sprintf('%d tables repaired, %d tables unable to be repaired', $successCount, $failureCount)
                : sprintf('%d tables would have been repaired: run with the `-p` flag to repair', $successCount)
        );
        exit($failureCount > 0 ? CliTool\Kernel::EXIT_STATUS_APP_ERROR : CliTool\Kernel::EXIT_STATUS_OK);
    }
}

<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\MobileApp\CommandLineTool;

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Throwable;

/**
 * Command-line tool for removing duplicate app registrations, in preparation
 * for applying a new primary key on the `mobile_app_registry` table.
 */
class RemoveDuplicates extends CliTool\App
{
    private const OPT_SHORT_HELP       = 'h';
    private const OPT_LONG_HELP        = 'help';

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [
            self::OPT_SHORT_HELP,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }

    /**
     * @inheritdoc
     */
    public function run(): void
    {
        // Did user request help?
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        $db = $this->getDatabaseInternal();
        $db->beginTransaction();

        $deleteDuplicateQuery = $db->prepareQuery("
            DELETE FROM mobile_app_registry
            WHERE user_id = :userId
            AND   SUBSTR(app_token, 1, 255) = :appToken
            AND   app_id = :appId
            ORDER BY touched_at ASC LIMIT :limit
        ");

        $this->getLogger()->notice(
            $this->perform()
                ? 'Running in live mode: changes will be committed'
                : 'Running in dry-run mode: changes will not be committed'
        );
        try {
            $result = $db
                ->executeQuery(
                    "
                    SELECT
                        user_id,
                        SUBSTR(app_token, 1, 255) AS app_token,
                        app_id,
                        COUNT(*) as duplicate_count
                    FROM mobile_app_registry
                    GROUP BY user_id, SUBSTR(app_token, 1, 255), app_id
                    HAVING duplicate_count > 1
                "
                )
                ->fetchAll();
            $this->getLogger()->notice(sprintf('%d duplicated mobile_app_registry record(s) found', count($result)));
            foreach ($result as $row) {
                $this->getLogger()->info(
                    sprintf(
                        '%d duplicates for user_id: %d, app_id: %d, app_token: "%s" found: deleting all but one',
                        $row['user_id'],
                        $row['app_id'],
                        $row['app_token'],
                        $row['duplicate_count'],
                    )
                );
                $deleteDuplicateQuery->execute(
                    [
                        'userId'   => $row['user_id'],
                        'appId'    => $row['app_id'],
                        'appToken' => $row['app_token'],
                        'limit'    => (int)$row['duplicate_count'] - 1,
                    ]
                );
            }
        } catch (Throwable $e) {
            $this->getLogger()->error(sprintf('Caught error "%s" during deletion: rolling back', $e->getMessage()));
            $db->rollBack();
            exit(CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
        }
        if ($this->perform()) {
            $this->getLogger()->notice('Committing changes');
            $db->commit();
        } else {
            $this->getLogger()->notice('Rolling back changes');
            $db->rollBack();
        }
    }

    /**
     * @inheritdoc
     */
    protected function printHelp(): void
    {
        $file           = $this->getToolFilename();
        $green          = self::COLOUR_GREEN;
        $white          = self::COLOUR_WHITE;
        $yellow         = self::COLOUR_YELLOW;
        $help           = "{$white}NAME:
    Duplicate mobile app registration detection and removal tool

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    Detects and optionally removes duplicate mobile app registrations.

OPTIONS
    $yellow-h, --help$white
        Print this help and exit

    $yellow-p, --perform$white
        If set, actually deletes duplicates - otherwise, performs a dry run.

";
        fwrite(STDOUT, $help);
    }
}

<?php

namespace Schoolbox\MobileApp\CommandLineTool;

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Alaress_Date_Format;

/**
 * Command-line tool for cleaning up old and abandoned app registrations,
 * this is to attempt in reducing the number of requests, in order to
 * reduce the change in hitting the rate limit on remote service.
 */
class CleanUpAbandoned extends CliTool\App
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

        $deleteAbandonedQuery = $db->prepareQuery("
            UPDATE mobile_app_registry
            SET
                deleted_at = :deleted_at
            WHERE user_id = :user_id
            AND app_token = :app_token;
        ");

        $this->getLogger()->notice(
            $this->perform()
                ? 'Running in live mode: registrations will be soft deleted.'
                : 'Running in dry-run mode: registrations will not be soft deleted.'
        );

        $getAbandonedQuery = "
            SELECT
                user_id,
                app_token,
                touched_at
            FROM mobile_app_registry
            WHERE touched_at < DATE_SUB(CURDATE(), INTERVAL 1 YEAR);
        ";

        try {
            $result = $db->executeQuery($getAbandonedQuery)->fetchAll();
            $this->getLogger()->notice(
                sprintf(
                    '%d abandoned mobile_app_registry record(s) found.',
                    count($result)
                )
            );
            foreach ($result as $row) {
                $this->getLogger()->info(
                    sprintf(
                        '%s of user_id %d was last used at %s: Deleting.',
                        $row['app_token'],
                        $row['user_id'],
                        $row['touched_at']
                    )
                );
                $deleteAbandonedQuery->bindInputs(
                    [
                        'deleted_at' => Alaress_Date_Format::now()->getDatabase(),
                        'user_id' => $row['user_id'],
                        'app_token' => $row['app_token'],
                    ]
                );
                $deleteAbandonedQuery->execute();
            }
        } catch (\PDOException $e) {
            $this->getLogger()->error(
                sprintf(
                    'Caught error "%s" during deletion: rolling back.',
                    $e->getMessage()
                )
            );
            $db->rollBack();
            exit(CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
        }

        if ($this->perform()) {
            $this->getLogger()->notice('App registration(s) soft deleted.');
            $db->commit();
        } else {
            $this->getLogger()->notice('Dry-run mode. Nothing was deleted. Rolling back.');
            $db->rollBack();
        }
    }

    /**
     * Print tool information
     *
     * @return void
     */
    protected function printHelp(): void
    {
        $file   = $this->getToolFilename();
        $green  = self::COLOUR_GREEN;
        $white  = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;
        $help   = "{$white}NAME:
    Abandoned mobile app registration detection and clean up tool

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    Detects old and abandoned mobile app registrations that was last `touched_at` over a year.

OPTIONS
    $yellow-h, --help$white
        Print this help and exit

    $yellow-p, --perform$white
        If set, actually deletes abandoned registrations - otherwise, performs a dry run.

";

        fwrite(STDOUT, $help);
    }
}

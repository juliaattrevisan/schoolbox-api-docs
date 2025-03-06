<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

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
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const LOCK_NAME = 'summative-report-graph-preselect';


    /**
     * Decide how long this should be based on your usage
     * You might have to adjust it, but it should cover the cost of an iteration
     */
    private const LOCK_TTL = 60;


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


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
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Summative Report

Preselects the "Graph" checkbox if you enabled "Show" for a given worktype.
This always runs in Perform mode.

Usage: 01-graph-preselect.php


HELP;
            return;
        }

        /* Decide how loudly to announce lock acquisition failure
         *
         * Simply using $lock = $this->acquireLock() will keep you safe, even if you don't
         * specifically check for acquisition
         */
        try {
            $lock = $this->acquireLock(self::LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            return;
        }

        try {
            /* Used to update multiple reports
             */
            $updateStatement = $this->getDatabaseInternal()->prepare("
                UPDATE summative_report
                SET options = :options
                WHERE id = :id
                LIMIT 1
            ");

            /* Find all the candidate reports
             */
            $summativeReports = $this->getDatabaseInternal()->prepare("
                SELECT *
                FROM summative_report
                WHERE deleted_at IS NULL
            ");

            $summativeReports->execute();

            while ($report = $summativeReports->fetch(\PDO::FETCH_ASSOC)) {
                $this->refreshLock($lock);

                /* Awkwardly, the summative report instance required you to have a
                 * hydrated instance to decode the options payload
                 */
                $decodedOptions = json_decode($report['options'], true, 512, JSON_THROW_ON_ERROR);

                /* Traverse each of the worktypes that were enabled and set their Graph
                 * option, so that users have it preselected upon migration
                 */
                $hasChanges = false;

                foreach ($decodedOptions['summary']['workType'] as $workTypeId => &$option) {
                    if (isset($decodedOptions['summary']['cohort']) && $decodedOptions['summary']['cohort']) {
                        $option['graph'] = 1;
                        $hasChanges = true;
                    }
                }

                foreach ($decodedOptions['class']['workType'] as $workTypeId => &$option) {
                    if (isset($decodedOptions['class']['cohort']) && $decodedOptions['class']['cohort']) {
                        $option['graph'] = 1;
                        $hasChanges = true;
                    }
                }

                /* Only persist if necessary
                 */
                if (!$hasChanges) {
                    $this->getLogger()->info(sprintf(
                        'Summative report "%s" (id %s) requires no changes; skipping',
                        $report['title'],
                        $report['id']
                    ));

                    continue;
                }

                /* Re-encode it
                 */
                $updatedOptions = json_encode($decodedOptions, JSON_THROW_ON_ERROR);

                /* Save it
                 */
                $this->getLogger()->info(sprintf(
                    'Summative report "%s" (id %s) updated to preselect Graph',
                    $report['title'],
                    $report['id']
                ));

                $updateStatement->execute([
                    'id' => $report['id'],
                    'options' => $updatedOptions,
                ]);
            }
        } catch (\Alaress\Lock\LockRefreshException $e) {
            /* Lock TTL expired; just leave politely
             */
            return;
        }

        /* Announce conclusion
         */
        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }

        /* Announce lock release
         */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
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
(new Schoolbox\Application\CliTool\Kernel($app, $options, true))->runApp();

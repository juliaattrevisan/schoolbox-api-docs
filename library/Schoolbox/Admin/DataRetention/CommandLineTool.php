<?php

/**
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\GenericCollection;
use Symfony\Component\Lock\LockInterface;

/**
 * Command-line tool for Schoolbox's data retention system.
 */
class CommandLineTool extends CliTool\App
{
    /**
     * The name of the lock this tool acquires.
     */
    public const string LOCK_NAME = 'dataRetention';

    /**
     * By default, the lock will be held for 30 seconds.
     */
    private const int LOCK_TIME = 30;

    /**
     * Print help
     */
    private const string OPT_SHORT_HELP = 'h';
    private const string OPT_LONG_HELP  = 'help';


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
        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            echo <<<HELP
NAME
    ./tools/dataRetention/process.php - hard deletes data that matches specified data retention criteria and has already been soft deleted

SYNOPSIS
    ./tools/dataRetention/process.php [OPTION]...

DESCRIPTION
    This tool hard deletes, or permanently removes, any soft-deleted records that fulfil the specific data retention criteria.


OPTIONS
    -h, --help:              Print this help text and exit
    -p:                      perform: *without* this flag, this tool will only do a dry run, outputting what would be
                             deleted
    -v[v[v]]:                verbosity: how much activity will be logged

HELP;
            exit;
        }

        $logger = $this->getLogger();

        $logger->info(
            sprintf(
                "Attempting to acquire process lock (%ds): will release at end",
                self::LOCK_TIME,
            ),
        );
        try {
            $lock = $this->acquireLock(self::LOCK_NAME, self::LOCK_TIME);
        } catch (\RuntimeException $e) {
            $logger->error(
                sprintf(
                    'Failed to acquire process lock "%s": %s',
                    self::LOCK_NAME,
                    $e->getMessage(),
                ),
            );
            return;
        }
        register_shutdown_function(fn () => $this->releaseLock($lock));


        // build the bus for all the data retention tasks
        /** @var GenericCollection<IStrategy> $bus */
        $bus = new GenericCollection(IStrategy::class);

        // add bus for cmsLogExample // clear things from the cmsLog older than 1 year (possible options are 1 Day, 30 Days, 365 Days, <null>)
//        $cmsLogRetentionPeriod = $this->getConfig()->get('dataRetention_cmsLogRetentionPeriod');
//        $cmsLogRetentionPeriod = '365';  // example data retention period get from the config table
//        $bus->add(new CmsLogExample($logger, $this->perform(), $this->getDatabaseInternal(), $lock, $this->getConfig()));

        // if we have functions, run them
        if (count($bus) > 0) {
            $logger->info('Running data retention strategies');
            /**
             * @var IStrategy $strategy
             */
            foreach ($bus as $busNumber => $strategy) {
                // log a helpful counter to know where we are up to
                $logger->notice(sprintf("[%s/%s] Running %s", $busNumber + 1, count($bus), $strategy->getRetentionLabel()));

                $strategy->process();

                // refresh lock, depending on the data retention queries this may have taken a while...
                $lock->refresh();
            }
        } else {
            $logger->notice('No data retention strategies to run');
        }

        exit(CliKernelAbstract::EXIT_STATUS_OK);
    }

    /**
     * Release lock on shutdown.
     *
     * @param LockInterface $lock
     * @return void
     */
    private function releaseLock(LockInterface $lock): void
    {
        $lock->release();
        $this->getLogger()->notice('Data Retention cleanup complete: released process lock');
    }
}

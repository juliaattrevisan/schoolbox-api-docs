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
     * --max-time={max-time}: run for this many seconds before exiting.
     */
    private const OPT_LONG_MAX_TIME = 'max-time:';

    /**
     * Default max time: 1 hour.
     */
    private const DEFAULT_MAX_TIME = 60 * 60 * 1;

    /**
     * --chunk={chunk-size}: process this many records per chunk.
     */
    private const OPT_LONG_CHUNK_SIZE = 'chunk:';

    /**
     * Print help
     */
    private const string OPT_SHORT_HELP = 'h';
    private const string OPT_LONG_HELP  = 'help';

    /**
     * -n: Count of loops to run for each strategy, default StrategyBase::DEFAULT_CHUNK_LOOPS
     */
    private const string OPT_SHORT_LOOP_COUNT = 'n:';


    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [
            self::OPT_SHORT_HELP,
            self::OPT_SHORT_LOOP_COUNT,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_CHUNK_SIZE,
            self::OPT_LONG_MAX_TIME,
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
    -n                       Count of loops to run for each strategy, default 3, set to 1 to only loop once
    --max-time={max-time}:   process entries in chunks until the script has been running for this many seconds.
                             Defaults to 3600 (1 hours).
    --chunk={chunk-size}:    process this many entries per chunk: defaults to 1000.
    -p:                      perform: *without* this flag, this tool will only do a dry run, outputting what would be
                             deleted
    -v[v[v]]:                verbosity: how much activity will be logged

HELP;
            exit;
        }

        $logger = $this->getLogger();

        // Hold process lock for max-time,
        // plus a little longer to finish actually deleting files
        $maxTime = $this->getOptAsInt(self::OPT_LONG_MAX_TIME, self::DEFAULT_MAX_TIME);
        if ($maxTime <= 0) {
            $maxTime = self::DEFAULT_MAX_TIME;
        }
        $endTime = time() + $maxTime;

        $logger->info(
            sprintf(
                "Attempting to acquire process lock. will release at end, or after '%s', whichever comes first.",
                date('Y-m-d H:i:s', $endTime)
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

        // get the number of loops to run for each strategy, default 3
        $loopCount = $this->getOptAsInt(self::OPT_SHORT_LOOP_COUNT, StrategyBase::DEFAULT_CHUNK_LOOPS);
        if ($loopCount <= 0) {
            $loopCount = StrategyBase::DEFAULT_CHUNK_LOOPS;
        }

        // get the chunk size, default 1000
        $chunkSize = $this->getOptAsInt(self::OPT_LONG_CHUNK_SIZE, StrategyBase::DEFAULT_CHUNK_SIZE);
        if ($chunkSize <= 0) {
            $chunkSize = StrategyBase::DEFAULT_CHUNK_SIZE;
        }


        // build the bus for all the data retention tasks
        /** @var GenericCollection<IStrategy> $bus */
        $bus = new GenericCollection(IStrategy::class);

        // add bus for cmsLogExample // clear things from the cmsLog older than 1 year (possible options are 1 Day, 30 Days, 365 Days, <null>)
//        $bus->add(new CmsLogExample($logger, $this->perform(), $this->getDatabaseInternal(), $lock, $this->getConfig(), $endTime, $loopCount, $chunkSize));
        $bus->add(new MomentsRetention(
            $logger,
            $this->perform(),
            $this->getDatabaseInternal(),
            $lock,
            $this->getConfig(),
            $endTime,
            $loopCount,
            $chunkSize,
        ));

        // if we have functions, run them
        if (count($bus) > 0) {
            $logger->info('Running data retention strategies');
            /**
             * @var IStrategy $strategy
             */
            foreach ($bus as $busNumber => $strategy) {
                // cache a timestamp to keep track of how long the strat runs for
                $start = microtime(true);
                // log a helpful counter to know where we are up to
                $logger->notice(sprintf("[%s/%s] Running %s", $busNumber + 1, count($bus), $strategy->getRetentionLabel()));

                $strategy->process();

                // log how long the strategy took to run
                $duration = microtime(true) - $start;
                $logger->notice(sprintf("[%s/%s] Finished %s in %ds", $busNumber + 1, count($bus), $strategy->getRetentionLabel(), $duration));

                // if we have passed the end time, stop for now and continue tomorrow
                if (time() > $endTime) {
                    $logger->notice(sprintf("End time of '%s' reached, stopping for now", date('Y-m-d H:i:s', $endTime)));
                    exit(CliKernelAbstract::EXIT_STATUS_OK);
                }

                // refresh lock, depending on the data retention queries this may have taken a while...
                $lock->refresh();
            }


            // log that we have finished and release the lock
            $logger->notice("Finished processing all chunks");
            $this->releaseLock($lock);
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

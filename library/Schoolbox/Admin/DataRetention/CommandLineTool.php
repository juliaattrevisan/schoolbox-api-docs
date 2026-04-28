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
     * Pick the strat(s) to execute
     */
    private const OPT_LONG_STRATS = 'strats::';

    /**
     * Manually override the retention period for a strategy, for testing purposes (number of days (same format as the admin config value)
     */
    private const OPT_RETENTION_PERIOD = 'retention-period:';

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
            self::OPT_LONG_STRATS,
            self::OPT_RETENTION_PERIOD,
        ];
    }

    /**
     * @inheritdoc
     */
    public function run(): void
    {
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

        // register shutdown function to release lock when the script ends, either naturally or through an error, to avoid leaving a stale lock
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

        // get strats to run, default all, and filter the bus to only those strats
        $validStrats = array_map(
            fn (IStrategy $strategy) => $strategy->getStrategyName(),
            iterator_to_array($bus)
        );

        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            // release this manually, so we don't trigger the notice in the shutdown function
            $lock->release();
            $this->getHelp($validStrats);
        }

        /**
         * get the strats to run, default all. This is a csv of strats, e.g. "cmslogexample,moments". If the result is an empty string, then we will run all strats.
         */
        $selectedStrats = [];
        $csvStrats = (string)$this->getOpt(self::OPT_LONG_STRATS);
        if ('' !== $csvStrats) {
            $selectedStrats = $this->validateStrats($csvStrats, $validStrats);
        }

        // if set override the retention period for all strats, for testing purposes
        $retentionPeriod = $this->getOptAsInt(self::OPT_RETENTION_PERIOD);

        // log the settings we are running with
        $logger->notice(sprintf("Running %s:", $this->perform() ? "with perform" : "in dry-run mode"));
        $logger->notice(sprintf("Script will end at or before '%s'", date('Y-m-d H:i:s', $endTime)));
        $logger->notice(sprintf("Chunk size: %s", $chunkSize));
        $logger->notice(sprintf("Loop count: %s", $loopCount));
        if (null !== $retentionPeriod) {
            // if in the future for some reason, log and return null to avoid deleting everything
            if ($retentionPeriod < 0) {
                $logger->notice('Negative retention period, exiting');
                return;
            }
            $logger->notice(sprintf("Overriding retention period to '%s' days", $retentionPeriod));
        }


        // filter buses to those we have requested
        // filter should only allow the strats we have requested, but if the result is an empty string, then we will run all strats
        if (count($selectedStrats) > 0) {
            $bus = $bus->filter(fn (IStrategy $strategy) => in_array($strategy->getStrategyName(), $selectedStrats, true));
            $logger->notice(sprintf("Filtering to '%s' enable strats:", count($selectedStrats)));
            $logger->notice(sprintf("    Enabled strats %s ", implode(', ', $selectedStrats)));
        } else {
            $logger->notice("No strats specified, running all strats");
        }

        // if we have functions, run them
        if ($bus->count() > 0) {
            $logger->info('Running data retention strategies');
            /**
             * @var IStrategy $strategy
             */
            foreach ($bus as $busNumber => $strategy) {
                // cache a timestamp to keep track of how long the strat runs for
                $start = microtime(true);
                // log a helpful counter to know where we are up to
                $logger->notice(sprintf("[%s/%s] Running %s", $busNumber + 1, count($bus), $strategy->getRetentionLabel()));

                // if retention period override is set, override the strategy's retention period for this run, for testing purposes
                if (null !== $retentionPeriod) {
                    $strategy->setRetentionPeriod($retentionPeriod);
                }

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


            // log that we have finished
            $logger->notice("Finished processing all strats");
            /* lock will be released by the shutdown
             * register_shutdown_function(fn () => $this->releaseLock($lock));
             */
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
        // if lock exists
        if ($lock->isAcquired()) {
            $this->getLogger()->notice('Data Retention cleanup complete: released process lock');
        }
        $lock->release();
    }

    /**
     * Validate the strats provided in the csv against the valid strats, and return an array of the valid strats to run.
     * This will ignore any invalid strats provided in the csv, and will return an empty array if no valid strats are found.
     * @param string $csvStrats
     * @param array  $validStrats
     * @return array
     */
    private function validateStrats(string $csvStrats, array $validStrats): array
    {
        // Split csv args into individual pieces
        $temp = explode(',', $csvStrats);

        /* Cast all the elements in $values to strings
         * because loosely typed data is poopy
         */
        $temp = array_map('strval', $temp);

        /* Lowercase everything
         */
        $temp = array_map('strtolower', $temp);

        /* Trim all the strings in the set
         * because doing manual trimming is boring
         */
        $temp = array_map('trim', $temp);

        /* Remove anything that doesn't also appear in $validStrats
         * and ignore those
         */
        $temp = array_intersect($validStrats, $temp);

        /* Store only the valid strats chosen
         */
        return array_values($temp);
    }

    /**
     * @param array $validStrats a list of valid strats to run, used for help text. If empty, will just show the option as is.
     * @return void
     */
    private function getHelp(array $validStrats = []): void
    {
        $csvStrats = implode(', ', $validStrats);
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
    --strats=                A csv of strats to hard delete.  Valid strats are {{$csvStrats}}. If not provided, will run all strats.
                             e.g. hard delete only learning moment stuff: ./tools/dataRetention/process.php --strats=moments
    --retention-period=      Manually override the retention period for a strategy, for testing purposes (number of days, same format as the admin config value)
    -p, --perform:           perform: *without* this flag, this tool will only do a dry run, outputting what would be
                             deleted
    -v[v[v]]:                verbosity: how much activity will be logged

HELP;
        exit;
    }
}

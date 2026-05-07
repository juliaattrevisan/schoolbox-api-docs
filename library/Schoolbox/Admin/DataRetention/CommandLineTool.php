<?php

/**
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Application\CliTool;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\IConfigLoader;
use Psr\Log\LoggerInterface;
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
     * -n: Count of loops to run for each strategy, default Strategy\Base::DEFAULT_CHUNK_LOOPS
     */
    private const string OPT_SHORT_LOOP_COUNT = 'n:';

    /**
     * Pick the strat(s) to execute
     */
    private const OPT_LONG_STRATS = 'strats::';

    /**
     * Manually override the retention period for all strategies, for testing purposes.
     * Value is a whole number of days in the same format as the admin config value:
     *  - positive N: hard-delete data soft-deleted more than N days ago ("N days ago" cutoff)
     *  - 0: treated as a safety guard; the tool will log a warning and exit without deleting anything
     *  - negative: invalid; the tool will log a warning and exit without deleting anything
     *
     * Only whole numbers are accepted; non-integer values will be rejected.
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

        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            // release this manually, so we don't trigger the notice in the shutdown function
            $this->getHelp();
            return;
        }

        // Validate any retention period override early so we can fail fast before doing work.
        try {
            $retentionOverride = $this->getOpt(self::OPT_RETENTION_PERIOD);
            if ((null !== $retentionOverride) && !new ValidRetentionPeriod()->isSatisfiedBy($retentionOverride)) {
                $logger->warning(sprintf("Invalid retention period override '%s'. Exiting without deleting anything.", $this->getOpt(self::OPT_RETENTION_PERIOD)));
                return;
            }
            $retentionPeriod = new RetentionPeriod($this->getOptAsInt(self::OPT_RETENTION_PERIOD));
        } catch (SchoolboxException $e) {
            $logger->warning(sprintf("Invalid retention period override '%s': %s. Exiting without deleting anything.", $this->getOpt(self::OPT_RETENTION_PERIOD), $e->getMessage()));
            return;
        }

        // Determine script end time: run for at most max-time seconds from now.
        $maxTime = $this->getOptAsInt(self::OPT_LONG_MAX_TIME, self::DEFAULT_MAX_TIME);
        if ($maxTime <= 0) {
            $maxTime = self::DEFAULT_MAX_TIME;
        }
        $endTime = time() + $maxTime;
        $endDateTime = new \Alaress_Date_Format($endTime, 'U')->getDatabase();

        $logger->info(
            sprintf(
                "Attempting to acquire process lock. will release at end, or after '%s', whichever comes first.",
                $endDateTime
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
        $loopCount = $this->getOptAsInt(self::OPT_SHORT_LOOP_COUNT, Strategy\Base::DEFAULT_CHUNK_LOOPS);
        if ($loopCount <= 0) {
            $loopCount = Strategy\Base::DEFAULT_CHUNK_LOOPS;
        }

        // get the chunk size, default 1000
        $chunkSize = $this->getOptAsInt(self::OPT_LONG_CHUNK_SIZE, Strategy\Base::DEFAULT_CHUNK_SIZE);
        // prevent negative chunks
        if ($chunkSize <= 0) {
            $chunkSize = Strategy\Base::DEFAULT_CHUNK_SIZE;
        }
        // prevent insane large chunks, reset to the max
        if ($chunkSize > Strategy\Base::MAX_CHUNK_SIZE) {
            $chunkSize = Strategy\Base::MAX_CHUNK_SIZE;
        }

        /**
         * get the strats to run, default all. This is a csv of strats, e.g. "moments,myFiles". If the result is an empty string, then we will run all strats.
         */
        $csvStrats = (string)$this->getOpt(self::OPT_LONG_STRATS);
        $validStrats = new ValidStrats($csvStrats);
        // if no strats are valid, log and return
        if (count($validStrats->getFilteredStrats()) !== 0) {
            $logger->notice(sprintf("Filtering to '%s' enabled strats:", count($validStrats->getFilteredStrats())));
            $logger->notice(sprintf("    Enabled strats %s ", implode(', ', $validStrats->getFilteredStrats())));
        } else {
            $logger->notice("No strats specified, running all strats");
        }


        // build the bus for all the data retention tasks
        /** @var Strategy\IStrategy[] $bus */
        $bus = [];

        if ($validStrats->isStratEnabled(ValidStrats::MOMENTS)) {
            $bus[] = new Strategy\Moments(
                $logger,
                $this->perform(),
                $this->getDatabaseInternal(),
                $lock,
                $this->getConfig(),
                $endTime,
                clone $retentionPeriod,
                $loopCount,
                $chunkSize,
            );
        }
        if ($validStrats->isStratEnabled(ValidStrats::MYFILES)) {
            $bus[] = new Strategy\MyFiles(
                $logger,
                $this->perform(),
                $this->getDatabaseInternal(),
                $lock,
                $this->getConfig(),
                $endTime,
                clone $retentionPeriod,
                $loopCount,
                $chunkSize,
            );
        }
        if ($validStrats->isStratEnabled(ValidStrats::STUDENTSUBMISSIONS)) {
            $bus[] = new Strategy\StudentSubmissions(
                $logger,
                $this->perform(),
                $this->getDatabaseInternal(),
                $lock,
                $this->getConfig(),
                $endTime,
                clone $retentionPeriod,
                $loopCount,
                $chunkSize,
            );
        }

        $bus = $this->buildStrategies(
            $validStrats,
            $logger,
            $this->perform(),
            $this->getDatabaseInternal(),
            $lock,
            $this->getConfig(),
            $endTime,
            $retentionPeriod,
            $loopCount,
            $chunkSize,
        );

        // log the settings we are running with
        $logger->notice(sprintf("Running %s:", $this->perform() ? "with perform" : "in dry-run mode"));
        $logger->notice(sprintf("Script will end at or before '%s'", $endDateTime));
        $logger->notice(sprintf("Chunk size: %s", $chunkSize));
        $logger->notice(sprintf("Loop count: %s", $loopCount));
        if ($retentionPeriod->getRetentionDays()) {
            $logger->notice(sprintf("Overriding retention period to '%s' days", $retentionPeriod->getRetentionDays()));
        }

        // if we have functions, run them
        if (count($bus) > 0) {
            $logger->info('Running data retention strategies');
            /**
             * @var Strategy\IStrategy $strategy
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
                    $logger->notice(sprintf("End time of '%s' reached, stopping for now", $endDateTime));
                    return;
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
    }

    /**
     * Build the ordered list of enabled retention strategies to process.
     *
     * This function allows the tests in DataRetention/Test/CommandLineToolStrategyBusTest to be done without having
     * to call CommandLineTool::run(), as so avoids pulling in many dependencies that are unrelated to the tests.
     *
     * @return Strategy\IStrategy[]
     */
    protected function buildStrategies(
        ValidStrats $validStrats,
        LoggerInterface $logger,
        bool $perform,
        IConnector $database,
        LockInterface $lock,
        IConfigLoader $config,
        int $endTime,
        RetentionPeriod $retentionPeriod,
        int $loopCount,
        int $chunkSize,
    ): array {
        $bus = [];

        if ($validStrats->isStratEnabled(ValidStrats::MOMENTS)) {
            $bus[] = new Strategy\Moments(
                $logger,
                $perform,
                $database,
                $lock,
                $config,
                $endTime,
                clone $retentionPeriod,
                $loopCount,
                $chunkSize,
            );
        }

        if ($validStrats->isStratEnabled(ValidStrats::MYFILES)) {
            $bus[] = new Strategy\MyFiles(
                $logger,
                $perform,
                $database,
                $lock,
                $config,
                $endTime,
                clone $retentionPeriod,
                $loopCount,
                $chunkSize,
            );
        }

        if ($validStrats->isStratEnabled(ValidStrats::EPORTFOLIO)) {
            $bus[] = new Strategy\Eportfolio(
                $logger,
                $perform,
                $database,
                $lock,
                $config,
                $endTime,
                clone $retentionPeriod,
                $loopCount,
                $chunkSize,
            );
        }

        return $bus;
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
     * @return void
     */
    private function getHelp(): void
    {
        // Build a multi-line, human-readable representation of the available
        // strategies. STRATS_WHITELIST can be very long (30+ items), so we
        // chunk it into lines of up to 10 items to avoid a single very long
        // help line which is hard to read in terminals.
        $chunks = array_chunk(ValidStrats::STRATS_WHITELIST, 10);
        $lines = [];
        foreach ($chunks as $chunk) {
            $lines[] = '        ' . implode(', ', $chunk);
        }
        $stratsHelp = implode("\n", $lines);
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
    --chunk={chunk-size}:    process this many entries per chunk: defaults to 1000. (max 10000)
    --strats=                A csv of strats to hard delete. If not provided, will run all strats.
                             Available strats (chunked for readability):
{$stratsHelp}
                             e.g. hard delete only learning moment stuff: ./tools/dataRetention/process.php --strats=moments
    --retention-period=      Manually override the retention period for all strategies, for testing purposes (whole number of days, same format as the admin config value).
                             A positive integer is expected.
    -p, --perform:           perform: *without* this flag, this tool will only do a dry run, outputting what would be
                             deleted
    -v[v[v]]:                verbosity: how much activity will be logged

HELP;
    }
}

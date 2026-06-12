<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\ModerationQueue;

use Schoolbox\Application\CliTool;
use Symfony\Component\Lock\LockInterface;

/**
 * Placeholder command-line tool for future AI moderation queueing.
 */
class CommandLineTool extends CliTool\App
{
    public const string LOCK_NAME = 'aiModerationQueue';

    private const int LOCK_ALLOWANCE = 300;
    private const int DEFAULT_LIMIT = 1000;
    private const int DEFAULT_BATCH_SIZE = 100;
    private const int DEFAULT_MAX_TIME = 1800;

    private const string OPT_SHORT_HELP = 'h';
    private const string OPT_SHORT_LIMIT = 'n:';

    private const string OPT_LONG_HELP = 'help';
    private const string OPT_LONG_BATCH_SIZE = 'batch:';
    private const string OPT_LONG_MAX_TIME = 'max-time:';

    /**
     * Provides the short options handled by this command.
     *
     * The shared CLI kernel handles common options such as `-p` and `-v`; this command only declares its own
     * command-specific flags here.
     *
     * @return string[]
     */
    public function getShortOpts(): array
    {
        return [
            self::OPT_SHORT_HELP,
            self::OPT_SHORT_LIMIT,
        ];
    }

    /**
     * Provides the long options handled by this command.
     *
     * @return string[]
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_BATCH_SIZE,
            self::OPT_LONG_MAX_TIME,
        ];
    }

    /**
     * Runs the placeholder AI moderation queue scheduler.
     *
     * This command intentionally does not fetch OpenAI logs, enqueue work, or call moderation APIs yet. It creates the
     * bounded and lock-protected scheduled entry point so follow-up moderation tickets can add that behaviour in one
     * place.
     *
     * @return void
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            echo <<<HELP
NAME
    moderation-queue.php - queues AI conversations for moderation review

SYNOPSIS
    moderation-queue.php [OPTION]...

DESCRIPTION
    Placeholder scheduler for future AI moderation queueing work. This command currently acquires a lock, calculates
    bounded batch settings, logs where future moderation queueing code will be added, and exits successfully.

OPTIONS
    -h, --help:              Print this help text and exit
    -p:                      perform: without this flag, this tool runs in dry-run mode
    -v[v[v]]:                verbosity: how much activity will be logged
    -n={limit}:              maximum number of candidate sessions to consider, defaults to 1000
    --batch={batch-size}:    process this many candidate sessions per batch, defaults to 100
    --max-time={max-time}:   run for this many seconds before exiting, defaults to 1800

HELP;
            return;
        }

        $logger = $this->getLogger();

        $limit = $this->normalisePositiveInt(
            $this->getOptAsInt(self::OPT_SHORT_LIMIT, self::DEFAULT_LIMIT),
            self::DEFAULT_LIMIT,
        );
        $batchSize = $this->normalisePositiveInt(
            $this->getOptAsInt(self::OPT_LONG_BATCH_SIZE, self::DEFAULT_BATCH_SIZE),
            self::DEFAULT_BATCH_SIZE,
        );
        $maxTime = $this->normalisePositiveInt(
            $this->getOptAsInt(self::OPT_LONG_MAX_TIME, self::DEFAULT_MAX_TIME),
            self::DEFAULT_MAX_TIME,
        );
        $endTime = time() + $maxTime;

        $logger->notice(sprintf('Running AI moderation queue placeholder %s.', $this->perform() ? 'with perform' : 'in dry-run mode'));
        $logger->notice(sprintf('Limit: %d; batch size: %d; max time: %d seconds.', $limit, $batchSize, $maxTime));

        try {
            $lock = $this->acquireLock(self::LOCK_NAME, $maxTime + self::LOCK_ALLOWANCE);
        } catch (\RuntimeException $e) {
            $logger->error(sprintf('Failed to acquire process lock "%s": %s', self::LOCK_NAME, $e->getMessage()));
            return;
        }

        register_shutdown_function($this->releaseLock(...), $lock);

        $processed = 0;
        while ($processed < $limit) {
            if (time() > $endTime) {
                $logger->notice('Maximum runtime reached, stopping for now.');
                return;
            }

            $remaining = $limit - $processed;
            $currentBatchSize = min($batchSize, $remaining);

            $queued = $this->processPlaceholderBatch($currentBatchSize);
            if ($queued === 0) {
                $logger->notice('No AI moderation queue candidates found.');
                return;
            }

            $processed += $queued;
            $lock->refresh();
        }

        $logger->notice(sprintf('Processed placeholder limit of %d candidate sessions.', $processed));
    }

    /**
     * Normalises operator-provided integer limits to a positive value.
     *
     * Non-positive values fall back to the command default so a malformed cron override cannot accidentally create an
     * unbounded placeholder run.
     *
     * @param int $value   The operator-provided value.
     * @param int $default The safe default value to use when the provided value is not positive.
     * @return int
     */
    private function normalisePositiveInt(int $value, int $default): int
    {
        if ($value <= 0) {
            return $default;
        }

        return $value;
    }

    /**
     * Processes one placeholder batch.
     *
     * The method currently returns zero so the scheduler exits cleanly after proving the bounded loop, logging, and
     * lock lifecycle. Future moderation tickets should replace the placeholder body with candidate lookup and queueing.
     *
     * @param int $batchSize The maximum number of candidate sessions that future code should process in this batch.
     * @return int The number of candidate sessions queued by this placeholder batch.
     */
    private function processPlaceholderBatch(int $batchSize): int
    {
        $this->getLogger()->info(sprintf('Planning AI moderation queue batch of up to %d candidate sessions.', $batchSize));

        // Future SA-21802 work: fetch candidate OpenAI sessions or conversations for this batch.
        // Future SA-21802 work: skip sessions that are already queued or moderated for their latest message.
        // Future SA-21802 work: enqueue moderation work instead of processing moderation directly in this scheduler.
        // Future SA-21801 work: worker loads conversation context and sends it to the AI moderation agent.
        // Future SA-21803 work: persist moderation outcome, flagged state, and last moderated timestamp.

        return 0;
    }

    /**
     * Releases the command lock on shutdown.
     *
     * @param LockInterface $lock The process lock acquired for this command run.
     * @return void
     */
    private function releaseLock(LockInterface $lock): void
    {
        $lock->release();
        $this->getLogger()->info('AI moderation queue placeholder complete: released process lock.');
    }
}

<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\ModerationQueue;

use Schoolbox\Application\CliTool;
use Schoolbox\OpenAi\Moderation\Event\Mapper as ModerationEventMapper;
use Schoolbox\OpenAi\Session\Log\Mapper as SessionLogMapper;
use Symfony\Component\Lock\LockInterface;

/**
 * Command-line tool for processing AI moderation queue candidates.
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

    private readonly \Closure $clock;

    /**
     * @param CandidateFinder|null $candidateFinder Optional candidate finder, primarily for tests.
     * @param Moderator|null       $moderator       Optional moderator, primarily for tests.
     * @param callable|null        $clock           Optional clock, primarily for tests.
     */
    public function __construct(
        private ?CandidateFinder $candidateFinder = null,
        private ?Moderator $moderator = null,
        ?callable $clock = null,
    ) {
        $this->clock = \Closure::fromCallable($clock ?? static fn(): int => time());
    }

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
     * Runs the AI moderation queue scheduler.
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
    Finds eligible AI sessions and moderates them in bounded batches. Without -p this command only logs the sessions
    that would be moderated.

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
        $endTime = $this->now() + $maxTime;

        $logger->notice(sprintf('Running AI moderation queue %s.', $this->perform() ? 'with perform' : 'in dry-run mode'));
        $logger->notice(sprintf('Limit: %d; batch size: %d; max time: %d seconds.', $limit, $batchSize, $maxTime));

        try {
            $lock = $this->acquireLock(self::LOCK_NAME, $maxTime + self::LOCK_ALLOWANCE);
        } catch (\RuntimeException $e) {
            $logger->error(sprintf('Failed to acquire process lock "%s": %s', self::LOCK_NAME, $e->getMessage()));
            return;
        }

        register_shutdown_function($this->releaseLock(...), $lock);

        $stats = [
            'inspected' => 0,
            'moderated' => 0,
            'dryRunSkipped' => 0,
            'noOpSkipped' => 0,
            'errors' => 0,
        ];
        $dryRunOffset = 0;
        $performOffset = 0;

        while ($stats['inspected'] < $limit) {
            if ($this->now() > $endTime) {
                $this->logSummary($stats, 'maximum runtime reached before next batch');
                return;
            }

            $remaining = $limit - $stats['inspected'];
            $currentBatchSize = min($batchSize, $remaining);
            $offset = $this->perform() ? $performOffset : $dryRunOffset;
            $candidates = $this->getCandidateFinder()->find($currentBatchSize, $offset);

            if (count($candidates) === 0) {
                $this->logSummary($stats, 'no candidates found');
                return;
            }

            foreach ($candidates as $candidate) {
                $stats['inspected']++;

                if (!$this->perform()) {
                    $stats['dryRunSkipped']++;
                    $dryRunOffset++;
                    $logger->info(sprintf(
                        'Dry-run: would moderate AI session "%s" last messaged at %s.',
                        $candidate->getSessionId(),
                        $candidate->getLastMessageSentAt()->getDatabase(),
                    ));
                    continue;
                }

                try {
                    $result = $this->getModerator()->moderate($candidate->getSessionId());
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $performOffset++;
                    $logger->error(sprintf(
                        'Failed to moderate AI session "%s": %s',
                        $candidate->getSessionId(),
                        $e->getMessage(),
                    ), ['exception' => $e]);
                    continue;
                }

                if ($result->isModerated()) {
                    $stats['moderated']++;
                    $logger->info(sprintf('Moderated AI session "%s".', $candidate->getSessionId()));
                    continue;
                }

                $stats['noOpSkipped']++;
                $performOffset++;
                $logger->info(sprintf(
                    'Skipped AI session "%s": %s.',
                    $candidate->getSessionId(),
                    $result->getReason() ?? 'no-op',
                ));
            }

            $this->refreshLock($lock);

            if ($this->now() > $endTime) {
                $this->logSummary($stats, 'maximum runtime reached after batch');
                return;
            }
        }

        $this->logSummary($stats, 'limit reached');
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
     * Releases the command lock on shutdown.
     *
     * @param LockInterface $lock The process lock acquired for this command run.
     * @return void
     */
    private function releaseLock(LockInterface $lock): void
    {
        $lock->release();
        $this->getLogger()->info('AI moderation queue complete: released process lock.');
    }

    /**
     * @return CandidateFinder
     */
    private function getCandidateFinder(): CandidateFinder
    {
        if (null === $this->candidateFinder) {
            $this->candidateFinder = new CandidateFinder($this->getDatabaseInternal());
        }

        return $this->candidateFinder;
    }

    /**
     * @return Moderator
     */
    private function getModerator(): Moderator
    {
        if (null === $this->moderator) {
            $this->moderator = new Moderator(
                SessionLogMapper::getInstance(),
                ModerationEventMapper::getInstance(),
                $this->getConfig(),
                $this->getDi()->get('ai.clientFactory'),
            );
        }

        return $this->moderator;
    }

    /**
     * @param array{inspected: int, moderated: int, dryRunSkipped: int, noOpSkipped: int, errors: int} $stats
     * @param string $reason
     * @return void
     */
    private function logSummary(array $stats, string $reason): void
    {
        $this->getLogger()->notice(sprintf(
            'AI moderation queue stopped: %s. Candidates inspected: %d; moderated: %d; dry-run skipped: %d; no-op skipped: %d; errors: %d.',
            $reason,
            $stats['inspected'],
            $stats['moderated'],
            $stats['dryRunSkipped'],
            $stats['noOpSkipped'],
            $stats['errors'],
        ));
    }

    /**
     * @return int
     */
    private function now(): int
    {
        return ($this->clock)();
    }
}

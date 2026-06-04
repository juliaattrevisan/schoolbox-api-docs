# SA-22418 AI Moderation Queue Placeholder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a safe nightly placeholder CLI and cron entry for future AI moderation queueing work without implementing moderation selection, persistence, queue integration, or API calls.

**Architecture:** Follow existing Schoolbox maintenance CLI patterns used by data retention and storage garbage collection. The new command acquires a process lock, runs in bounded batches, exits when its count or max-time budget is reached, and contains explicit future extension comments where later tickets will add fetching, queueing, AI checks, and status persistence.

**Tech Stack:** PHP 8.4, Schoolbox `CliTool\App`, Symfony Lock through existing CLI helpers, packaged cron template, packaged logrotate configuration, PHPUnit for focused artifact tests.

---

## Jira Context

- Jira: `SA-22418` - "Add nightly moderation queue job".
- Parent: `SA-21768` - "Moderation of AI interactions".
- Related future work:
  - `SA-21801`: configure the AI moderation agent.
  - `SA-21802`: run moderation checks for AI interactions since the last moderation check.
  - `SA-21803`: flag guardrail breaches for human review.
- Current scope decision: this ticket only creates the runnable scheduled entry point and operational safeguards. It does not add tables, new queue contracts, data queries, moderation API calls, or duplicate-prevention logic.

## Behaviour

The placeholder command should:

- support `-h`, `--help`, `-p`, `-v`, `-n`, `--batch`, and `--max-time`;
- acquire a single process lock named `aiModerationQueue`;
- default to dry-run mode unless `-p` is supplied;
- cap each run by both a maximum item count and maximum runtime;
- process work in batches, even though the placeholder has no real records yet;
- log where future data-fetching and queueing code will be added;
- exit successfully when no placeholder work remains, the count limit is reached, or the time limit is reached;
- write cron output to a dedicated log file;
- ensure that dedicated cron log is covered by logrotate.

## Files

- Create: `tools/openai/moderation-queue.php`
- Create: `library/Schoolbox/Admin/OpenAI/ModerationQueue/CommandLineTool.php`
- Create: `library/Schoolbox/Admin/OpenAI/ModerationQueue/Test/CommandLineToolTest.php`
- Modify: `tools/cron/schoolbox`
- Modify: `tools/logrotate/schoolbox`

## Defaults

- Lock name: `aiModerationQueue`
- Default limit: `1000`
- Default batch size: `100`
- Default max time: `1800` seconds
- Lock TTL: max time plus `300` seconds
- Cron log: `/var/log/schoolbox/ai-moderation-queue.log`
- Logrotate target: `/var/log/schoolbox/*/ai-moderation-queue.log`

## Future Extension Comment Text

Use concrete comments in the scaffold so later implementation tickets have clear insertion points:

```php
// Future SA-21802 work: fetch candidate OpenAI sessions or conversations for this batch.
// Future SA-21802 work: skip sessions that are already queued or moderated for their latest message.
// Future SA-21802 work: enqueue moderation work instead of processing moderation directly in this scheduler.
// Future SA-21801 work: worker loads conversation context and sends it to the AI moderation agent.
// Future SA-21803 work: persist moderation outcome, flagged state, and last moderated timestamp.
```

## Task 1: Add the CLI Wrapper

**Files:**

- Create: `tools/openai/moderation-queue.php`

- [ ] **Step 1: Create the wrapper**

Create `tools/openai/moderation-queue.php`:

```php
#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\Kernel;
use Schoolbox\Admin\OpenAI\ModerationQueue\CommandLineTool;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Kernel(
    new CommandLineTool(),
    [
        CliKernelAbstract::OPTION_ERRORS => E_ALL,
    ],
);
$kernel->runApp();
```

- [ ] **Step 2: Run syntax check**

Run:

```bash
php -l tools/openai/moderation-queue.php
```

Expected output:

```text
No syntax errors detected in tools/openai/moderation-queue.php
```

## Task 2: Add the Placeholder Command

**Files:**

- Create: `library/Schoolbox/Admin/OpenAI/ModerationQueue/CommandLineTool.php`

- [ ] **Step 1: Create the command class**

Create `library/Schoolbox/Admin/OpenAI/ModerationQueue/CommandLineTool.php`:

```php
<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\ModerationQueue;

use Schoolbox\Application\CliTool;

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
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [
            self::OPT_SHORT_HELP,
            self::OPT_SHORT_LIMIT,
        ];
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
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

        register_shutdown_function(function () use ($lock): void {
            $lock->release();
            $this->getLogger()->info('AI moderation queue placeholder complete: released process lock.');
        });

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

    private function normalisePositiveInt(int $value, int $default): int
    {
        if ($value <= 0) {
            return $default;
        }

        return $value;
    }

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
}
```

- [ ] **Step 2: Run syntax check**

Run:

```bash
php -l library/Schoolbox/Admin/OpenAI/ModerationQueue/CommandLineTool.php
```

Expected output:

```text
No syntax errors detected in library/Schoolbox/Admin/OpenAI/ModerationQueue/CommandLineTool.php
```

## Task 3: Add Cron and Logrotate Entries

**Files:**

- Modify: `tools/cron/schoolbox`
- Modify: `tools/logrotate/schoolbox`

- [ ] **Step 1: Add cron entry**

In `tools/cron/schoolbox`, place this near the existing nightly maintenance jobs for data retention, garbage collection, and dialog agent autosubmit:

```cron
# AI moderation queue: nightly placeholder job
23 1 * * * www-data /usr/local/bin/jitter 300 php /usr/share/schoolbox/tools/openai/moderation-queue.php -vvvp -n1000 --batch=100 --max-time=1800 >> /var/log/schoolbox/ai-moderation-queue.log 2>&1
```

- [ ] **Step 2: Add logrotate entry**

In `tools/logrotate/schoolbox`, add `/var/log/schoolbox/*/ai-moderation-queue.log` to the same block as `data-retention.log` and `garbage-collect.log`:

```logrotate
/var/log/schoolbox/*/data-retention.log
/var/log/schoolbox/*/garbage-collect.log
/var/log/schoolbox/*/ai-moderation-queue.log {
    daily
    missingok
    rotate 4
    compress
    delaycompress
    notifempty
}
```

If the existing block already contains additional logs, preserve them and only add the new AI moderation log line.

## Task 4: Add Focused Tests

**Files:**

- Create: `library/Schoolbox/Admin/OpenAI/ModerationQueue/Test/CommandLineToolTest.php`

- [ ] **Step 1: Add artifact tests**

Create a PHPUnit test that reads the shipped cron and logrotate files directly:

```php
<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\ModerationQueue\Test;

use PHPUnit\Framework\TestCase;

class CommandLineToolTest extends TestCase
{
    public function testCronRunsModerationQueueWithBoundedOptions(): void
    {
        $cron = file_get_contents(SITE_root . '/tools/cron/schoolbox');

        $this->assertStringContainsString('tools/openai/moderation-queue.php', $cron);
        $this->assertStringContainsString('-n1000', $cron);
        $this->assertStringContainsString('--batch=100', $cron);
        $this->assertStringContainsString('--max-time=1800', $cron);
        $this->assertStringContainsString('/var/log/schoolbox/ai-moderation-queue.log', $cron);
    }

    public function testModerationQueueLogIsRotated(): void
    {
        $logrotate = file_get_contents(SITE_root . '/tools/logrotate/schoolbox');

        $this->assertStringContainsString('/var/log/schoolbox/*/ai-moderation-queue.log', $logrotate);
        $this->assertStringContainsString('rotate 4', $logrotate);
        $this->assertStringContainsString('compress', $logrotate);
    }
}
```

- [ ] **Step 2: Run targeted PHPUnit**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/OpenAI/ModerationQueue/Test/CommandLineToolTest.php
```

Expected result:

```text
OK
```

## Task 5: Final Verification

- [ ] **Step 1: Run PHP syntax checks**

Run:

```bash
php -l tools/openai/moderation-queue.php
php -l library/Schoolbox/Admin/OpenAI/ModerationQueue/CommandLineTool.php
php -l library/Schoolbox/Admin/OpenAI/ModerationQueue/Test/CommandLineToolTest.php
```

Expected output for each file:

```text
No syntax errors detected
```

- [ ] **Step 2: Run style check on touched PHP files**

Run:

```bash
./vendor/bin/phpcs tools/openai/moderation-queue.php library/Schoolbox/Admin/OpenAI/ModerationQueue/CommandLineTool.php library/Schoolbox/Admin/OpenAI/ModerationQueue/Test/CommandLineToolTest.php
```

Expected result: no errors.

- [ ] **Step 3: Run static analysis on touched PHP files**

Run:

```bash
./vendor/bin/phpstan analyze tools/openai/moderation-queue.php library/Schoolbox/Admin/OpenAI/ModerationQueue/CommandLineTool.php library/Schoolbox/Admin/OpenAI/ModerationQueue/Test/CommandLineToolTest.php
```

Expected result: no new PHPStan errors.

- [ ] **Step 4: Run targeted PHPUnit**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/OpenAI/ModerationQueue/Test/CommandLineToolTest.php
```

Expected result: tests pass.

## Out of Scope

- No database schema changes.
- No new OpenAI session table.
- No `openai_log` eligibility query.
- No moderation queue worker.
- No AI moderation API calls.
- No duplicate queueing or moderation-state logic beyond comments that identify the future insertion points.

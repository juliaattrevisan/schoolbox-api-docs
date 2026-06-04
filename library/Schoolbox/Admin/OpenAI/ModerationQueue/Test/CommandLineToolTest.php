<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\ModerationQueue\Test;

use PHPUnit\Framework\TestCase;

/**
 * Tests shipped operational artifacts for the AI moderation queue placeholder.
 */
class CommandLineToolTest extends TestCase
{
    /**
     * Ensures the packaged cron template runs the placeholder with bounded work settings.
     *
     * @return void
     */
    public function testCronRunsModerationQueueWithBoundedOptions(): void
    {
        $cron = file_get_contents(SITE_root . '/tools/cron/schoolbox');

        $this->assertStringContainsString('tools/openai/moderation-queue.php', $cron);
        $this->assertStringContainsString('-n1000', $cron);
        $this->assertStringContainsString('--batch=100', $cron);
        $this->assertStringContainsString('--max-time=1800', $cron);
        $this->assertStringContainsString('/var/log/schoolbox/ai-moderation-queue.log', $cron);
    }

    /**
     * Ensures the packaged CLI wrapper is directly executable like other shebang tools.
     *
     * @return void
     */
    public function testModerationQueueWrapperIsExecutable(): void
    {
        $this->assertTrue(is_executable(SITE_root . '/tools/openai/moderation-queue.php'));
    }

    /**
     * Ensures the dedicated cron log is rotated with the other bounded maintenance logs.
     *
     * @return void
     */
    public function testModerationQueueLogIsRotated(): void
    {
        $logrotate = file_get_contents(SITE_root . '/tools/logrotate/schoolbox');

        $this->assertMatchesRegularExpression(
            '/var/log/schoolbox/\*/ai-moderation-queue\.log\s+' .
            'weekly\s+missingok\s+rotate 4\s+compress\s+\}#',
            $logrotate,
        );
    }

    /**
     * Ensures operators can inspect and release the scheduler lock if a placeholder run exits unexpectedly.
     *
     * @return void
     */
    public function testModerationQueueLockIsRegisteredWithLockInspector(): void
    {
        $inspector = file_get_contents(SITE_root . '/library/Alaress/Lock/CommandLineTool/Inspector.php');

        $this->assertStringContainsString('ModerationQueueTool::LOCK_NAME', $inspector);
        $this->assertStringContainsString('AI Moderation Queue (./tools/openai/moderation-queue.php)', $inspector);
    }
}

<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\ModerationQueue\Test;

use Alaress\Lock\LockRefreshException;
use Alaress\Database\Connector\NullConnector;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\OpenAI\ModerationQueue\Candidate;
use Schoolbox\Admin\OpenAI\ModerationQueue\CandidateFinder;
use Schoolbox\Admin\OpenAI\ModerationQueue\CommandLineTool;
use Schoolbox\Admin\OpenAI\ModerationQueue\ModerationResult;
use Schoolbox\Admin\OpenAI\ModerationQueue\Moderator;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\OpenAi\Moderation\Event\Model as ModerationEventModel;
use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Lock\LockInterface;

/**
 * Tests operational behavior and shipped artifacts for the AI moderation queue.
 */
class CommandLineToolTest extends TestCase
{
    /**
     * @return void
     */
    public function testDryRunLogsCandidatesWithoutModerating(): void
    {
        $candidate = $this->createCandidate('session-a');
        $finder = $this->createFinder([
            [$candidate],
            [],
        ]);
        $moderator = $this->createMock(Moderator::class);
        $moderator->expects($this->never())->method('moderate');

        $this->createTool($finder, $moderator, ['n' => 10, 'batch' => 5], false)->run();

        $this->assertSame([[5, 0], [5, 1]], $this->findCalls);
    }

    /**
     * @return void
     */
    public function testPerformModeModeratesCandidates(): void
    {
        $finder = $this->createFinder([
            [$this->createCandidate('session-a')],
            [],
        ]);
        $moderator = $this->createMock(Moderator::class);
        $moderator
            ->expects($this->once())
            ->method('moderate')
            ->with('session-a')
            ->willReturn(ModerationResult::moderated(new ModerationEventModel()));

        $this->createTool($finder, $moderator, ['n' => 10, 'batch' => 5], true)->run();

        $this->assertSame([[5, 0], [5, 0]], $this->findCalls);
    }

    /**
     * @return void
     */
    public function testNoCandidatesExitsCleanly(): void
    {
        $finder = $this->createFinder([[]]);
        $moderator = $this->createMock(Moderator::class);
        $moderator->expects($this->never())->method('moderate');

        $this->createTool($finder, $moderator, ['n' => 10, 'batch' => 5], true)->run();

        $this->assertSame([[5, 0]], $this->findCalls);
    }

    /**
     * @return void
     */
    public function testMaxLimitBatchesWork(): void
    {
        $finder = $this->createFinder([
            [$this->createCandidate('session-a'), $this->createCandidate('session-b')],
            [$this->createCandidate('session-c')],
        ]);
        $moderator = $this->createMock(Moderator::class);
        $moderator
            ->expects($this->exactly(3))
            ->method('moderate')
            ->willReturn(ModerationResult::moderated(new ModerationEventModel()));

        $this->createTool($finder, $moderator, ['n' => 3, 'batch' => 2], true)->run();

        $this->assertSame([[2, 0], [1, 0]], $this->findCalls);
    }

    /**
     * @return void
     */
    public function testMaxTimeStopsBetweenBatches(): void
    {
        $finder = $this->createFinder([
            [$this->createCandidate('session-a')],
            [$this->createCandidate('session-b')],
        ]);
        $moderator = $this->createMock(Moderator::class);
        $moderator
            ->expects($this->once())
            ->method('moderate')
            ->with('session-a')
            ->willReturnCallback(function (): ModerationResult {
                $this->waitForMaxTimeToExpire();

                return ModerationResult::moderated(new ModerationEventModel());
            });

        $this->createTool(
            $finder,
            $moderator,
            ['n' => 10, 'batch' => 1, 'max-time' => 1],
            true,
        )->run();

        $this->assertSame([[1, 0]], $this->findCalls);
    }

    /**
     * @return void
     */
    public function testMaxTimeStopsDuringBatch(): void
    {
        $finder = $this->createFinder([
            [$this->createCandidate('session-a'), $this->createCandidate('session-b')],
        ]);
        $moderator = $this->createMock(Moderator::class);
        $moderator
            ->expects($this->once())
            ->method('moderate')
            ->with('session-a')
            ->willReturnCallback(function (): ModerationResult {
                $this->waitForMaxTimeToExpire();

                return ModerationResult::moderated(new ModerationEventModel());
            });

        $this->createTool(
            $finder,
            $moderator,
            ['n' => 10, 'batch' => 2, 'max-time' => 1],
            true,
        )->run();

        $this->assertSame([[2, 0]], $this->findCalls);
    }

    /**
     * @return void
     */
    public function testNoOpResultDoesNotAdvancePerformOffset(): void
    {
        $finder = $this->createFinder([
            [$this->createCandidate('session-concurrent')],
            [],
        ]);
        $moderator = $this->createMock(Moderator::class);
        $moderator
            ->expects($this->once())
            ->method('moderate')
            ->with('session-concurrent')
            ->willReturn(ModerationResult::noOp('already_moderated'));

        $this->createTool($finder, $moderator, ['n' => 3, 'batch' => 1], true)->run();

        $this->assertSame([[1, 0], [1, 0]], $this->findCalls);
    }

    /**
     * @return void
     */
    public function testFailedSessionDoesNotBlockLaterCandidates(): void
    {
        $finder = $this->createFinder([
            [$this->createCandidate('session-bad')],
            [$this->createCandidate('session-good')],
            [],
        ]);
        $moderator = $this->createMock(Moderator::class);
        $moderator
            ->expects($this->exactly(2))
            ->method('moderate')
            ->willReturnCallback(
                function (string $sessionId): ModerationResult {
                    if ($sessionId === 'session-bad') {
                        throw new \RuntimeException('bad session');
                    }

                    return ModerationResult::moderated(new ModerationEventModel());
                }
            );

        $this->createTool($finder, $moderator, ['n' => 3, 'batch' => 1], true)->run();

        $this->assertSame([[1, 0], [1, 1], [1, 1]], $this->findCalls);
    }

    /**
     * @return void
     */
    public function testLockRefreshFailureStopsProcessingCleanly(): void
    {
        $finder = $this->createFinder([
            [$this->createCandidate('session-a'), $this->createCandidate('session-b')],
        ]);
        $moderator = $this->createMock(Moderator::class);
        $moderator
            ->expects($this->once())
            ->method('moderate')
            ->with('session-a')
            ->willReturn(ModerationResult::moderated(new ModerationEventModel()));

        $this->createTool($finder, $moderator, ['n' => 10, 'batch' => 2], true, 1)->run();

        $this->assertSame([[2, 0]], $this->findCalls);
    }

    /**
     * @return void
     */
    public function testReleaseLockFailureDoesNotEscapeShutdownHandler(): void
    {
        $lock = $this->createMock(LockInterface::class);
        $lock
            ->expects($this->once())
            ->method('release')
            ->willThrowException(new LockReleasingException('expired lock'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with('AI moderation queue complete: process lock could not be released.');

        $tool = new CommandLineTool();
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            ['config', new ArrayLoader()],
            ['databaseInternal', new NullConnector()],
        ]);
        $tool->setup([], $container, fopen('php://memory', 'w+'), $logger, false);

        $releaseLock = new \ReflectionMethod($tool, 'releaseLock');
        $releaseLock->invoke($tool, $lock);
    }

    /**
     * Ensures the packaged cron template runs the moderation queue with bounded work settings.
     *
     * @return void
     */
    public function testCronRunsModerationQueueWithBoundedOptions(): void
    {
        $cron = file_get_contents(SITE_root . '/tools/cron/schoolbox');

        $this->assertStringContainsString('tools/ai/moderation-queue.php', $cron);
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
        $this->assertTrue(is_executable(SITE_root . '/tools/ai/moderation-queue.php'));
    }

    /**
     * Ensures the dedicated cron log is rotated with the other bounded maintenance logs.
     *
     * @return void
     */
    public function testModerationQueueLogIsRotated(): void
    {
        $logrotate = file_get_contents(SITE_root . '/tools/logrotate/schoolbox');

        $this->assertStringContainsString('/var/log/schoolbox/*/ai-moderation-queue.log', $logrotate);
    }

    /**
     * Ensures operators can inspect and release the scheduler lock if a moderation queue run exits unexpectedly.
     *
     * @return void
     */
    public function testModerationQueueLockIsRegisteredWithLockInspector(): void
    {
        $inspector = file_get_contents(SITE_root . '/library/Alaress/Lock/CommandLineTool/Inspector.php');

        $this->assertStringContainsString('ModerationQueueTool::LOCK_NAME', $inspector);
        $this->assertStringContainsString('AI Moderation Queue (./tools/ai/moderation-queue.php)', $inspector);
    }

    /**
     * @var list<array{int, int}>
     */
    private array $findCalls = [];

    /**
     * @param list<list<Candidate>> $batches
     * @return CandidateFinder
     */
    private function createFinder(array $batches): CandidateFinder
    {
        $finder = $this->createStub(CandidateFinder::class);
        $finder
            ->method('find')
            ->willReturnCallback(function (int $limit, int $offset) use (&$batches): array {
                $this->findCalls[] = [$limit, $offset];
                return array_shift($batches) ?? [];
            });

        return $finder;
    }

    /**
     * @param CandidateFinder $finder
     * @param Moderator $moderator
     * @param array<string, int> $options
     * @param bool $perform
     * @param int|null $failRefreshAfter
     * @return CommandLineTool
     */
    private function createTool(
        CandidateFinder $finder,
        Moderator $moderator,
        array $options,
        bool $perform,
        ?int $failRefreshAfter = null,
    ): CommandLineTool {
        $lock = $this->createStub(LockInterface::class);
        $tool = new class ($finder, $moderator, $lock, $failRefreshAfter) extends CommandLineTool {
            private int $refreshCount = 0;

            public function __construct(
                CandidateFinder $candidateFinder,
                Moderator $moderator,
                private readonly LockInterface $lock,
                private readonly ?int $failRefreshAfter,
            ) {
                parent::__construct($candidateFinder, $moderator);
            }

            protected function acquireLock(string $lockName, ?float $timeout): LockInterface
            {
                return $this->lock;
            }

            protected function refreshLock(LockInterface $lock): void
            {
                $this->refreshCount++;

                if ($this->failRefreshAfter === $this->refreshCount) {
                    throw new LockRefreshException('Could not refresh lock; perhaps expired');
                }
            }
        };

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            ['config', new ArrayLoader()],
            ['databaseInternal', new NullConnector()],
        ]);

        $tool->setup($options, $container, fopen('php://memory', 'w+'), $this->createStub(LoggerInterface::class), $perform);

        return $tool;
    }

    /**
     * @return void
     */
    private function waitForMaxTimeToExpire(): void
    {
        sleep(2);
    }

    /**
     * @param string $sessionId
     * @return Candidate
     */
    private function createCandidate(string $sessionId): Candidate
    {
        return new Candidate(
            $sessionId,
            new \Alaress_Date_Format('2026-07-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT),
            null,
            'dialog_agent',
        );
    }
}

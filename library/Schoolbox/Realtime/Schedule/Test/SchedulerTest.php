<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Schedule\Test;

use Alaress\Async\Notification\Postman;
use Alaress\Date\IClock;
use Alaress\Redis\Async\Event\IEvent;
use Alaress\Redis\Async\Event\Message;
use Alaress\WorkerCrew\DispatcherInterface;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Schedule\Scheduler;

class SchedulerTest extends TestCase
{
    /**
     * Subscription-route reloads must leave active notification workers and
     * their configuration untouched because the router belongs to the parent
     * scheduler process.
     *
     * @return void
     */
    public function testReloadRefreshesRouterWithoutRefreshingWorkers(): void
    {
        $postman = $this->createMock(Postman::class);
        $postman->expects(self::once())->method('reloadRouter');

        $conf = $this->createMock(IConfigLoader::class);
        $conf->expects(self::never())->method('dropCache');

        $dispatcher = $this->createMock(DispatcherInterface::class);
        $dispatcher->expects(self::never())->method('releaseWorkerPool');

        $this->buildScheduler($postman, $conf, $dispatcher)
            ->handle(new Message('', 'scheduled.test.reload'));
    }

    /**
     * Configuration refresh events must retain their existing cache-only
     * behavior and not reload subscription routes.
     *
     * @return void
     */
    public function testRefreshConfigurationDropsConfigurationCacheOnly(): void
    {
        $postman = $this->createMock(Postman::class);
        $postman->expects(self::never())->method('reloadRouter');

        $conf = $this->createMock(IConfigLoader::class);
        $conf->expects(self::once())->method('dropCache');

        $dispatcher = $this->createMock(DispatcherInterface::class);
        $dispatcher->expects(self::never())->method('releaseWorkerPool');

        $this->buildScheduler($postman, $conf, $dispatcher)
            ->handle(new Message('', 'scheduled.test.refreshConfiguration'));
    }

    /**
     * Non-message Redis events are pings and must not affect scheduler state.
     *
     * @return void
     */
    public function testNonMessageEventIsIgnored(): void
    {
        $postman = $this->createMock(Postman::class);
        $postman->expects(self::never())->method('reloadRouter');

        $conf = $this->createMock(IConfigLoader::class);
        $conf->expects(self::never())->method('dropCache');

        $dispatcher = $this->createMock(DispatcherInterface::class);
        $dispatcher->expects(self::never())->method('releaseWorkerPool');

        $event = $this->createStub(IEvent::class);

        $this->buildScheduler($postman, $conf, $dispatcher)->handle($event);
    }

    /**
     * A real SIGHUP must retain the existing cache invalidation and soft
     * worker-pool release behavior.
     *
     * @return void
     */
    public function testHangupDropsConfigurationCacheAndSoftReleasesWorkers(): void
    {
        $postman = $this->createMock(Postman::class);
        $postman->expects(self::never())->method('reloadRouter');

        $conf = $this->createMock(IConfigLoader::class);
        $conf->expects(self::once())->method('dropCache');

        $dispatcher = $this->createMock(DispatcherInterface::class);
        $dispatcher
            ->expects(self::once())
            ->method('releaseWorkerPool')
            ->with(false);

        $this->buildScheduler($postman, $conf, $dispatcher)->onHangup();
    }

    /**
     * Builds a scheduler with replaceable collaborators so lifecycle tests do
     * not start child worker processes.
     *
     * @param Postman                     $postman
     * @param IConfigLoader                $conf
     * @param DispatcherInterface          $dispatcher
     * @return Scheduler
     */
    private function buildScheduler(
        Postman $postman,
        IConfigLoader $conf,
        DispatcherInterface $dispatcher
    ): Scheduler {
        $loop = $this->createStub(LoopInterface::class);
        $scheduler = new Scheduler(
            $loop,
            $this->createStub(DispatchStrategyInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(IClock::class),
            $conf,
            $postman,
            'test'
        );

        $property = new \ReflectionProperty($scheduler, 'dispatcher');
        $property->setValue($scheduler, $dispatcher);

        return $scheduler;
    }
}

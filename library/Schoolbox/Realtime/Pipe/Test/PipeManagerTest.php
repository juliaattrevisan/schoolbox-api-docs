<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Pipe\Test;

use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\Worker\WorkerBundleInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use ReflectionClass;
use Schoolbox\MultiInstance\InstanceRepository;
use Schoolbox\Realtime\Pipe\PipeCollection;
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\Realtime\Subscription\ISubscriptionManager;

class PipeManagerTest extends TestCase
{
    public function testScanInactiveUsersHandlesNumericInstanceIds(): void
    {
        $instanceId = '123';
        $userId     = 456;

        $pipes = $this->createMock(PipeCollection::class);
        $pipes
            ->expects(self::once())
            ->method('getPipesByInstanceAndUserId')
            ->with($instanceId, $userId)
            ->willReturn([]);

        $subscriptionManager = $this->createMock(ISubscriptionManager::class);
        $subscriptionManager
            ->expects(self::once())
            ->method('removeSubscription')
            ->with($instanceId, $userId)
            ->willReturn(true);

        $pipeManager = $this->buildPipeManager($pipes, $subscriptionManager);

        $reflection = new ReflectionClass($pipeManager);
        $reflection->getProperty('userLastActivity')->setValue(
            $pipeManager,
            [$instanceId => [$userId => time() - 301]]
        );

        $reflection->getMethod('scanInactiveUsers')->invoke($pipeManager);
    }

    private function buildPipeManager(
        PipeCollection $pipes,
        ISubscriptionManager $subscriptionManager
    ): PipeManager {
        $workerBundle = $this->createStub(WorkerBundleInterface::class);

        $strategy = $this->createStub(DispatchStrategyInterface::class);
        $strategy
            ->method('newWorkerBundle')
            ->willReturn($workerBundle);

        return new PipeManager(
            $this->createStub(LoopInterface::class),
            $this->createStub(InstanceRepository::class),
            $pipes,
            $subscriptionManager,
            $this->createStub(LoggerInterface::class),
            $strategy,
            1024
        );
    }
}

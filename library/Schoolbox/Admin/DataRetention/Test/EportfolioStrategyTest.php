<?php

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Test;

use Alaress\Database\Connector\IConnector;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\DataRetention\RetentionPeriod;
use Schoolbox\Admin\DataRetention\Strategy\Eportfolio;
use Schoolbox\IConfigLoader;
use Symfony\Component\Lock\LockInterface;

class EportfolioStrategyTest extends TestCase
{
    public function testProcessSafelyExitsWhenRetentionIsOverridden(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $database = $this->createMock(IConnector::class);
        $lock = $this->createMock(LockInterface::class);
        $config = $this->createStub(IConfigLoader::class);

        $lock->expects($this->once())
            ->method('refresh');
        $database->expects($this->never())
            ->method('beginTransaction');
        $logger->expects($this->once())
            ->method('notice')
            ->with('No entries to delete');

        $strategy = new Eportfolio(
            $logger,
            false,
            $database,
            $lock,
            $config,
            time() + 3600,
            new RetentionPeriod(30),
        );

        $strategy->process();
    }
}

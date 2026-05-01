<?php

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Test;

use Alaress\Database\Connector\IConnector;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\DataRetention\CommandLineTool;
use Schoolbox\Admin\DataRetention\RetentionPeriod;
use Schoolbox\Admin\DataRetention\Strategy\Eportfolio;
use Schoolbox\Admin\DataRetention\Strategy\Moments;
use Schoolbox\Admin\DataRetention\Strategy\MyFiles;
use Schoolbox\Admin\DataRetention\ValidStrats;
use Schoolbox\IConfigLoader;
use Symfony\Component\Lock\LockInterface;

class CommandLineToolStrategyBusTest extends TestCase
{
    /**
     * @return list<object>
     */
    private function buildStrategies(string $csvStrats): array
    {
        $tool = new class extends CommandLineTool {
            /**
             * @return list<object>
             */
            public function exposedBuildStrategies(
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
                return $this->buildStrategies(
                    $validStrats,
                    $logger,
                    $perform,
                    $database,
                    $lock,
                    $config,
                    $endTime,
                    $retentionPeriod,
                    $loopCount,
                    $chunkSize,
                );
            }
        };

        return $tool->exposedBuildStrategies(
            new ValidStrats($csvStrats),
            $this->createMock(LoggerInterface::class),
            false,
            $this->createMock(IConnector::class),
            $this->createMock(LockInterface::class),
            $this->createMock(IConfigLoader::class),
            time() + 3600,
            new RetentionPeriod(30),
            3,
            1000,
        );
    }

    public function testBuildsOnlyEportfolioStrategyWhenFilteredToEportfolio(): void
    {
        $strategies = $this->buildStrategies('ePortfolio');

        $this->assertCount(1, $strategies);
        $this->assertInstanceOf(Eportfolio::class, $strategies[0]);
    }

    public function testBuildsEportfolioStrategyWhenNoFilterProvided(): void
    {
        $strategyClasses = array_map('get_class', $this->buildStrategies(''));

        $this->assertContains(Moments::class, $strategyClasses);
        $this->assertContains(MyFiles::class, $strategyClasses);
        $this->assertContains(Eportfolio::class, $strategyClasses);
    }
}

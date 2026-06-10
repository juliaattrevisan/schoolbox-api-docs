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
     * Build strategies with stubbed dependencies so the test can inspect the CLI bus.
     *
     * @return list<object>
     */
    private function buildStrategies(string $csvStrats): array
    {
        $tool = new class (
            $this->createStub(IConnector::class),
            $this->createStub(IConfigLoader::class),
        ) extends CommandLineTool {
            public function __construct(
                private IConnector $database,
                private IConfigLoader $config,
            ) {
            }

            protected function perform(): bool
            {
                return false;
            }

            protected function getDatabaseInternal(): IConnector
            {
                return $this->database;
            }

            protected function getConfig(): IConfigLoader
            {
                return $this->config;
            }

            /**
             * Expose the protected strategy builder for this test harness.
             *
             * @return list<object>
             */
            public function exposedBuildStrategies(
                ValidStrats $validStrats,
                LoggerInterface $logger,
                LockInterface $lock,
                int $endTime,
                RetentionPeriod $retentionPeriod,
                int $loopCount,
                int $chunkSize,
            ): array {
                return $this->buildStrategies(
                    $validStrats,
                    $logger,
                    $lock,
                    $endTime,
                    $retentionPeriod,
                    $loopCount,
                    $chunkSize,
                );
            }
        };

        return $tool->exposedBuildStrategies(
            new ValidStrats($csvStrats),
            $this->createStub(LoggerInterface::class),
            $this->createStub(LockInterface::class),
            time() + 3600,
            new RetentionPeriod(30),
            3,
            1000,
        );
    }

    /**
     * Verify the ePortfolio filter builds only the ePortfolio strategy.
     */
    public function testBuildsOnlyEportfolioStrategyWhenFilteredToEportfolio(): void
    {
        $strategies = $this->buildStrategies('ePortfolio');

        $this->assertCount(1, $strategies);
        $this->assertInstanceOf(Eportfolio::class, $strategies[0]);
    }

    /**
     * Verify the unfiltered CLI bus includes the ePortfolio strategy.
     */
    public function testBuildsEportfolioStrategyWhenNoFilterProvided(): void
    {
        $strategyClasses = array_map('get_class', $this->buildStrategies(''));

        $this->assertContains(Moments::class, $strategyClasses);
        $this->assertContains(MyFiles::class, $strategyClasses);
        $this->assertContains(Eportfolio::class, $strategyClasses);
    }
}

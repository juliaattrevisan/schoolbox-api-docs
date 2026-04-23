<?php

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Test;

use Alaress\Database\Connector\IConnector;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\DataRetention\RetentionPeriod;
use Schoolbox\Admin\DataRetention\Strategy\MyFiles;
use Schoolbox\IConfigLoader;
use Symfony\Component\Lock\LockInterface;

class MyFilesStrategyTest extends TestCase
{
    private function strategy(array $folderChildren, array $fileChildren): MyFiles
    {
        $logger = $this->createMock(LoggerInterface::class);
        $database = $this->createMock(IConnector::class);
        $lock = $this->createMock(LockInterface::class);
        $config = $this->createMock(IConfigLoader::class);

        $logger->method('debug');
        $logger->method('notice');
        $logger->method('info');

        return new class (
            $logger,
            true,
            $database,
            $lock,
            $config,
            time() + 3600,
            new RetentionPeriod(30),
            $folderChildren,
            $fileChildren,
        ) extends MyFiles {
            /** @var array<int, list<int>> */
            private array $folderChildren;

            /** @var array<int, list<int>> */
            private array $fileChildren;

            /** @var list<int> */
            public array $deletedFiles = [];

            /** @var list<int> */
            public array $deletedFolders = [];

            /** @var list<int> */
            public array $deletedEportfolioFileIds = [];

            public function __construct(
                LoggerInterface $logger,
                bool $perform,
                IConnector $database,
                LockInterface $lock,
                IConfigLoader $config,
                int $stoppingTime,
                RetentionPeriod $retentionPeriod,
                array $folderChildren,
                array $fileChildren,
            ) {
                parent::__construct($logger, $perform, $database, $lock, $config, $stoppingTime, $retentionPeriod);
                $this->folderChildren = $folderChildren;
                $this->fileChildren = $fileChildren;
            }

            public function delete(array $chunk): void
            {
                $this->deleteChunk($chunk);
            }

            protected function findChildFolderIds(array $parentIds): array
            {
                $children = [];
                foreach ($parentIds as $parentId) {
                    foreach ($this->folderChildren[$parentId] ?? [] as $childId) {
                        $children[] = $childId;
                    }
                }

                return $children;
            }

            protected function findChildFileIds(array $parentIds): array
            {
                $children = [];
                foreach ($parentIds as $parentId) {
                    foreach ($this->fileChildren[$parentId] ?? [] as $childId) {
                        $children[] = $childId;
                    }
                }

                return $children;
            }

            protected function deleteFiles(array $fileIds): int
            {
                $this->deletedFiles = $fileIds;

                return count($fileIds);
            }

            protected function deleteFolders(array $folderIds): int
            {
                $this->deletedFolders = $folderIds;

                return count($folderIds);
            }

            protected function deleteEportfolioFileJoin(array $fileIds): int
            {
                $this->deletedEportfolioFileIds = $fileIds;

                return count($fileIds);
            }
        };
    }

    public function testDeletesDescendants(): void
    {
        $strategy = $this->strategy(
            [
                10 => [11, 12],
                11 => [13],
                12 => [],
                13 => [],
            ],
            [
                10 => [101],
                11 => [102, 103],
                12 => [104],
                13 => [105],
            ],
        );

        $strategy->delete([
            ['id' => 10, 'table' => 'my_folder'],
        ]);

        $this->assertSame([101, 102, 103, 104, 105], $strategy->deletedFiles);
        $this->assertSame([101, 102, 103, 104, 105], $strategy->deletedEportfolioFileIds);
        $this->assertSame([13, 11, 12, 10], $strategy->deletedFolders);
    }

    public function testMergesDirectFiles(): void
    {
        $strategy = $this->strategy(
            [
                20 => [21],
                21 => [],
            ],
            [
                20 => [201],
                21 => [202],
            ],
        );

        $strategy->delete([
            ['id' => 20, 'table' => 'my_folder'],
            ['id' => 202, 'table' => 'my_file'],
            ['id' => 203, 'table' => 'my_file'],
        ]);

        $this->assertSame([201, 202, 203], $strategy->deletedFiles);
        $this->assertSame([21, 20], $strategy->deletedFolders);
    }

    public function testDeduplicatesNestedFolders(): void
    {
        $strategy = $this->strategy(
            [
                30 => [31],
                31 => [32],
                32 => [],
            ],
            [
                30 => [301],
                31 => [302],
                32 => [303],
            ],
        );

        $strategy->delete([
            ['id' => 30, 'table' => 'my_folder'],
            ['id' => 31, 'table' => 'my_folder'],
        ]);

        $this->assertSame([301, 302, 303], $strategy->deletedFiles);
        $this->assertSame([32, 31, 30], $strategy->deletedFolders);
    }

    public function testDeletesSiblingBranchesLeafFirst(): void
    {
        $strategy = $this->strategy(
            [
                1 => [2, 3],
                2 => [],
                3 => [],
            ],
            [
                1 => [11],
                2 => [12],
                3 => [13],
            ],
        );

        $strategy->delete([
            ['id' => 1, 'table' => 'my_folder'],
        ]);

        $this->assertSame([11, 12, 13], $strategy->deletedFiles);
        $this->assertSame([2, 3, 1], $strategy->deletedFolders);
    }
}

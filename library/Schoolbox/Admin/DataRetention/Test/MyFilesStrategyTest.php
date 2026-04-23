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

class TestableMyFilesStrategy extends MyFiles
{
    /** @var array<int, list<int>> */
    private array $folderChildren = [];

    /** @var array<int, list<int>> */
    private array $fileChildren = [];

    /** @var list<int> */
    public array $deletedFiles = [];

    /** @var list<int> */
    public array $deletedFolders = [];

    /** @var list<int> */
    public array $deletedEportfolioFileIds = [];

    public function setTree(array $folderChildren, array $fileChildren): void
    {
        $this->folderChildren = $folderChildren;
        $this->fileChildren = $fileChildren;
    }

    public function runDeleteChunk(array $chunk): void
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
}

class MyFilesStrategyTest extends TestCase
{
    private function makeStrategy(): TestableMyFilesStrategy
    {
        $logger = $this->createMock(LoggerInterface::class);
        $database = $this->createMock(IConnector::class);
        $lock = $this->createMock(LockInterface::class);
        $config = $this->createMock(IConfigLoader::class);

        $logger->method('debug');
        $logger->method('notice');
        $logger->method('info');

        return new TestableMyFilesStrategy(
            $logger,
            true,
            $database,
            $lock,
            $config,
            time() + 3600,
            new RetentionPeriod(30),
        );
    }

    public function testDeleteChunkDeletesDescendantFilesBeforeFoldersAndParentsLast(): void
    {
        $strategy = $this->makeStrategy();
        $strategy->setTree(
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

        $strategy->runDeleteChunk([
            ['id' => 10, 'table' => 'my_folder'],
        ]);

        $this->assertSame([101, 102, 103, 104, 105], $strategy->deletedFiles);
        $this->assertSame([101, 102, 103, 104, 105], $strategy->deletedEportfolioFileIds);
        $this->assertSame([13, 11, 12, 10], $strategy->deletedFolders);
    }

    public function testDeleteChunkMergesDirectFileIdsWithCascadeFileIdsWithoutDuplicates(): void
    {
        $strategy = $this->makeStrategy();
        $strategy->setTree(
            [
                20 => [21],
                21 => [],
            ],
            [
                20 => [201],
                21 => [202],
            ],
        );

        $strategy->runDeleteChunk([
            ['id' => 20, 'table' => 'my_folder'],
            ['id' => 202, 'table' => 'my_file'],
            ['id' => 203, 'table' => 'my_file'],
        ]);

        $this->assertSame([201, 202, 203], $strategy->deletedFiles);
        $this->assertSame([21, 20], $strategy->deletedFolders);
    }

    public function testDeleteChunkHandlesNestedRequestedFoldersOnlyOnce(): void
    {
        $strategy = $this->makeStrategy();
        $strategy->setTree(
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

        $strategy->runDeleteChunk([
            ['id' => 30, 'table' => 'my_folder'],
            ['id' => 31, 'table' => 'my_folder'],
        ]);

        $this->assertSame([301, 302, 303], $strategy->deletedFiles);
        $this->assertSame([32, 31, 30], $strategy->deletedFolders);
    }
}

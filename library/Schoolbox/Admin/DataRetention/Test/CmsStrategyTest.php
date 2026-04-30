<?php

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Test;

use Alaress\Database\Connector\IConnector;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\DataRetention\RetentionPeriod;
use Schoolbox\Admin\DataRetention\Strategy\Cms;
use Schoolbox\IConfigLoader;
use Symfony\Component\Lock\LockInterface;

#[AllowMockObjectsWithoutExpectations]
class CmsStrategyTest extends TestCase
{
    /**
     * @param array<int, list<int>> $folderChildren
     * @param list<int> $blockedFolderIds
     * @param array<string, array<int, list<int>>> $lookupIds
     * @return array{strategy: Cms, state: object{calls: list<string>, deletedFolderOwnedRowIds: list<int>, deletedFolders: list<int>}}
     */
    private function strategy(array $folderChildren = [], array $blockedFolderIds = [], array $lookupIds = [], array $homepagePadRecords = [], array $padDeleteResults = [], array $redisDeleteCounts = [], bool $perform = true, ?LoggerInterface $logger = null): array
    {
        if ($logger === null) {
            $logger = $this->createMock(LoggerInterface::class);
        }
        $database = $this->createMock(IConnector::class);
        $lock = $this->createMock(LockInterface::class);
        $config = $this->createMock(IConfigLoader::class);

        $logger->method('debug');
        $logger->method('info');
        $logger->method('notice');
        $logger->method('warning');

        $state = new class {
            /** @var list<string> */
            public array $calls = [];

            /** @var list<int> */
            public array $deletedFolderOwnedRowIds = [];

            /** @var list<int> */
            public array $deletedFolders = [];
        };

        $strategy = new class (
            $logger,
            $perform,
            $database,
            $lock,
            $config,
            time() + 3600,
            new RetentionPeriod(30),
            $folderChildren,
            $blockedFolderIds,
            $lookupIds,
            $homepagePadRecords,
            $padDeleteResults,
            $redisDeleteCounts,
            $state,
        ) extends Cms {
            /** @var array<int, list<int>> */
            private array $folderChildren;

            /** @var list<int> */
            private array $blockedFolderIds;

            /** @var array<string, array<int, list<int>>> */
            private array $lookupIds;

            /** @var array<int, array<int, array<string, mixed>>> */
            private array $homepagePadRecords;

            /** @var array<string, bool> */
            private array $padDeleteResults;

            /** @var array<string, int> */
            private array $redisDeleteCounts;

            private object $state;

            public function __construct(
                LoggerInterface $logger,
                bool $perform,
                IConnector $database,
                LockInterface $lock,
                IConfigLoader $config,
                int $endTime,
                RetentionPeriod $retentionPeriod,
                array $folderChildren,
                array $blockedFolderIds,
                array $lookupIds,
                array $homepagePadRecords,
                array $padDeleteResults,
                array $redisDeleteCounts,
                object $state,
            ) {
                parent::__construct($logger, $perform, $database, $lock, $config, $endTime, $retentionPeriod);
                $this->folderChildren = $folderChildren;
                $this->blockedFolderIds = $blockedFolderIds;
                $this->lookupIds = $lookupIds;
                $this->homepagePadRecords = $homepagePadRecords;
                $this->padDeleteResults = $padDeleteResults;
                $this->redisDeleteCounts = $redisDeleteCounts;
                $this->state = $state;
            }

            /**
             * @param array<int, int> $parentIds
             * @return array<int, int>
             */
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

            /**
             * @param array<int, int> $folderIds
             * @return array<int, int>
             */
            protected function findBlockedFolderIds(array $folderIds): array
            {
                return array_values(array_intersect($folderIds, $this->blockedFolderIds));
            }

            /**
             * @param array<int, int> $folderIds
             * @return array<int, int>
             */
            protected function findIdsByFolder(string $table, string $idColumn, string $folderColumn, array $folderIds): array
            {
                $ids = [];
                foreach ($folderIds as $folderId) {
                    foreach ($this->lookupIds[$table][$folderId] ?? [] as $id) {
                        $ids[] = $id;
                    }
                }

                return $ids;
            }

            /**
             * @param int $homepageId
             * @return array<int, array<string, mixed>>
             */
            protected function getHomepagePadRecords(int $homepageId): array
            {
                return $this->homepagePadRecords[$homepageId] ?? [];
            }

            protected function deletePadFromEtherpad(string $padId): bool
            {
                $this->state->calls[] = 'deletePadFromEtherpad:' . $padId;
                return $this->padDeleteResults[$padId] ?? false;
            }

            protected function softDeleteEtherpadPad(int $id): int
            {
                $this->state->calls[] = 'softDeleteEtherpadPad';

                return 1;
            }

            protected function hardDeleteEtherpadPad(int $id): int
            {
                $this->state->calls[] = 'hardDeleteEtherpadPad';

                return 1;
            }

            protected function deleteEtherpadRedisKeys(string $padId, int $homepageId, bool $dryRun): int
            {
                $this->state->calls[] = 'deleteEtherpadRedisKeys:' . $padId;

                return $this->redisDeleteCounts[$padId] ?? 0;
            }

            /**
             * @param int $homepageId
             * @param bool $deleteRedisKeys
             * @param bool $softDeleteMeta
             * @param bool $dryRun
             * @param bool $verbose
             */
            public function cleanupHomepagePads(int $homepageId, bool $deleteRedisKeys = false, bool $softDeleteMeta = false, bool $dryRun = true, bool $verbose = false): array
            {
                $this->state->calls[] = 'cleanupHomepagePads:' . $homepageId . ':' . ($dryRun ? 'dry' : 'do');

                return parent::cleanupHomepagePads($homepageId, $deleteRedisKeys, $softDeleteMeta, $dryRun, $verbose);
            }

            /**
             * @param array<int, int> $ids
             */
            protected function setWikiLatestRevisionNull(array $ids): int
            {
                $this->state->calls[] = 'setWikiLatestRevisionNull';

                return count($ids);
            }

            /**
             * @param array<int, int> $ids
             */
            protected function deleteBlogComments(array $ids): int
            {
                $this->state->calls[] = 'deleteBlogComments';

                return count($ids);
            }

            /**
             * @param array<int, int> $ids
             */
            protected function deleteBlogImages(array $ids): int
            {
                $this->state->calls[] = 'deleteBlogImages';

                return count($ids);
            }

            /**
             * @param array<int, int> $ids
             */
            protected function deleteBlog(array $ids): int
            {
                $this->state->calls[] = 'deleteBlog';

                return count($ids);
            }

            /**
             * @param array<int, int> $ids
             */
            protected function deleteForumPosts(array $ids): int
            {
                $this->state->calls[] = 'deleteForumPosts';

                return count($ids);
            }

            /**
             * @param array<int, int> $ids
             */
            protected function deleteForumThreads(array $ids): int
            {
                $this->state->calls[] = 'deleteForumThreads';

                return count($ids);
            }

            /**
             * @param array<int, int> $ids
             */
            protected function deletePollLogs(array $ids): int
            {
                $this->state->calls[] = 'deletePollLogs';

                return count($ids);
            }

            /**
             * @param array<int, int> $ids
             */
            protected function deletePolls(array $ids): int
            {
                $this->state->calls[] = 'deletePolls';

                return count($ids);
            }

            /**
             * @param array<int, int> $ids
             */
            protected function deleteWikiRevisions(array $ids): int
            {
                $this->state->calls[] = 'deleteWikiRevisions';

                return count($ids);
            }

            /**
             * @param array<int, int> $ids
             */
            protected function deleteWikis(array $ids): int
            {
                $this->state->calls[] = 'deleteWikis';

                return count($ids);
            }

            /**
             * @param array<int, int> $ids
             */
            protected function deleteComponentConfigInstances(array $ids): int
            {
                $this->state->calls[] = 'deleteComponentConfigInstances';

                return count($ids);
            }

            /**
             * @param array<int, int> $ids
             */
            protected function deleteComponentInstances(array $ids): int
            {
                $this->state->calls[] = 'deleteComponentInstances';

                return count($ids);
            }

            /**
             * @param array<int, int> $folderIds
             */
            protected function deleteFolderOwnedRows(array $folderIds): int
            {
                $this->state->deletedFolderOwnedRowIds = $folderIds;
                $this->state->calls[] = 'deleteFolderOwnedRows';

                return count($folderIds);
            }

            /**
             * @param array<int, int> $folderIds
             */
            protected function deleteFolders(array $folderIds): int
            {
                $this->state->deletedFolders = $folderIds;
                $this->state->calls[] = 'deleteFolders';

                return count($folderIds);
            }
        };

        return [
            'strategy' => $strategy,
            'state' => $state,
        ];
    }

    public function testDeleteChunkRunsHomepageCleanupInPerformMode(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy([], [], [], [], [], [], true);

        $this->delete($strategy, [
            ['id' => 10, 'table' => 'folder'],
        ]);

        $this->assertSame([
            'cleanupHomepagePads:10:do',
            'deleteBlogComments',
            'deleteBlogImages',
            'deleteBlog',
            'deleteForumPosts',
            'deleteForumThreads',
            'deletePollLogs',
            'deletePolls',
            'setWikiLatestRevisionNull',
            'deleteWikiRevisions',
            'deleteWikis',
            'deleteComponentConfigInstances',
            'deleteComponentInstances',
            'deleteFolderOwnedRows',
            'deleteFolders',
        ], $state->calls);
    }

    public function testDeleteChunkRunsHomepageCleanupInDryRunMode(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy([], [], [], [], [], [], false);

        $this->delete($strategy, [
            ['id' => 10, 'table' => 'folder'],
        ]);

        $this->assertSame([
            'cleanupHomepagePads:10:dry',
            'deleteBlogComments',
            'deleteBlogImages',
            'deleteBlog',
            'deleteForumPosts',
            'deleteForumThreads',
            'deletePollLogs',
            'deletePolls',
            'setWikiLatestRevisionNull',
            'deleteWikiRevisions',
            'deleteWikis',
            'deleteComponentConfigInstances',
            'deleteComponentInstances',
            'deleteFolderOwnedRows',
            'deleteFolders',
        ], $state->calls);
    }

    /**
     * @param Cms $strategy
     * @param array<int, array{id: int, table: string}> $chunk
     */
    private function delete(Cms $strategy, array $chunk): void
    {
        $delete = \Closure::bind(
            function (array $chunk): void {
                $this->deleteChunk($chunk);
            },
            $strategy,
            Cms::class,
        );

        $delete($chunk);
    }

    /**
     * @param Cms $strategy
     * @param array<int, int> $folderIds
     */
    private function cleanupHomepagePadsForFolders(Cms $strategy, array $folderIds): int
    {
        $call = \Closure::bind(
            function (array $folderIds): int {
                return $this->cleanupHomepagePadsForFolders($folderIds);
            },
            $strategy,
            Cms::class,
        );

        return $call($folderIds);
    }

    public function testCleanupHomepagePadsForFoldersCountsPadsFound(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy(
            [],
            [],
            [],
            [
                10 => [
                    ['id' => 1, 'pad_id' => 'g.test1'],
                    ['id' => 2, 'pad_id' => 'g.test2'],
                ],
            ],
            ['g.test1' => true, 'g.test2' => false],
        );

        $count = $this->cleanupHomepagePadsForFolders($strategy, [10]);

        $this->assertSame(2, $count);
        $this->assertSame([
            'cleanupHomepagePads:10:do',
            'deletePadFromEtherpad:g.test1',
            'hardDeleteEtherpadPad',
            'deletePadFromEtherpad:g.test2',
        ], $state->calls);
    }

    public function testDeleteChunkIncludesEtherpadPadsCountInNotice(): void
    {
        $state = new class {
            public array $notices = [];
        };

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('debug');
        $logger->method('info');
        $logger->method('warning');
        $logger->method('notice')->willReturnCallback(
            function (string $message) use ($state): void {
                $state->notices[] = $message;
            }
        );

        ['strategy' => $strategy] = $this->strategy(
            [],
            [],
            [],
            [
                10 => [
                    ['id' => 1, 'pad_id' => 'g.test1'],
                    ['id' => 2, 'pad_id' => 'g.test2'],
                ],
            ],
            ['g.test1' => true, 'g.test2' => false],
            [],
            true,
            $logger,
        );

        $this->delete($strategy, [
            ['id' => 10, 'table' => 'folder'],
        ]);

        $this->assertStringContainsString('etherpad_pads=2', implode(' | ', $state->notices));
    }

    public function testUsesCmsStrategyNameAndLabel(): void
    {
        $strategy = new Cms(
            $this->createMock(LoggerInterface::class),
            true,
            $this->createMock(IConnector::class),
            $this->createMock(LockInterface::class),
            $this->createMock(IConfigLoader::class),
            time() + 3600,
            new RetentionPeriod(30),
        );

        $this->assertSame('cms', $strategy->getStrategyName());
        $this->assertSame('Resources (CMS) Retention', $strategy->getRetentionLabel());
    }

    public function testDeletesCmsFolderTreeInLeafFirstOrder(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy(
            [
                10 => [11, 12],
                11 => [13],
                12 => [],
                13 => [],
            ],
            [],
            [
                'blog' => [
                    10 => [101],
                    11 => [102],
                    12 => [103],
                    13 => [104],
                ],
            ],
        );

        $this->delete($strategy, [
            ['id' => 10, 'table' => 'folder'],
        ]);

        $this->assertSame([13, 11, 12, 10], $state->deletedFolders);
        $this->assertSame([
            'cleanupHomepagePads:13:do',
            'cleanupHomepagePads:11:do',
            'cleanupHomepagePads:12:do',
            'cleanupHomepagePads:10:do',
            'deleteBlogComments',
            'deleteBlogImages',
            'deleteBlog',
            'deleteForumPosts',
            'deleteForumThreads',
            'deletePollLogs',
            'deletePolls',
            'setWikiLatestRevisionNull',
            'deleteWikiRevisions',
            'deleteWikis',
            'deleteComponentConfigInstances',
            'deleteComponentInstances',
            'deleteFolderOwnedRows',
            'deleteFolders',
        ], $state->calls);
    }

    public function testSkipsBlockedCmsFolderChunks(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy(
            [
                10 => [11],
                11 => [],
            ],
            [10],
        );

        $this->delete($strategy, [
            ['id' => 10, 'table' => 'folder'],
        ]);

        $this->assertSame([], $state->deletedFolders);
        $this->assertSame([], $state->calls);
    }

    public function testHomepageCleanupDryRunDoesNotDeletePadsOrMetadata(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy(
            [],
            [],
            [],
            [
                123 => [
                    ['id' => 1, 'pad_id' => 'g.test123'],
                ],
            ],
            ['g.test123' => true],
            ['g.test123' => 1],
        );

        $summary = $strategy->cleanupHomepagePads(123, true, false, true, true);

        $this->assertSame(1, $summary['padsFound']);
        $this->assertSame(0, $summary['padsDeleted']);
        $this->assertSame(0, $summary['metaRowsHardDeleted']);
        $this->assertSame(1, $summary['redisKeysDeleted']);
        $this->assertSame(['cleanupHomepagePads:123:dry', 'deleteEtherpadRedisKeys:g.test123'], $state->calls);
    }

    public function testHomepageCleanupSoftDeletesMetadataWhenPadDeleted(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy(
            [],
            [],
            [],
            [
                123 => [
                    ['id' => 2, 'pad_id' => 'g.softdelete'],
                ],
            ],
            ['g.softdelete' => true],
        );

        $summary = $strategy->cleanupHomepagePads(123, false, true, false, false);

        $this->assertSame(1, $summary['padsFound']);
        $this->assertSame(1, $summary['padsDeleted']);
        $this->assertSame(1, $summary['metaRowsSoftDeleted']);
        $this->assertSame(0, $summary['redisKeysDeleted']);
        $this->assertSame(['cleanupHomepagePads:123:do', 'deletePadFromEtherpad:g.softdelete', 'softDeleteEtherpadPad'], $state->calls);
    }

    public function testHomepageCleanupTriggersRedisCleanupWhenEnabled(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy(
            [],
            [],
            [],
            [
                123 => [
                    ['id' => 3, 'pad_id' => 'g.redisclean'],
                ],
            ],
            ['g.redisclean' => false],
            ['g.redisclean' => 2],
        );

        $summary = $strategy->cleanupHomepagePads(123, true, false, false, false);

        $this->assertSame(1, $summary['padsFound']);
        $this->assertSame(0, $summary['padsDeleted']);
        $this->assertSame(2, $summary['redisKeysDeleted']);
        $this->assertSame(['cleanupHomepagePads:123:do', 'deletePadFromEtherpad:g.redisclean', 'deleteEtherpadRedisKeys:g.redisclean'], $state->calls);
    }
}

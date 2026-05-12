<?php

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Test;

use Alaress\Database\Connector\IConnector;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\DataRetention\RetentionPeriod;
use Schoolbox\Admin\DataRetention\Strategy\Cms;
use Schoolbox\IConfigLoader;
use Schoolbox\Permission\Access\UserAccess;
use Symfony\Component\Lock\LockInterface;

#[AllowMockObjectsWithoutExpectations]
class CmsStrategyTest extends TestCase
{
    /**
     * Build a CMS strategy test double whose protected database/Etherpad hooks are observable.
     *
     * The retention strategy has to coordinate folder traversal, blocker checks,
     * homepage cleanup, and delete ordering. This fixture keeps those behaviours
     * testable without a real database or Etherpad service, while still running
     * the production orchestration in Cms::deleteChunk().
     *
     * @param array<int, list<int>> $folderChildren
     * @param list<int> $blockedFolderIds
     * @param array<string, array<int, list<int>>> $lookupIds
     * @param array<int, array<int, array<string, mixed>>> $homepagePadRecords
     * @param array<string, bool> $padDeleteResults
     * @param array<string, int> $redisDeleteCounts
     * @param null|(LoggerInterface&MockObject) $logger
     * @param null|list<int> $softDeletedFolderIds
     * @return array{strategy: Cms, state: object{
     *     calls: list<string>,
     *     deletedFolderOwnedRowIds: list<int>,
     *     deletedHomepageUserAccessFolderIds: list<int>,
     *     deletedFolders: list<int>
     * }}
     */
    private function strategy(
        array $folderChildren = [],
        array $blockedFolderIds = [],
        array $lookupIds = [],
        array $homepagePadRecords = [],
        array $padDeleteResults = [],
        array $redisDeleteCounts = [],
        bool $perform = true,
        ?LoggerInterface $logger = null,
        int $homepageUserAccessDeleteCount = 0,
        ?array $softDeletedFolderIds = null,
    ): array {
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
            public array $deletedHomepageUserAccessFolderIds = [];

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
            $homepageUserAccessDeleteCount,
            $softDeletedFolderIds,
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

            private int $homepageUserAccessDeleteCount;

            /** @var null|array<int, true> */
            private ?array $softDeletedFolderIds;

            /**
             * Store the in-memory graph and lookup tables used by this strategy double.
             *
             * The constructor mirrors Cms enough for parent behaviour to run, then
             * replaces external dependencies with arrays so tests can model active
             * children, blocked folders, and related row ids precisely.
             *
             * @param array<int, list<int>> $folderChildren
             * @param list<int> $blockedFolderIds
             * @param array<string, array<int, list<int>>> $lookupIds
             * @param array<int, array<int, array<string, mixed>>> $homepagePadRecords
             * @param array<string, bool> $padDeleteResults
             * @param array<string, int> $redisDeleteCounts
             * @param null|list<int> $softDeletedFolderIds
             * @return void
             */
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
                int $homepageUserAccessDeleteCount,
                ?array $softDeletedFolderIds,
            ) {
                parent::__construct($logger, $perform, $database, $lock, $config, $endTime, $retentionPeriod);
                $this->folderChildren = $folderChildren;
                $this->blockedFolderIds = $blockedFolderIds;
                $this->lookupIds = $lookupIds;
                $this->homepagePadRecords = $homepagePadRecords;
                $this->padDeleteResults = $padDeleteResults;
                $this->redisDeleteCounts = $redisDeleteCounts;
                $this->state = $state;
                $this->homepageUserAccessDeleteCount = $homepageUserAccessDeleteCount;
                $this->softDeletedFolderIds = $softDeletedFolderIds === null
                    ? null
                    : array_fill_keys($softDeletedFolderIds, true);
            }

            /**
             * Return the raw child graph so tests can model both active and deleted children.
             *
             * Cms has separate child lookups for broad tree checks and retention-safe
             * cascade expansion. Keeping this override unfiltered lets the active-child
             * tests prove the narrower helper is doing the exclusion.
             *
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
             * Return retention-eligible descendants from the in-memory folder graph.
             *
             * The production helper asks the database for soft-deleted children. This
             * recursive array implementation lets tests prove the cascade can still
             * reach soft-deleted descendants without letting active folders into the
             * delete plan.
             *
             * @param array<int, int> $parentIds
             * @return array<int, int>
             */
            protected function findSoftDeletedChildFolderIds(array $parentIds): array
            {
                if ($this->softDeletedFolderIds === null) {
                    return $this->findChildFolderIds($parentIds);
                }

                $visited = [];
                $softDeletedFolderIds = [];
                $pending = $this->findChildFolderIds($parentIds);

                while ($pending !== []) {
                    $nextPending = [];
                    foreach ($pending as $folderId) {
                        if (isset($visited[$folderId])) {
                            continue;
                        }

                        $visited[$folderId] = true;
                        if (!isset($this->softDeletedFolderIds[$folderId])) {
                            continue;
                        }

                        $softDeletedFolderIds[$folderId] = $folderId;
                        foreach ($this->folderChildren[$folderId] ?? [] as $childId) {
                            $nextPending[] = $childId;
                        }
                    }

                    $pending = $nextPending;
                }

                return array_values($softDeletedFolderIds);
            }

            /**
             * Find active descendants in the in-memory graph for parent safety checks.
             *
             * Active descendants are what prevent a retained ancestor from being
             * hard-deleted. The test double computes them recursively so tests cover
             * active grandchildren as well as direct children.
             *
             * @param array<int, int> $parentIds
             * @return array<int, int>
             */
            protected function findActiveChildFolderIds(array $parentIds): array
            {
                if ($this->softDeletedFolderIds === null) {
                    return [];
                }

                $visited = [];
                $activeFolderIds = [];
                $pending = $this->findChildFolderIds($parentIds);

                while ($pending !== []) {
                    $nextPending = [];
                    foreach ($pending as $folderId) {
                        if (isset($visited[$folderId])) {
                            continue;
                        }

                        $visited[$folderId] = true;
                        if (!isset($this->softDeletedFolderIds[$folderId])) {
                            $activeFolderIds[$folderId] = $folderId;
                        }

                        foreach ($this->findChildFolderIds([$folderId]) as $childId) {
                            $nextPending[] = $childId;
                        }
                    }

                    $pending = $nextPending;
                }

                return array_values($activeFolderIds);
            }

            /**
             * Simulate excluded assessment/course relations without real FK tables.
             *
             * The production strategy skips chunks that still belong to out-of-scope
             * relations. This override lets tests exercise that branch by supplying
             * just the blocked folder ids.
             *
             * @param array<int, int> $folderIds
             * @return array<int, int>
             */
            protected function findBlockedFolderIds(array $folderIds): array
            {
                return array_values(array_intersect($folderIds, $this->blockedFolderIds));
            }

            /**
             * Resolve related row ids from in-memory lookup tables.
             *
             * CMS has many related tables with different id and folder column names.
             * Tests only need to prove the orchestration asks for the right related
             * ids before deletion, so this avoids database fixtures.
             *
             * @param string $table
             * @param string $idColumn
             * @param string $folderColumn
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
             * Provide homepage Etherpad rows without querying the database.
             *
             * Homepage cleanup is part of CMS folder deletion, and tests need to
             * exercise that path while controlling malformed and successful pad
             * records deterministically.
             *
             * @param int $homepageId
             * @return array<int, array<string, mixed>>
             */
            protected function getHomepagePadRecords(int $homepageId): array
            {
                return $this->homepagePadRecords[$homepageId] ?? [];
            }

            /**
             * Record Etherpad delete attempts instead of contacting an Etherpad server.
             *
             * @param string $padId
             * @return bool
             */
            protected function deletePadFromEtherpad(string $padId): bool
            {
                $this->state->calls[] = 'deletePadFromEtherpad:' . $padId;
                return $this->padDeleteResults[$padId] ?? false;
            }

            /**
             * Keep legacy soft-delete behaviour observable if a test exercises it.
             *
             * @param int $id
             * @return int
             */
            protected function softDeleteEtherpadPad(int $id): int
            {
                $this->state->calls[] = 'softDeleteEtherpadPad';

                return 1;
            }

            /**
             * Record local Etherpad metadata deletion without touching the database.
             *
             * @param int $id
             * @return int
             */
            protected function hardDeleteEtherpadPad(int $id): int
            {
                $this->state->calls[] = 'hardDeleteEtherpadPad';

                return 1;
            }

            /**
             * Record Redis cleanup attempts without requiring Redis in unit tests.
             *
             * @param string $padId
             * @param int $homepageId
             * @param bool $dryRun
             * @return int
             */
            protected function deleteEtherpadRedisKeys(string $padId, int $homepageId, bool $dryRun): int
            {
                $this->state->calls[] = 'deleteEtherpadRedisKeys:' . $padId;

                return $this->redisDeleteCounts[$padId] ?? 0;
            }

            /**
             * Keep homepage cleanup in the call log so folder traversal order is visible.
             *
             * Etherpad cleanup must run for every folder selected for deletion. The
             * call log proves the cleanup follows the same leaf-first order as the
             * eventual folder deletion.
             *
             * @param int $homepageId
             * @return array<string, int|bool>
             */
            public function cleanupHomepagePads(int $homepageId): array
            {
                $this->state->calls[] = 'cleanupHomepagePads:' . $homepageId . ':' . ($this->perform ? 'do' : 'dry');

                return parent::cleanupHomepagePads($homepageId);
            }

            /**
             * Record wiki unlinking so tests can assert it happens before wiki deletion.
             *
             * The production strategy clears latestRevision to satisfy wiki foreign
             * keys. Unit tests do not need the database update, only evidence that
             * the ordered step ran.
             *
             * @param array<int, int> $ids
             * @return int
             */
            protected function setWikiLatestRevisionNull(array $ids): int
            {
                $this->state->calls[] = 'setWikiLatestRevisionNull';

                return count($ids);
            }

            /**
             * Record blog comment cleanup to verify child rows are deleted first.
             *
             * @param array<int, int> $ids
             * @return int
             */
            protected function deleteBlogComments(array $ids): int
            {
                $this->state->calls[] = 'deleteBlogComments';

                return count($ids);
            }

            /**
             * Record blog image cleanup to verify child rows are deleted first.
             *
             * @param array<int, int> $ids
             * @return int
             */
            protected function deleteBlogImages(array $ids): int
            {
                $this->state->calls[] = 'deleteBlogImages';

                return count($ids);
            }

            /**
             * Record blog deletion after its dependent rows have been handled.
             *
             * @param array<int, int> $ids
             * @return int
             */
            protected function deleteBlog(array $ids): int
            {
                $this->state->calls[] = 'deleteBlog';

                return count($ids);
            }

            /**
             * Record forum post cleanup to verify posts are removed before threads.
             *
             * @param array<int, int> $ids
             * @return int
             */
            protected function deleteForumPosts(array $ids): int
            {
                $this->state->calls[] = 'deleteForumPosts';

                return count($ids);
            }

            /**
             * Record forum thread deletion after posts have been handled.
             *
             * @param array<int, int> $ids
             * @return int
             */
            protected function deleteForumThreads(array $ids): int
            {
                $this->state->calls[] = 'deleteForumThreads';

                return count($ids);
            }

            /**
             * Record poll log cleanup to verify logs are removed before polls.
             *
             * @param array<int, int> $ids
             * @return int
             */
            protected function deletePollLogs(array $ids): int
            {
                $this->state->calls[] = 'deletePollLogs';

                return count($ids);
            }

            /**
             * Record poll deletion after poll logs have been handled.
             *
             * @param array<int, int> $ids
             * @return int
             */
            protected function deletePolls(array $ids): int
            {
                $this->state->calls[] = 'deletePolls';

                return count($ids);
            }

            /**
             * Record wiki revision cleanup to verify revisions are removed before wikis.
             *
             * @param array<int, int> $ids
             * @return int
             */
            protected function deleteWikiRevisions(array $ids): int
            {
                $this->state->calls[] = 'deleteWikiRevisions';

                return count($ids);
            }

            /**
             * Record wiki deletion after latestRevision and revisions have been handled.
             *
             * @param array<int, int> $ids
             * @return int
             */
            protected function deleteWikis(array $ids): int
            {
                $this->state->calls[] = 'deleteWikis';

                return count($ids);
            }

            /**
             * Record component config cleanup before component instance deletion.
             *
             * @param array<int, int> $ids
             * @return int
             */
            protected function deleteComponentConfigInstances(array $ids): int
            {
                $this->state->calls[] = 'deleteComponentConfigInstances';

                return count($ids);
            }

            /**
             * Record homepage component instance deletion after config cleanup.
             *
             * @param array<int, int> $ids
             * @return int
             */
            protected function deleteComponentInstances(array $ids): int
            {
                $this->state->calls[] = 'deleteComponentInstances';

                return count($ids);
            }

            /**
             * Capture homepage userAccess cleanup because those ACL rows block folders.
             *
             * @param array<int, int> $folderIds
             * @return int
             */
            protected function deleteHomepageUserAccess(array $folderIds): int
            {
                $this->state->deletedHomepageUserAccessFolderIds = $folderIds;
                $this->state->calls[] = 'deleteHomepageUserAccess';

                return $this->homepageUserAccessDeleteCount;
            }

            /**
             * Capture final folder ids so tests can assert leaf-first deletion.
             *
             * @param array<int, int> $folderIds
             * @return int
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

    /**
     * Homepage pad cleanup must run as part of CMS folder deletion.
     *
     * Pads are homepage-owned data tied to the folder id. The provider keeps
     * perform and dry-run coverage aligned so both modes assert the same
     * orchestration with only the mode-specific call changing.
     *
     * @param bool $perform
     * @param list<string> $expectedCalls
     * @return void
     */
    #[DataProvider('providerDeleteChunkHomepageCleanup')]
    public function testDeleteChunkHomepageCleanup(bool $perform, array $expectedCalls): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy([], [], [], [], [], [], $perform);

        $this->delete($strategy, [
            ['id' => 10, 'table' => 'folder'],
        ]);

        $this->assertSame($expectedCalls, $state->calls);
        $this->assertSame([10], $state->deletedHomepageUserAccessFolderIds);
    }

    /**
     * Data provider for testDeleteChunkHomepageCleanup.
     *
     * @return array<string, array{bool, list<string>}>
     */
    public static function providerDeleteChunkHomepageCleanup(): array
    {
        return [
            'perform mode' => [
                true,
                [
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
                    'deleteHomepageUserAccess',
                    'deleteFolders',
                ],
            ],

            'dry-run mode' => [
                false,
                [
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
                    'deleteHomepageUserAccess',
                    'deleteFolders',
                ],
            ],
        ];
    }

    /**
     * Invoke deleteChunk() directly so tests can focus on one chunk's orchestration.
     *
     * Base::process() covers transactions, lock refresh, and chunk iteration.
     * These tests need the smaller unit where CMS-specific cascade and delete
     * ordering decisions happen.
     *
     * @param Cms $strategy
     * @param array<int, array{id: int, table: string}> $chunk
     * @return void
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
     * Call homepage cleanup through the protected folder wrapper.
     *
     * The wrapper is where folder ids are treated as homepage ids, so tests bind
     * into the class to verify the CMS-specific count aggregation directly.
     *
     * @param Cms $strategy
     * @param array<int, int> $folderIds
     * @return int
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

    /**
     * Call the protected homepage ACL cleanup helper for SQL-shape assertions.
     *
     * This is the narrowest way to prove dry-run mode still targets homepage
     * service ACL rows without running a full chunk.
     *
     * @param Cms $strategy
     * @param array<int, int> $folderIds
     * @return int
     */
    private function deleteHomepageUserAccess(Cms $strategy, array $folderIds): int
    {
        $call = \Closure::bind(
            function (array $folderIds): int {
                return $this->deleteHomepageUserAccess($folderIds);
            },
            $strategy,
            Cms::class,
        );

        return $call($folderIds);
    }

    /**
     * Call the protected CMS candidate loader so query shape can be asserted.
     *
     * The active-child guard lives in SQL, not only in PHP traversal, so this
     * helper exposes the protected query builder to lock that behaviour down.
     *
     * @param Cms $strategy
     * @return array<int, array{id: int}>
     */
    private function getIdsToDelete(Cms $strategy): array
    {
        $call = \Closure::bind(
            function (): array {
                return $this->getIdsToDelete();
            },
            $strategy,
            Cms::class,
        );

        return $call();
    }

    /**
     * Folder-level homepage cleanup should report pads found, not only pads deleted.
     *
     * The chunk notice uses this count to explain the scope of Etherpad work even
     * when an external Etherpad delete fails for a specific pad.
     *
     * @return void
     */
    public function testCleanupHomepagePad(): void
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
            'hardDeleteEtherpadPad',
        ], $state->calls);
    }

    /**
     * Chunk logging must include Etherpad pad counts for auditability.
     *
     * Data retention runs are destructive, so the summary log needs to show that
     * homepage pad metadata was considered alongside the SQL table counts.
     *
     * @return void
     */
    public function testDeleteChunkLogsEtherpadPads(): void
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

    /**
     * Chunk logging must include homepage ACL cleanup counts.
     *
     * userAccess rows are not obvious CMS content, but they can block folder
     * deletion. Logging their count makes that hidden cleanup visible.
     *
     * @return void
     */
    public function testDeleteChunkLogsUserAccess(): void
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
            logger: $logger,
            homepageUserAccessDeleteCount: 3,
        );

        $this->delete($strategy, [
            ['id' => 10, 'table' => 'folder'],
        ]);

        $this->assertStringContainsString('userAccess=3', implode(' | ', $state->notices));
    }

    /**
     * Homepage ACL cleanup must use dry-run SELECTs and the homepage service id.
     *
     * This protects two non-obvious requirements: dry-run must not mutate ACL
     * rows, and only homepage permissions for the folder sid should be targeted.
     *
     * @return void
     */
    public function testDeleteHomepageUserAccessDryRun(): void
    {
        $state = new class {
            public string $preparedSql = '';

            /** @var array<string, int>|null */
            public ?array $executeParams = null;
        };

        $statement = new class ($state) extends \PDOStatement {
            /**
             * Keep captured SQL state with the PDOStatement stub.
             *
             * @param object $state
             * @return void
             */
            public function __construct(private object $state)
            {
            }

            /**
             * Capture bound parameters so the test can verify homepage ACL targeting.
             *
             * @param null|array<string, int> $params
             * @return bool
             */
            public function execute(?array $params = null): bool
            {
                $this->state->executeParams = $params;

                return true;
            }

            /**
             * Return a fixed count because this test is about SQL shape, not row matching.
             *
             * @return int
             */
            public function rowCount(): int
            {
                return 3;
            }
        };

        $database = $this->createMock(IConnector::class);
        $database->expects($this->once())->method('prepare')->willReturnCallback(
            function (string $sql) use ($state, $statement): \PDOStatement {
                $state->preparedSql = $sql;

                return $statement;
            }
        );

        $strategy = new Cms(
            $this->createMock(LoggerInterface::class),
            false,
            $database,
            $this->createMock(LockInterface::class),
            $this->createMock(IConfigLoader::class),
            time() + 3600,
            new RetentionPeriod(30),
        );

        $count = $this->deleteHomepageUserAccess($strategy, [10, 12]);

        $this->assertSame(3, $count);
        $this->assertStringStartsWith(
            'SELECT userAccess.* FROM userAccess WHERE service = :service AND sid IN',
            $state->preparedSql,
        );
        $this->assertSame([
            'folder_0' => 10,
            'folder_1' => 12,
            'service' => UserAccess::SERVICE_HOMEPAGE,
        ], $state->executeParams);
    }

    /**
     * Strategy identity must match the CLI/admin configuration contract.
     *
     * The strategy name is how command-line filtering selects CMS retention, and
     * the label is what administrators see when reviewing retention settings.
     *
     * @return void
     */
    public function testUsesStrategyIdentity(): void
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

    /**
     * CMS folder trees must delete child folders before parents.
     *
     * The folder table is self-referential, so parent-first hard deletes can fail
     * FK checks. This test also proves related homepage cleanup follows the same
     * leaf-first folder order.
     *
     * @return void
     */
    public function testDeletesFolderTree(): void
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
        $this->assertSame([13, 11, 12, 10], $state->deletedHomepageUserAccessFolderIds);
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
            'deleteHomepageUserAccess',
            'deleteFolders',
        ], $state->calls);
    }

    /**
     * A retained parent must not be hard-deleted when it has an active child.
     *
     * @return void
     */
    public function testDeleteChunkSkipsActiveChildren(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy(
            [
                10 => [11, 12],
                11 => [],
                12 => [],
            ],
            softDeletedFolderIds: [10, 11],
        );

        $this->delete($strategy, [
            ['id' => 10, 'table' => 'folder'],
        ]);

        $this->assertSame([], $state->deletedFolders);
    }

    /**
     * Active descendants must stop the ancestor from entering the delete plan.
     *
     * This protects the bottom-up retention rule even when the candidate root is
     * already soft-deleted and old enough to be considered for hard deletion.
     *
     * @return void
     */
    public function testExpandFolderCascadeSkipsActiveParents(): void
    {
        $state = new class {
            public array $warnings = [];
        };

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('debug');
        $logger->method('info');
        $logger->method('notice');
        $logger->method('warning')->willReturnCallback(
            function (string $message) use ($state): void {
                $state->warnings[] = $message;
            }
        );

        ['strategy' => $strategy] = $this->strategy(
            [
                10 => [11],
                11 => [],
            ],
            [],
            [],
            [],
            [],
            [],
            true,
            $logger,
            0,
            [10],
        );

        $call = \Closure::bind(
            function (array $folderIds): array {
                return $this->expandFolderCascade($folderIds);
            },
            $strategy,
            Cms::class,
        );

        $this->assertSame([], $call([10]));
        $this->assertSame([
            'Skipping deletion of folder 10 because it has active child folder 11',
        ], $state->warnings);
    }

    /**
     * Active descendant detection must be recursive, not only direct-child based.
     *
     * Otherwise a soft-deleted parent could be hard-deleted while an active
     * grandchild still exists below a soft-deleted child folder.
     *
     * @return void
     */
    public function testFindActiveChildFolderIds(): void
    {
        ['strategy' => $strategy] = $this->strategy(
            [
                10 => [11],
                11 => [12],
                12 => [],
            ],
            softDeletedFolderIds: [10, 11],
        );

        $call = \Closure::bind(
            function (array $parentIds): array {
                return $this->findActiveChildFolderIds($parentIds);
            },
            $strategy,
            Cms::class,
        );

        $this->assertSame([12], $call([10]));
    }

    /**
     * Soft-deleted descendant discovery must be recursive for leaf-first cleanup.
     *
     * This proves the test double models the production cascade closely enough to
     * catch parent/child ordering regressions across more than one folder level.
     *
     * @return void
     */
    public function testFindSoftDeletedChildFolderIds(): void
    {
        ['strategy' => $strategy] = $this->strategy(
            [
                10 => [11],
                11 => [12],
                12 => [],
            ],
            softDeletedFolderIds: [10, 11, 12],
        );

        $call = \Closure::bind(
            function (array $parentIds): array {
                return $this->findSoftDeletedChildFolderIds($parentIds);
            },
            $strategy,
            Cms::class,
        );

        $this->assertSame([11, 12], $call([10]));
    }

    /**
     * Candidate SQL must leave parents alone while they still have active children.
     *
     * @return void
     */
    public function testGetIdsToDelete(): void
    {
        $state = new class {
            public string $preparedSql = '';

            /** @var array<string, string|null>|null */
            public ?array $executeParams = null;
        };

        $statement = new class ($state) extends \PDOStatement {
            /**
             * Keep captured SQL state with the PDOStatement stub.
             *
             * @param object $state
             * @return void
             */
            public function __construct(private object $state)
            {
            }

            /**
             * Capture candidate-query parameters so the cutoff binding is asserted.
             *
             * @param null|array<string, string|null> $params
             * @return bool
             */
            public function execute(?array $params = null): bool
            {
                $this->state->executeParams = $params;

                return true;
            }

            /**
             * Return one candidate row because this test is about the guard in SQL.
             *
             * @param int $mode
             * @param mixed ...$args
             * @return array<int, array{id: int}>
             */
            public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
            {
                unset($mode, $args);

                return [['id' => 10]];
            }
        };

        $database = $this->createMock(IConnector::class);
        $database->expects($this->once())->method('prepare')->willReturnCallback(
            function (string $sql) use ($state, $statement): \PDOStatement {
                $state->preparedSql = $sql;

                return $statement;
            }
        );

        $strategy = new Cms(
            $this->createMock(LoggerInterface::class),
            false,
            $database,
            $this->createMock(LockInterface::class),
            $this->createMock(IConfigLoader::class),
            time() + 3600,
            new RetentionPeriod(30),
        );

        $ids = $this->getIdsToDelete($strategy);

        $this->assertSame([['id' => 10]], $ids);
        $this->assertStringContainsString('NOT EXISTS', $state->preparedSql);
        $this->assertStringContainsString('child.parent = folder.id', $state->preparedSql);
        $this->assertStringContainsString('child.deleted_at IS NULL', $state->preparedSql);
        $this->assertArrayHasKey('old', $state->executeParams);
        $this->assertNotEmpty($state->executeParams['old']);
    }

    /**
     * Excluded assessment/course relationships should skip a chunk before deletes.
     *
     * Those domains are intentionally out of scope for CMS retention, so this
     * guards against partially deleting folder-owned CMS rows before hitting a
     * foreign-key failure on an excluded relation.
     *
     * @return void
     */
    public function testSkipsBlockedChunks(): void
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
        $this->assertSame([], $state->deletedHomepageUserAccessFolderIds);
        $this->assertSame([], $state->calls);
    }

    /**
     * Homepage cleanup should report pad and metadata outcomes consistently.
     *
     * The provider mirrors the example validation-test shape: each named case
     * supplies the mode, source records, external delete result, and expected
     * summary for one cleanup scenario.
     *
     * @param bool $perform
     * @param array<int, array<int, array<string, mixed>>> $homepagePadRecords
     * @param array<string, bool> $padDeleteResults
     * @param array{padsFound: int, padsDeleted: int, dbRowsDeleted: int} $expectedSummary
     * @param list<string> $expectedCalls
     * @return void
     */
    #[DataProvider('providerHomepageCleanup')]
    public function testHomepageCleanup(
        bool $perform,
        array $homepagePadRecords,
        array $padDeleteResults,
        array $expectedSummary,
        array $expectedCalls,
    ): void {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy(
            homepagePadRecords: $homepagePadRecords,
            padDeleteResults: $padDeleteResults,
            perform: $perform,
        );

        $summary = $strategy->cleanupHomepagePads(123);

        $this->assertSame($expectedSummary['padsFound'], $summary['padsFound']);
        $this->assertSame($expectedSummary['padsDeleted'], $summary['padsDeleted']);
        $this->assertSame($expectedSummary['dbRowsDeleted'], $summary['dbRowsDeleted']);
        $this->assertSame($expectedCalls, $state->calls);
    }

    /**
     * Data provider for testHomepageCleanup.
     *
     * @return array<string, array{
     *     bool,
     *     array<int, array<int, array<string, mixed>>>,
     *     array<string, bool>,
     *     array{padsFound: int, padsDeleted: int, dbRowsDeleted: int},
     *     list<string>
     * }>
     */
    public static function providerHomepageCleanup(): array
    {
        return [
            'dry-run mode reports would-be cleanup' => [
                false,
                [
                    123 => [
                        ['id' => 1, 'pad_id' => 'g.test123'],
                    ],
                ],
                ['g.test123' => true],
                [
                    'padsFound' => 1,
                    'padsDeleted' => 1,
                    'dbRowsDeleted' => 1,
                ],
                ['cleanupHomepagePads:123:dry', 'deletePadFromEtherpad:g.test123', 'hardDeleteEtherpadPad'],
            ],

            'successful Etherpad delete removes metadata' => [
                true,
                [
                    123 => [
                        ['id' => 2, 'pad_id' => 'g.softdelete'],
                    ],
                ],
                ['g.softdelete' => true],
                [
                    'padsFound' => 1,
                    'padsDeleted' => 1,
                    'dbRowsDeleted' => 1,
                ],
                ['cleanupHomepagePads:123:do', 'deletePadFromEtherpad:g.softdelete', 'hardDeleteEtherpadPad'],
            ],

            'failed Etherpad delete still removes metadata' => [
                true,
                [
                    123 => [
                        ['id' => 3, 'pad_id' => 'g.redisclean'],
                    ],
                ],
                ['g.redisclean' => false],
                [
                    'padsFound' => 1,
                    'padsDeleted' => 0,
                    'dbRowsDeleted' => 1,
                ],
                ['cleanupHomepagePads:123:do', 'deletePadFromEtherpad:g.redisclean', 'hardDeleteEtherpadPad'],
            ],
        ];
    }
}

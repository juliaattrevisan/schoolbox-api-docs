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
    /**
     * Creates a test double of Eportfolio backed by in-memory state, with fetchRows and all delete
     * operations intercepted so tests can assert on the queries and operations without a real database.
     *
     * @param list<list<array<string, mixed>>> $fetchRows
     * @return array{strategy: Eportfolio, state: object}
     */
    private function strategy(array $fetchRows = [], ?LoggerInterface $logger = null): array
    {
        $logger ??= $this->createStub(LoggerInterface::class);
        $database = $this->createStub(IConnector::class);
        $lock = $this->createStub(LockInterface::class);
        $config = $this->createStub(IConfigLoader::class);

        $state = new class ($fetchRows) {
            /**
             * Store queued fetch results for the in-memory strategy test double.
             *
             * @param list<list<array<string, mixed>>> $fetchRows
             */
            public function __construct(public array $fetchRows)
            {
            }

            /** @var list<array{query: string, params: array<string, mixed>}> */
            public array $queries = [];

            /** @var array<int, list<int>> */
            public array $pageComponentInstanceIds = [];

            /** @var array<int, list<int>> */
            public array $pageBlogIds = [];

            /** @var list<array{method: string, table: string, column: string, ids: list<int>, excludedIds?: list<int>}> */
            public array $operations = [];
        };

        $strategy = new class (
            $logger,
            true,
            $database,
            $lock,
            $config,
            time() + 3600,
            new RetentionPeriod(30),
            $state,
        ) extends Eportfolio {
            /**
             * Wire the in-memory state into the ePortfolio strategy test double.
             */
            public function __construct(
                LoggerInterface $logger,
                bool $perform,
                IConnector $database,
                LockInterface $lock,
                IConfigLoader $config,
                int $endTime,
                RetentionPeriod $retentionPeriod,
                private object $state,
            ) {
                parent::__construct($logger, $perform, $database, $lock, $config, $endTime, $retentionPeriod);
            }

            /**
             * Record each query and return the next queued fixture result set.
             *
             * @param array<string, mixed> $params
             * @return list<array<string, mixed>>
             */
            protected function fetchRows(string $query, array $params): array
            {
                $this->state->queries[] = ['query' => $query, 'params' => $params];

                return array_shift($this->state->fetchRows) ?? [];
            }

            /**
             * Resolve component instance IDs from the in-memory page mapping.
             *
             * @param list<int> $pageIds
             * @return list<int>
             */
            protected function findComponentInstanceIdsByPageIds(array $pageIds): array
            {
                $componentInstanceIds = [];
                foreach ($pageIds as $pageId) {
                    array_push($componentInstanceIds, ...($this->state->pageComponentInstanceIds[$pageId] ?? []));
                }

                return $componentInstanceIds;
            }

            /**
             * Resolve blog IDs from the in-memory page mapping.
             *
             * @param list<int> $pageIds
             * @return list<int>
             */
            protected function findBlogIdsByPageIds(array $pageIds): array
            {
                $blogIds = [];
                foreach ($pageIds as $pageId) {
                    array_push($blogIds, ...($this->state->pageBlogIds[$pageId] ?? []));
                }

                return $blogIds;
            }

            /**
             * Record primary-key delete operations without touching a database.
             *
             * @param array<string, int> $deleted
             * @param list<int> $ids
             */
            protected function deleteRowsByIds(array &$deleted, string $table, array $ids, string $column = 'id'): void
            {
                if ($ids === []) {
                    return;
                }

                $this->state->operations[] = [
                    'method' => 'ids',
                    'table' => $table,
                    'column' => $column,
                    'ids' => $ids,
                ];
            }

            /**
             * Record foreign-key delete operations without touching a database.
             *
             * @param array<string, int> $deleted
             * @param list<int> $ids
             * @param list<int> $excludedIds
             */
            protected function deleteRowsByForeignIds(
                array &$deleted,
                string $table,
                string $column,
                array $ids,
                array $excludedIds = [],
            ): void {
                if ($ids === []) {
                    return;
                }

                $operation = [
                    'method' => 'foreign',
                    'table' => $table,
                    'column' => $column,
                    'ids' => $ids,
                ];

                if ($excludedIds !== []) {
                    $operation['excludedIds'] = $excludedIds;
                }

                $this->state->operations[] = $operation;
            }
        };

        return ['strategy' => $strategy, 'state' => $state];
    }

    /**
     * Invokes the protected getIdsToDelete() method on the strategy under test.
     *
     * @return list<array<string, mixed>>
     */
    private function getIdsToDelete(Eportfolio $strategy): array
    {
        $getIdsToDelete = \Closure::bind(
            fn(): array => $this->getIdsToDelete(),
            $strategy,
            Eportfolio::class,
        );

        return $getIdsToDelete();
    }

    /**
     * Invokes the protected deleteChunk() method on the strategy under test.
     *
     * @param list<array{table: string, id: int|string}> $chunk
     */
    private function deleteChunk(Eportfolio $strategy, array $chunk): void
    {
        $deleteChunk = \Closure::bind(
            fn(array $chunk): null => $this->deleteChunk($chunk),
            $strategy,
            Eportfolio::class,
        );

        $deleteChunk($chunk);
    }

    /**
     * Verifies that when a retention period override is set but all table counts are zero,
     * process() logs 'No entries to delete' and never starts a database transaction.
     */
    public function testProcessSafelyExitsWhenRetentionIsOverridden(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $database = $this->createMock(IConnector::class);
        $lock = $this->createMock(LockInterface::class);
        $config = $this->createStub(IConfigLoader::class);

        $lock->expects($this->once())
            ->method('refresh');
        $database->expects($this->never())
            ->method('beginTransaction');

        $noticeMessages = [];
        $logger->method('notice')
            ->willReturnCallback(
                static function (string|\Stringable $message) use (&$noticeMessages): void {
                    $noticeMessages[] = (string)$message;
                },
            );

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

        $this->assertContains('No entries to delete', $noticeMessages);
    }

    /**
     * Verifies getIdsToDelete() merges results from all five separate 'find eligible targets'
     * queries and preserves each row's table label.
     */
    public function testCollectsRootDeleteTargetsByTable(): void
    {
        ['strategy' => $strategy] = $this->strategy([
            [['table' => Eportfolio::TABLE_EPORTFOLIO_PAGE, 'id' => 10]],
            [['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG, 'id' => 20]],
            [['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, 'id' => 30]],
            [['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, 'id' => 40]],
            [['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM, 'id' => 50]],
        ]);

        $this->assertSame(
            [
                ['table' => Eportfolio::TABLE_EPORTFOLIO_PAGE, 'id' => 10],
                ['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG, 'id' => 20],
                ['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, 'id' => 30],
                ['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, 'id' => 40],
                ['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM, 'id' => 50],
            ],
            $this->getIdsToDelete($strategy),
        );
    }

    /**
     * Verifies the SQL for independently targeted children (blogs, comments, images, filelist items)
     * guards against selecting rows whose parent or grandparent is also being deleted in the same run,
     * preventing those rows from being double-counted as independent targets.
     */
    public function testIndependentChildQueriesExcludeRowsCoveredByParentCascades(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy([[], [], [], [], []]);

        $this->getIdsToDelete($strategy);

        $sql = implode("\n", array_column($state->queries, 'query'));

        $this->assertStringContainsString('p.deleted_at IS NULL OR p.deleted_at >= :old_page', $sql);
        $this->assertStringContainsString('b.deleted_at IS NULL OR b.deleted_at >= :old_blog', $sql);
    }

    /**
     * Verifies that targeting leaf rows (comments, images, filelist items) by their own deleted_at
     * produces only primary-key deletes with no cascade, and that no unrelated tables are touched.
     */
    public function testIndependentlyDeletedChildrenAreRemovedWithoutDeletingExternalTables(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy();

        $this->deleteChunk($strategy, [
            ['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, 'id' => 30],
            ['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, 'id' => 40],
            ['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM, 'id' => 50],
        ]);

        $this->assertSame(
            [
                ['method' => 'ids', 'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, 'column' => 'id', 'ids' => [30]],
                ['method' => 'ids', 'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, 'column' => 'id', 'ids' => [40]],
                ['method' => 'ids', 'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM, 'column' => 'id', 'ids' => [50]],
            ],
            $state->operations,
        );

        $tables = array_column($state->operations, 'table');
        $this->assertNotContains('my_file', $tables);
        $this->assertNotContains('storage', $tables);
    }

    /**
     * Verifies that a directly targeted blog triggers foreign-key deletes for its comments and images
     * before the blog row itself is deleted.
     */
    public function testIndependentlyDeletedBlogRemovesItsChildrenBeforeTheBlog(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy();

        $this->deleteChunk($strategy, [
            ['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG, 'id' => 20],
        ]);

        $this->assertSame(
            [
                ['method' => 'foreign', 'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, 'column' => 'blog_id', 'ids' => [20]],
                ['method' => 'foreign', 'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, 'column' => 'blog_id', 'ids' => [20]],
                ['method' => 'ids', 'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG, 'column' => 'id', 'ids' => [20]],
            ],
            $state->operations,
        );
    }

    /**
     * Verifies that a directly targeted page triggers foreign-key deletes for all descendant tables
     * in dependency order (grandchildren before children before page).
     */
    public function testEligiblePageCascadeDeletesChildrenBeforePage(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy();
        $state->pageComponentInstanceIds = [10 => [501, 502]];
        $state->pageBlogIds = [10 => [301, 302]];

        $this->deleteChunk($strategy, [
            ['table' => Eportfolio::TABLE_EPORTFOLIO_PAGE, 'id' => 10],
        ]);

        $this->assertSame(
            [
                [
                    'method' => 'foreign',
                    'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT,
                    'column' => 'blog_id',
                    'ids' => [301, 302],
                ],
                [
                    'method' => 'foreign',
                    'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE,
                    'column' => 'blog_id',
                    'ids' => [301, 302],
                ],
                [
                    'method' => 'foreign',
                    'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG,
                    'column' => 'page_id',
                    'ids' => [10],
                ],
                [
                    'method' => 'foreign',
                    'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM,
                    'column' => 'page_id',
                    'ids' => [10],
                ],
                [
                    'method' => 'foreign',
                    'table' => Eportfolio::TABLE_EPORTFOLIO_PERMISSION,
                    'column' => 'page_id',
                    'ids' => [10],
                ],
                [
                    'method' => 'foreign',
                    'table' => Eportfolio::TABLE_COMPONENT_CONFIG_INSTANCE,
                    'column' => 'component_instance_id',
                    'ids' => [501, 502],
                ],
                [
                    'method' => 'foreign',
                    'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_ADVANCEDLIST_ITEM,
                    'column' => 'component_instance_id',
                    'ids' => [501, 502],
                ],
                [
                    'method' => 'foreign',
                    'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BASICLIST_ITEM,
                    'column' => 'component_instance_id',
                    'ids' => [501, 502],
                ],
                [
                    'method' => 'foreign',
                    'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_LINKLIST_ITEM,
                    'column' => 'component_instance_id',
                    'ids' => [501, 502],
                ],
                [
                    'method' => 'foreign',
                    'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_TEXTBOX_ITEM,
                    'column' => 'component_instance_id',
                    'ids' => [501, 502],
                ],
                [
                    'method' => 'ids',
                    'table' => Eportfolio::TABLE_COMPONENT_INSTANCE,
                    'column' => 'id',
                    'ids' => [501, 502],
                ],
                [
                    'method' => 'ids',
                    'table' => Eportfolio::TABLE_EPORTFOLIO_PAGE,
                    'column' => 'page_id',
                    'ids' => [10],
                ],
            ],
            $state->operations,
        );
    }

    /**
     * Verifies that cascading from a targeted page does not produce delete operations for rows
     * belonging to a non-targeted sibling page that also has children configured in state.
     */
    public function testCascadeFromTargetedPageDoesNotTouchSiblingPage(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy();
        $state->pageBlogIds = [10 => [301], 11 => [302]];
        $state->pageComponentInstanceIds = [10 => [501], 11 => [502]];

        $this->deleteChunk($strategy, [
            ['table' => Eportfolio::TABLE_EPORTFOLIO_PAGE, 'id' => 10],
        ]);

        $allIds = array_merge(...array_map(fn(array $op): array => $op['ids'], $state->operations));
        $this->assertNotContains(11, $allIds);
        $this->assertNotContains(302, $allIds);
        $this->assertNotContains(502, $allIds);
    }

    /**
     * Verifies that when a chunk contains both directly targeted child rows and a page,
     * the direct-target deletes run before the page cascade, and the filelist item is excluded
     * from the broader page cascade to avoid double-deletion.
     */
    public function testMixedChunksDeleteIndependentChildrenBeforePageCascade(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy();

        $this->deleteChunk($strategy, [
            ['table' => Eportfolio::TABLE_EPORTFOLIO_PAGE, 'id' => 10],
            ['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, 'id' => 30],
            ['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, 'id' => 40],
            ['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM, 'id' => 50],
        ]);

        $this->assertSame(
            [
                ['method' => 'ids', 'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, 'column' => 'id', 'ids' => [30]],
                ['method' => 'ids', 'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, 'column' => 'id', 'ids' => [40]],
                ['method' => 'foreign', 'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG, 'column' => 'page_id', 'ids' => [10]],
                ['method' => 'ids', 'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM, 'column' => 'id', 'ids' => [50]],
                [
                    'method' => 'foreign',
                    'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM,
                    'column' => 'page_id',
                    'ids' => [10],
                    'excludedIds' => [50],
                ],
                ['method' => 'foreign', 'table' => Eportfolio::TABLE_EPORTFOLIO_PERMISSION, 'column' => 'page_id', 'ids' => [10]],
                ['method' => 'ids', 'table' => Eportfolio::TABLE_EPORTFOLIO_PAGE, 'column' => 'page_id', 'ids' => [10]],
            ],
            $state->operations,
        );
    }

    /**
     * Verifies that when a row is both a direct target and reachable by a broader cascade,
     * the cascade delete explicitly excludes that row's ID to prevent double-deletion.
     */
    public function testOverlappingMixedChunksExcludeDirectTargetsFromBroaderDryRunCounts(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy();
        $state->pageBlogIds = [10 => [20]];

        $this->deleteChunk($strategy, [
            ['table' => Eportfolio::TABLE_EPORTFOLIO_PAGE, 'id' => 10],
            ['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG, 'id' => 20],
            ['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, 'id' => 30],
            ['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, 'id' => 40],
            ['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM, 'id' => 50],
        ]);

        $this->assertSame(
            [
                ['method' => 'ids', 'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, 'column' => 'id', 'ids' => [30]],
                [
                    'method' => 'foreign',
                    'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT,
                    'column' => 'blog_id',
                    'ids' => [20],
                    'excludedIds' => [30],
                ],
                ['method' => 'ids', 'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, 'column' => 'id', 'ids' => [40]],
                [
                    'method' => 'foreign',
                    'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE,
                    'column' => 'blog_id',
                    'ids' => [20],
                    'excludedIds' => [40],
                ],
                ['method' => 'ids', 'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG, 'column' => 'id', 'ids' => [20]],
                [
                    'method' => 'foreign',
                    'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG,
                    'column' => 'page_id',
                    'ids' => [10],
                    'excludedIds' => [20],
                ],
                ['method' => 'ids', 'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM, 'column' => 'id', 'ids' => [50]],
                [
                    'method' => 'foreign',
                    'table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM,
                    'column' => 'page_id',
                    'ids' => [10],
                    'excludedIds' => [50],
                ],
                ['method' => 'foreign', 'table' => Eportfolio::TABLE_EPORTFOLIO_PERMISSION, 'column' => 'page_id', 'ids' => [10]],
                ['method' => 'ids', 'table' => Eportfolio::TABLE_EPORTFOLIO_PAGE, 'column' => 'page_id', 'ids' => [10]],
            ],
            $state->operations,
        );
    }

    /**
     * Verifies that the IDs of each root-target table in a chunk are logged at DEBUG level
     * at the start of deleteChunk(), before any delete operations are performed.
     */
    public function testDeleteChunkLogsRootTargetIdsByTableAtDebugLevel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $debugMessages = [];

        $logger->expects($this->exactly(2))
            ->method('debug')
            ->willReturnCallback(
                static function (string|\Stringable $message, array $context = []) use (&$debugMessages): void {
                    $debugMessages[] = (string)$message;
                },
            );

        ['strategy' => $strategy] = $this->strategy([], $logger);

        $this->deleteChunk($strategy, [
            ['table' => Eportfolio::TABLE_EPORTFOLIO_PAGE, 'id' => 10],
            ['table' => Eportfolio::TABLE_EPORTFOLIO_PAGE, 'id' => 11],
            ['table' => Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG, 'id' => 20],
        ]);

        $this->assertSame(
            [
                'Processing ePortfolio root target eportfolio_page ids: 10, 11',
                'Processing ePortfolio root target eportfolio_component_blog ids: 20',
            ],
            $debugMessages,
        );
    }

    /**
     * Verifies hasDataToDelete() returns false when the count query returns zero for all five
     * direct-delete tables.
     */
    public function testHasDataToDeleteReturnsFalseWhenNoEligibleRowsExist(): void
    {
        ['strategy' => $strategy] = $this->strategy([
            [[
                Eportfolio::TABLE_EPORTFOLIO_PAGE => 0,
                Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG => 0,
                Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT => 0,
                Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE => 0,
                Eportfolio::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM => 0,
            ]],
        ]);

        $hasData = \Closure::bind(
            fn(): bool => $this->hasDataToDelete(),
            $strategy,
            Eportfolio::class,
        );

        $this->assertFalse($hasData());
    }

    /**
     * Verifies hasDataToDelete() returns true when at least one of the five direct-delete tables
     * has a non-zero count.
     */
    public function testHasDataToDeleteReturnsTrueWhenAnyEligibleRowsExist(): void
    {
        ['strategy' => $strategy] = $this->strategy([
            [[
                Eportfolio::TABLE_EPORTFOLIO_PAGE => 1,
                Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG => 0,
                Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT => 0,
                Eportfolio::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE => 0,
                Eportfolio::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM => 0,
            ]],
        ]);

        $hasData = \Closure::bind(
            fn(): bool => $this->hasDataToDelete(),
            $strategy,
            Eportfolio::class,
        );

        $this->assertTrue($hasData());
    }
}

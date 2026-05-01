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
    private const string TABLE_PAGE = 'eportfolio_page';
    private const string TABLE_COMPONENT_CONFIG_INSTANCE = 'component_config_instance';
    private const string TABLE_COMPONENT_INSTANCE = 'component_instance';
    private const string TABLE_ADVANCEDLIST_ITEM = 'eportfolio_component_advancedlist_item';
    private const string TABLE_BASICLIST_ITEM = 'eportfolio_component_basiclist_item';
    private const string TABLE_LINKLIST_ITEM = 'eportfolio_component_linklist_item';
    private const string TABLE_TEXTBOX_ITEM = 'eportfolio_component_textbox_item';
    private const string TABLE_BLOG = 'eportfolio_component_blog';
    private const string TABLE_BLOG_COMMENT = 'eportfolio_component_blog_comment';
    private const string TABLE_BLOG_IMAGE = 'eportfolio_component_blog_image';
    private const string TABLE_FILELIST_ITEM = 'eportfolio_component_filelist_item';
    private const string TABLE_PERMISSION = 'eportfolio_permission';

    /**
     * @param list<list<array<string, mixed>>> $fetchRows
     * @return array{strategy: Eportfolio, state: object}
     */
    private function strategy(array $fetchRows = []): array
    {
        $logger = $this->createStub(LoggerInterface::class);
        $database = $this->createStub(IConnector::class);
        $lock = $this->createStub(LockInterface::class);
        $config = $this->createStub(IConfigLoader::class);

        $logger->method('debug');
        $logger->method('info');
        $logger->method('notice');
        $logger->method('warning');

        $state = new class ($fetchRows) {
            /**
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

            /** @var list<array{method: string, table: string, column: string, ids: list<int>}> */
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
             * @param array<string, mixed> $params
             * @return list<array<string, mixed>>
             */
            protected function fetchRows(string $query, array $params): array
            {
                $this->state->queries[] = ['query' => $query, 'params' => $params];

                return array_shift($this->state->fetchRows) ?? [];
            }

            /**
             * @param list<int> $pageIds
             * @return list<int>
             */
            protected function findComponentInstanceIdsByPageIds(array $pageIds): array
            {
                $componentInstanceIds = [];
                foreach ($pageIds as $pageId) {
                    $componentInstanceIds = array_merge(
                        $componentInstanceIds,
                        $this->state->pageComponentInstanceIds[$pageId] ?? [],
                    );
                }

                return $componentInstanceIds;
            }

            /**
             * @param list<int> $pageIds
             * @return list<int>
             */
            protected function findBlogIdsByPageIds(array $pageIds): array
            {
                $blogIds = [];
                foreach ($pageIds as $pageId) {
                    $blogIds = array_merge($blogIds, $this->state->pageBlogIds[$pageId] ?? []);
                }

                return $blogIds;
            }

            /**
             * @param list<int> $ids
             */
            protected function deleteRowsByIds(string $table, string $column, array $ids): int
            {
                if ($ids === []) {
                    return 0;
                }

                $this->state->operations[] = [
                    'method' => 'ids',
                    'table' => $table,
                    'column' => $column,
                    'ids' => $ids,
                ];

                return count($ids);
            }

            /**
             * @param list<int> $ids
             */
            protected function deleteRowsByForeignIds(string $table, string $column, array $ids): int
            {
                if ($ids === []) {
                    return 0;
                }

                $this->state->operations[] = [
                    'method' => 'foreign',
                    'table' => $table,
                    'column' => $column,
                    'ids' => $ids,
                ];

                return count($ids);
            }
        };

        return ['strategy' => $strategy, 'state' => $state];
    }

    /**
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

    public function testCollectsRootDeleteTargetsByTable(): void
    {
        ['strategy' => $strategy] = $this->strategy([
            [['table' => self::TABLE_PAGE, 'id' => 10]],
            [['table' => self::TABLE_BLOG, 'id' => 20]],
            [['table' => self::TABLE_BLOG_COMMENT, 'id' => 30]],
            [['table' => self::TABLE_BLOG_IMAGE, 'id' => 40]],
            [['table' => self::TABLE_FILELIST_ITEM, 'id' => 50]],
        ]);

        $this->assertSame(
            [
                ['table' => self::TABLE_PAGE, 'id' => 10],
                ['table' => self::TABLE_BLOG, 'id' => 20],
                ['table' => self::TABLE_BLOG_COMMENT, 'id' => 30],
                ['table' => self::TABLE_BLOG_IMAGE, 'id' => 40],
                ['table' => self::TABLE_FILELIST_ITEM, 'id' => 50],
            ],
            $this->getIdsToDelete($strategy),
        );
    }

    public function testIndependentChildQueriesExcludeRowsCoveredByParentCascades(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy([[], [], [], [], []]);

        $this->getIdsToDelete($strategy);

        $sql = implode("\n", array_column($state->queries, 'query'));

        $this->assertStringContainsString('p.deleted_at IS NULL OR p.deleted_at >= :old_page', $sql);
        $this->assertStringContainsString('b.deleted_at IS NULL OR b.deleted_at >= :old_blog', $sql);
    }

    public function testEligiblePageCascadeDeletesChildrenBeforePage(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy();
        $state->pageComponentInstanceIds = [10 => [501, 502]];
        $state->pageBlogIds = [10 => [301, 302]];

        $this->deleteChunk($strategy, [
            ['table' => self::TABLE_PAGE, 'id' => 10],
        ]);

        $this->assertSame(
            [
                [
                    'method' => 'foreign',
                    'table' => self::TABLE_COMPONENT_CONFIG_INSTANCE,
                    'column' => 'component_instance_id',
                    'ids' => [501, 502],
                ],
                [
                    'method' => 'foreign',
                    'table' => self::TABLE_ADVANCEDLIST_ITEM,
                    'column' => 'component_instance_id',
                    'ids' => [501, 502],
                ],
                [
                    'method' => 'foreign',
                    'table' => self::TABLE_BASICLIST_ITEM,
                    'column' => 'component_instance_id',
                    'ids' => [501, 502],
                ],
                [
                    'method' => 'foreign',
                    'table' => self::TABLE_LINKLIST_ITEM,
                    'column' => 'component_instance_id',
                    'ids' => [501, 502],
                ],
                [
                    'method' => 'foreign',
                    'table' => self::TABLE_TEXTBOX_ITEM,
                    'column' => 'component_instance_id',
                    'ids' => [501, 502],
                ],
                [
                    'method' => 'foreign',
                    'table' => self::TABLE_BLOG_COMMENT,
                    'column' => 'blog_id',
                    'ids' => [301, 302],
                ],
                [
                    'method' => 'foreign',
                    'table' => self::TABLE_BLOG_IMAGE,
                    'column' => 'blog_id',
                    'ids' => [301, 302],
                ],
                [
                    'method' => 'foreign',
                    'table' => self::TABLE_BLOG,
                    'column' => 'page_id',
                    'ids' => [10],
                ],
                [
                    'method' => 'foreign',
                    'table' => self::TABLE_FILELIST_ITEM,
                    'column' => 'page_id',
                    'ids' => [10],
                ],
                [
                    'method' => 'foreign',
                    'table' => self::TABLE_PERMISSION,
                    'column' => 'page_id',
                    'ids' => [10],
                ],
                [
                    'method' => 'ids',
                    'table' => self::TABLE_COMPONENT_INSTANCE,
                    'column' => 'id',
                    'ids' => [501, 502],
                ],
                [
                    'method' => 'ids',
                    'table' => self::TABLE_PAGE,
                    'column' => 'page_id',
                    'ids' => [10],
                ],
            ],
            $state->operations,
        );
    }
}

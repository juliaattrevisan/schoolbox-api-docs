# ePortfolio Data Retention Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the SA-21885 ePortfolio data retention strategy so eligible soft-deleted ePortfolio pages and independently soft-deleted child rows are hard-deleted safely.

**Architecture:** Keep the strategy inside the existing `Schoolbox\Admin\DataRetention` CLI framework. Use table-name constants as root target identifiers, select root targets in `getIdsToDelete()`, expand page-owned rows inside `deleteChunk()`, and delete leaves before parents. Add a small protected `CommandLineTool::buildStrategies()` seam so the CLI bus can be tested without running lock/config setup.

**Tech Stack:** PHP 8.4, PHPUnit, Schoolbox data retention CLI, `Alaress\Database\Predicate\Builder`, PDO prepared statements.

---

## File Structure

- Modify `library/Schoolbox/Admin/DataRetention/ValidStrats.php`
  - Add `ValidStrats::EPORTFOLIO` to `STRATS_WHITELIST`.
- Modify `library/Schoolbox/Admin/DataRetention/CommandLineTool.php`
  - Extract strategy construction into `buildStrategies()`.
  - Add `Strategy\Eportfolio` to the bus when `ePortfolio` is enabled.
- Modify `library/Schoolbox/Admin/DataRetention/Strategy/Eportfolio.php`
  - Implement root target selection, page cascade expansion, dependency-ordered deletes, and logging.
- Modify `library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php`
  - Cover the `ePortfolio` CLI strategy name.
- Create `library/Schoolbox/Admin/DataRetention/Test/CommandLineToolStrategyBusTest.php`
  - Cover that the CLI bus instantiates `Strategy\Eportfolio`.
- Create `library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php`
  - Cover target grouping, page cascade ordering, independent child cleanup, and external table safety.

## Task 1: Wire ePortfolio Into Strategy Selection

**Files:**
- Modify: `library/Schoolbox/Admin/DataRetention/ValidStrats.php`
- Modify: `library/Schoolbox/Admin/DataRetention/CommandLineTool.php`
- Modify: `library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php`
- Create: `library/Schoolbox/Admin/DataRetention/Test/CommandLineToolStrategyBusTest.php`

- [ ] **Step 1: Extend the failing ValidStrats coverage**

In `library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php`, add these cases to `providerValidStrats()`:

```php
'single ePortfolio valid' => [
    'ePortfolio',
    ['ePortfolio'],
],
'all valid strategies' => [
    'moments,myFiles,ePortfolio',
    ['moments', 'myFiles', 'ePortfolio'],
],
```

Add this case to `providerStratEnabled()`:

```php
'ePortfolio enabled' => [
    'ePortfolio',
    'ePortfolio',
    true,
],
```

- [ ] **Step 2: Add the failing CLI bus test**

Create `library/Schoolbox/Admin/DataRetention/Test/CommandLineToolStrategyBusTest.php`:

```php
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
```

- [ ] **Step 3: Run the new focused tests and verify they fail**

Run:

```bash
docker/exec vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php library/Schoolbox/Admin/DataRetention/Test/CommandLineToolStrategyBusTest.php
```

Expected: FAIL because `ePortfolio` is not yet in `STRATS_WHITELIST` and `CommandLineTool::buildStrategies()` does not exist yet.

- [ ] **Step 4: Enable the strategy name**

In `library/Schoolbox/Admin/DataRetention/ValidStrats.php`, update `STRATS_WHITELIST`:

```php
public const array STRATS_WHITELIST = [
    self::MOMENTS,
    self::MYFILES,
    self::EPORTFOLIO,
];
```

- [ ] **Step 5: Extract and extend CLI strategy construction**

In `library/Schoolbox/Admin/DataRetention/CommandLineTool.php`, add these imports:

```php
use Alaress\Database\Connector\IConnector;
use Psr\Log\LoggerInterface;
use Schoolbox\IConfigLoader;
```

Replace the inline bus-building block in `run()` with:

```php
$bus = $this->buildStrategies(
    $validStrats,
    $logger,
    $this->perform(),
    $this->getDatabaseInternal(),
    $lock,
    $this->getConfig(),
    $endTime,
    $retentionPeriod,
    $loopCount,
    $chunkSize,
);
```

Add this protected method above `releaseLock()`:

```php
/**
 * @return Strategy\IStrategy[]
 */
protected function buildStrategies(
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
    $bus = [];

    if ($validStrats->isStratEnabled(ValidStrats::MOMENTS)) {
        $bus[] = new Strategy\Moments(
            $logger,
            $perform,
            $database,
            $lock,
            $config,
            $endTime,
            clone $retentionPeriod,
            $loopCount,
            $chunkSize,
        );
    }

    if ($validStrats->isStratEnabled(ValidStrats::MYFILES)) {
        $bus[] = new Strategy\MyFiles(
            $logger,
            $perform,
            $database,
            $lock,
            $config,
            $endTime,
            clone $retentionPeriod,
            $loopCount,
            $chunkSize,
        );
    }

    if ($validStrats->isStratEnabled(ValidStrats::EPORTFOLIO)) {
        $bus[] = new Strategy\Eportfolio(
            $logger,
            $perform,
            $database,
            $lock,
            $config,
            $endTime,
            clone $retentionPeriod,
            $loopCount,
            $chunkSize,
        );
    }

    return $bus;
}
```

- [ ] **Step 6: Run the focused tests**

Run:

```bash
docker/exec vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php library/Schoolbox/Admin/DataRetention/Test/CommandLineToolStrategyBusTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit the wiring**

Run:

```bash
git add library/Schoolbox/Admin/DataRetention/ValidStrats.php library/Schoolbox/Admin/DataRetention/CommandLineTool.php library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php library/Schoolbox/Admin/DataRetention/Test/CommandLineToolStrategyBusTest.php
git commit -m "Wire ePortfolio data retention strategy [SA-21885]"
```

## Task 2: Implement Root Target Selection

**Files:**
- Modify: `library/Schoolbox/Admin/DataRetention/Strategy/Eportfolio.php`
- Create: `library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php`

- [ ] **Step 1: Add failing selection tests**

Create `library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php` with the test harness and the first selection tests:

```php
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
    private const TABLE_PAGE = 'eportfolio_page';
    private const TABLE_BLOG = 'eportfolio_component_blog';
    private const TABLE_BLOG_COMMENT = 'eportfolio_component_blog_comment';
    private const TABLE_BLOG_IMAGE = 'eportfolio_component_blog_image';
    private const TABLE_FILELIST_ITEM = 'eportfolio_component_filelist_item';

    /**
     * @param list<list<array<string, mixed>>> $fetchRows
     * @return array{strategy: Eportfolio, state: object}
     */
    private function strategy(array $fetchRows = []): array
    {
        $logger = $this->createMock(LoggerInterface::class);
        $database = $this->createMock(IConnector::class);
        $lock = $this->createMock(LockInterface::class);
        $config = $this->createMock(IConfigLoader::class);

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
             * @return list<array<string, mixed>>
             */
            public function exposedGetIdsToDelete(): array
            {
                return $this->getIdsToDelete();
            }

            /**
             * @param array<int, array{table: string, id: int|string}> $chunk
             */
            public function exposedDeleteChunk(array $chunk): void
            {
                $this->deleteChunk($chunk);
            }

            /**
             * @return list<array<string, mixed>>
             */
            protected function fetchRows(string $query, array $params): array
            {
                $this->state->queries[] = ['query' => $query, 'params' => $params];

                return array_shift($this->state->fetchRows) ?? [];
            }

            /**
             * @return list<int>
             */
            protected function findComponentInstanceIdsByPageIds(array $pageIds): array
            {
                return [];
            }

            /**
             * @return list<int>
             */
            protected function findBlogIdsByPageIds(array $pageIds): array
            {
                return [];
            }

            protected function deleteRowsByIds(string $table, string $idColumn, array $ids, string $parameterPrefix): int
            {
                if ($ids === []) {
                    return 0;
                }

                $this->state->operations[] = [
                    'method' => 'ids',
                    'table' => $table,
                    'column' => $idColumn,
                    'ids' => array_values(array_map('intval', $ids)),
                ];

                return count($ids);
            }

            protected function deleteRowsByForeignIds(string $table, string $column, array $ids, string $parameterPrefix): int
            {
                if ($ids === []) {
                    return 0;
                }

                $this->state->operations[] = [
                    'method' => 'foreign',
                    'table' => $table,
                    'column' => $column,
                    'ids' => array_values(array_map('intval', $ids)),
                ];

                return count($ids);
            }
        };

        return ['strategy' => $strategy, 'state' => $state];
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
            $strategy->exposedGetIdsToDelete(),
        );
    }

    public function testIndependentChildQueriesExcludeRowsCoveredByParentCascades(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy([[], [], [], [], []]);

        $strategy->exposedGetIdsToDelete();

        $sql = implode("\n", array_column($state->queries, 'query'));

        $this->assertStringContainsString('p.deleted_at IS NULL OR p.deleted_at >= :old_page', $sql);
        $this->assertStringContainsString('b.deleted_at IS NULL OR b.deleted_at >= :old_blog', $sql);
    }
}
```

- [ ] **Step 2: Run the selection tests and verify they fail**

Run:

```bash
docker/exec vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php --filter 'testCollectsRootDeleteTargetsByTable|testIndependentChildQueriesExcludeRowsCoveredByParentCascades'
```

Expected: FAIL because `Eportfolio::getIdsToDelete()` is empty and `fetchRows()` does not exist in the production class yet.

- [ ] **Step 3: Add constants and selection helpers**

In `library/Schoolbox/Admin/DataRetention/Strategy/Eportfolio.php`, add the import:

```php
use PDO;
```

Add table constants inside the class:

```php
private const string TABLE_EPORTFOLIO_PAGE = 'eportfolio_page';
private const string TABLE_EPORTFOLIO_PERMISSION = 'eportfolio_permission';
private const string TABLE_EPORTFOLIO_COMPONENT_BLOG = 'eportfolio_component_blog';
private const string TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT = 'eportfolio_component_blog_comment';
private const string TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE = 'eportfolio_component_blog_image';
private const string TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM = 'eportfolio_component_filelist_item';
private const string TABLE_COMPONENT_INSTANCE = 'component_instance';
private const string TABLE_COMPONENT_CONFIG_INSTANCE = 'component_config_instance';
private const string TABLE_EPORTFOLIO_COMPONENT_ADVANCEDLIST_ITEM = 'eportfolio_component_advancedlist_item';
private const string TABLE_EPORTFOLIO_COMPONENT_BASICLIST_ITEM = 'eportfolio_component_basiclist_item';
private const string TABLE_EPORTFOLIO_COMPONENT_LINKLIST_ITEM = 'eportfolio_component_linklist_item';
private const string TABLE_EPORTFOLIO_COMPONENT_TEXTBOX_ITEM = 'eportfolio_component_textbox_item';
private const string COMPONENT_MODULE_EPORTFOLIO = 'eportfolio';
```

Replace `getIdsToDelete()` with:

```php
/**
 * @return list<array{table: string, id: int|string}>
 */
protected function getIdsToDelete(): array
{
    $old = $this->calculateDate();
    if ($old === null) {
        return [];
    }

    $this->log->info('Deleting ePortfolio data older than ' . $old);

    return array_merge(
        $this->findEligiblePageTargets($old),
        $this->findEligibleBlogTargets($old),
        $this->findEligibleBlogCommentTargets($old),
        $this->findEligibleBlogImageTargets($old),
        $this->findEligibleFileListItemTargets($old),
    );
}
```

Add these helper methods:

```php
/**
 * @return list<array{table: string, id: int|string}>
 */
private function findEligiblePageTargets(string $old): array
{
    return $this->fetchRows(
        "
            SELECT
                :table AS `table`,
                page_id AS id
            FROM " . self::TABLE_EPORTFOLIO_PAGE . "
            WHERE deleted_at IS NOT NULL AND deleted_at < :old
        ",
        ['table' => self::TABLE_EPORTFOLIO_PAGE, 'old' => $old],
    );
}

/**
 * @return list<array{table: string, id: int|string}>
 */
private function findEligibleBlogTargets(string $old): array
{
    return $this->fetchRows(
        "
            SELECT
                :table AS `table`,
                b.id
            FROM " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG . " b
            INNER JOIN " . self::TABLE_EPORTFOLIO_PAGE . " p ON p.page_id = b.page_id
            WHERE b.deleted_at IS NOT NULL
              AND b.deleted_at < :old_blog
              AND (p.deleted_at IS NULL OR p.deleted_at >= :old_page)
        ",
        ['table' => self::TABLE_EPORTFOLIO_COMPONENT_BLOG, 'old_blog' => $old, 'old_page' => $old],
    );
}

/**
 * @return list<array{table: string, id: int|string}>
 */
private function findEligibleBlogCommentTargets(string $old): array
{
    return $this->fetchRows(
        "
            SELECT
                :table AS `table`,
                c.id
            FROM " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT . " c
            INNER JOIN " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG . " b ON b.id = c.blog_id
            INNER JOIN " . self::TABLE_EPORTFOLIO_PAGE . " p ON p.page_id = b.page_id
            WHERE c.deleted_at IS NOT NULL
              AND c.deleted_at < :old_comment
              AND (b.deleted_at IS NULL OR b.deleted_at >= :old_blog)
              AND (p.deleted_at IS NULL OR p.deleted_at >= :old_page)
        ",
        [
            'table' => self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT,
            'old_comment' => $old,
            'old_blog' => $old,
            'old_page' => $old,
        ],
    );
}

/**
 * @return list<array{table: string, id: int|string}>
 */
private function findEligibleBlogImageTargets(string $old): array
{
    return $this->fetchRows(
        "
            SELECT
                :table AS `table`,
                i.id
            FROM " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE . " i
            INNER JOIN " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG . " b ON b.id = i.blog_id
            INNER JOIN " . self::TABLE_EPORTFOLIO_PAGE . " p ON p.page_id = b.page_id
            WHERE i.deleted_at IS NOT NULL
              AND i.deleted_at < :old_image
              AND (b.deleted_at IS NULL OR b.deleted_at >= :old_blog)
              AND (p.deleted_at IS NULL OR p.deleted_at >= :old_page)
        ",
        [
            'table' => self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE,
            'old_image' => $old,
            'old_blog' => $old,
            'old_page' => $old,
        ],
    );
}

/**
 * @return list<array{table: string, id: int|string}>
 */
private function findEligibleFileListItemTargets(string $old): array
{
    return $this->fetchRows(
        "
            SELECT
                :table AS `table`,
                f.id
            FROM " . self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM . " f
            LEFT JOIN " . self::TABLE_EPORTFOLIO_PAGE . " p ON p.page_id = f.page_id
            WHERE f.deleted_at IS NOT NULL
              AND f.deleted_at < :old_filelist_item
              AND (p.page_id IS NULL OR p.deleted_at IS NULL OR p.deleted_at >= :old_page)
        ",
        [
            'table' => self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM,
            'old_filelist_item' => $old,
            'old_page' => $old,
        ],
    );
}

/**
 * @param array<string, mixed> $params
 * @return list<array<string, mixed>>
 */
protected function fetchRows(string $query, array $params): array
{
    $statement = $this->database->prepare($query);
    $statement->execute($params);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}
```

- [ ] **Step 4: Run the selection tests**

Run:

```bash
docker/exec vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php --filter 'testCollectsRootDeleteTargetsByTable|testIndependentChildQueriesExcludeRowsCoveredByParentCascades'
```

Expected: PASS.

- [ ] **Step 5: Commit root target selection**

Run:

```bash
git add library/Schoolbox/Admin/DataRetention/Strategy/Eportfolio.php library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php
git commit -m "Select ePortfolio retention targets [SA-21885]"
```

## Task 3: Implement Page Cascade Deletion

**Files:**
- Modify: `library/Schoolbox/Admin/DataRetention/Strategy/Eportfolio.php`
- Modify: `library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php`

- [ ] **Step 1: Add the failing page cascade test**

First update the anonymous test strategy inside the existing `strategy()` helper. Add these public properties and replace the existing empty `findComponentInstanceIdsByPageIds()` and `findBlogIdsByPageIds()` overrides with the versions below:

```php
/** @var list<int> */
public array $pageComponentInstanceIds = [];

/** @var list<int> */
public array $pageBlogIds = [];

protected function findComponentInstanceIdsByPageIds(array $pageIds): array
{
    return $this->pageComponentInstanceIds;
}

protected function findBlogIdsByPageIds(array $pageIds): array
{
    return $this->pageBlogIds;
}
```

Then append this test to `EportfolioStrategyTest`:

```php
public function testDeletedPageCascadesThroughPageOwnedTables(): void
{
    ['strategy' => $strategy, 'state' => $state] = $this->strategy();

    $strategy->pageComponentInstanceIds = [501, 502];
    $strategy->pageBlogIds = [301, 302];

    $strategy->exposedDeleteChunk([
        ['table' => self::TABLE_PAGE, 'id' => 10],
    ]);

    $this->assertSame(
        [
            ['method' => 'foreign', 'table' => 'component_config_instance', 'column' => 'component_instance_id', 'ids' => [501, 502]],
            ['method' => 'foreign', 'table' => 'eportfolio_component_advancedlist_item', 'column' => 'component_instance_id', 'ids' => [501, 502]],
            ['method' => 'foreign', 'table' => 'eportfolio_component_basiclist_item', 'column' => 'component_instance_id', 'ids' => [501, 502]],
            ['method' => 'foreign', 'table' => 'eportfolio_component_linklist_item', 'column' => 'component_instance_id', 'ids' => [501, 502]],
            ['method' => 'foreign', 'table' => 'eportfolio_component_textbox_item', 'column' => 'component_instance_id', 'ids' => [501, 502]],
            ['method' => 'foreign', 'table' => 'eportfolio_component_blog_comment', 'column' => 'blog_id', 'ids' => [301, 302]],
            ['method' => 'foreign', 'table' => 'eportfolio_component_blog_image', 'column' => 'blog_id', 'ids' => [301, 302]],
            ['method' => 'foreign', 'table' => 'eportfolio_component_blog', 'column' => 'page_id', 'ids' => [10]],
            ['method' => 'foreign', 'table' => 'eportfolio_component_filelist_item', 'column' => 'page_id', 'ids' => [10]],
            ['method' => 'foreign', 'table' => 'eportfolio_permission', 'column' => 'page_id', 'ids' => [10]],
            ['method' => 'ids', 'table' => 'component_instance', 'column' => 'id', 'ids' => [501, 502]],
            ['method' => 'ids', 'table' => 'eportfolio_page', 'column' => 'page_id', 'ids' => [10]],
        ],
        $state->operations,
    );
}
```

- [ ] **Step 2: Run the page cascade test and verify it fails**

Run:

```bash
docker/exec vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php --filter testDeletedPageCascadesThroughPageOwnedTables
```

Expected: FAIL because `deleteChunk()` is empty.

- [ ] **Step 3: Implement grouping, lookup, and delete helpers**

In `Eportfolio.php`, replace `deleteChunk()` and add helpers:

```php
/**
 * @param array<int, array{table: string, id: int|string}> $chunk
 */
protected function deleteChunk(array $chunk): void
{
    $idsByTable = $this->groupTargetIdsByTable($chunk);
    $pageIds = $idsByTable[self::TABLE_EPORTFOLIO_PAGE] ?? [];

    $componentInstanceIds = $this->findComponentInstanceIdsByPageIds($pageIds);
    $pageBlogIds = $this->findBlogIdsByPageIds($pageIds);

    $deleted = [];

    $this->recordDeleted($deleted, self::TABLE_COMPONENT_CONFIG_INSTANCE, $this->deleteRowsByForeignIds(self::TABLE_COMPONENT_CONFIG_INSTANCE, 'component_instance_id', $componentInstanceIds, 'component_instance_id'));
    $this->recordDeleted($deleted, self::TABLE_EPORTFOLIO_COMPONENT_ADVANCEDLIST_ITEM, $this->deleteRowsByForeignIds(self::TABLE_EPORTFOLIO_COMPONENT_ADVANCEDLIST_ITEM, 'component_instance_id', $componentInstanceIds, 'component_instance_id'));
    $this->recordDeleted($deleted, self::TABLE_EPORTFOLIO_COMPONENT_BASICLIST_ITEM, $this->deleteRowsByForeignIds(self::TABLE_EPORTFOLIO_COMPONENT_BASICLIST_ITEM, 'component_instance_id', $componentInstanceIds, 'component_instance_id'));
    $this->recordDeleted($deleted, self::TABLE_EPORTFOLIO_COMPONENT_LINKLIST_ITEM, $this->deleteRowsByForeignIds(self::TABLE_EPORTFOLIO_COMPONENT_LINKLIST_ITEM, 'component_instance_id', $componentInstanceIds, 'component_instance_id'));
    $this->recordDeleted($deleted, self::TABLE_EPORTFOLIO_COMPONENT_TEXTBOX_ITEM, $this->deleteRowsByForeignIds(self::TABLE_EPORTFOLIO_COMPONENT_TEXTBOX_ITEM, 'component_instance_id', $componentInstanceIds, 'component_instance_id'));

    $this->recordDeleted($deleted, self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, $this->deleteRowsByForeignIds(self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, 'blog_id', $pageBlogIds, 'page_blog_id'));
    $this->recordDeleted($deleted, self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, $this->deleteRowsByForeignIds(self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, 'blog_id', $pageBlogIds, 'page_blog_id'));

    $this->recordDeleted($deleted, self::TABLE_EPORTFOLIO_COMPONENT_BLOG, $this->deleteRowsByForeignIds(self::TABLE_EPORTFOLIO_COMPONENT_BLOG, 'page_id', $pageIds, 'page_id'));
    $this->recordDeleted($deleted, self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM, $this->deleteRowsByForeignIds(self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM, 'page_id', $pageIds, 'page_id'));
    $this->recordDeleted($deleted, self::TABLE_EPORTFOLIO_PERMISSION, $this->deleteRowsByForeignIds(self::TABLE_EPORTFOLIO_PERMISSION, 'page_id', $pageIds, 'page_id'));
    $this->recordDeleted($deleted, self::TABLE_COMPONENT_INSTANCE, $this->deleteRowsByIds(self::TABLE_COMPONENT_INSTANCE, 'id', $componentInstanceIds, 'component_instance_id'));
    $this->recordDeleted($deleted, self::TABLE_EPORTFOLIO_PAGE, $this->deleteRowsByIds(self::TABLE_EPORTFOLIO_PAGE, 'page_id', $pageIds, 'page_id'));

    $this->logChunkResult($deleted);
}

/**
 * @param array<string, int> $deleted
 */
private function recordDeleted(array &$deleted, string $table, int $count): void
{
    $deleted[$table] = ($deleted[$table] ?? 0) + $count;
}

/**
 * @param array<int, array{table: string, id: int|string}> $chunk
 * @return array<string, list<int>>
 */
private function groupTargetIdsByTable(array $chunk): array
{
    $idsByTable = [];

    foreach ($chunk as $target) {
        $id = (int)$target['id'];
        if ($id <= 0) {
            continue;
        }

        $idsByTable[$target['table']][] = $id;
    }

    foreach ($idsByTable as $table => $ids) {
        $ids = array_values(array_unique($ids));
        sort($ids);
        $idsByTable[$table] = $ids;
        $this->log->debug(sprintf('Deleting %s ids: %s', $table, implode(', ', $ids)));
    }

    return $idsByTable;
}

/**
 * @param list<int> $pageIds
 * @return list<int>
 */
protected function findComponentInstanceIdsByPageIds(array $pageIds): array
{
    if ($pageIds === []) {
        return [];
    }

    $placeholders = \Alaress\Database\Predicate\Builder::getArrayPlaceholders($pageIds, 'page_id');
    $rows = $this->fetchRows(
        "
            SELECT id
            FROM " . self::TABLE_COMPONENT_INSTANCE . "
            WHERE module = :module
              AND identifier IN {$placeholders}
        ",
        array_merge(
            ['module' => self::COMPONENT_MODULE_EPORTFOLIO],
            \Alaress\Database\Predicate\Builder::getArrayParameters($pageIds, 'page_id'),
        ),
    );

    return array_map(static fn(array $row): int => (int)$row['id'], $rows);
}

/**
 * @param list<int> $pageIds
 * @return list<int>
 */
protected function findBlogIdsByPageIds(array $pageIds): array
{
    if ($pageIds === []) {
        return [];
    }

    $placeholders = \Alaress\Database\Predicate\Builder::getArrayPlaceholders($pageIds, 'page_id');
    $rows = $this->fetchRows(
        "
            SELECT id
            FROM " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG . "
            WHERE page_id IN {$placeholders}
        ",
        \Alaress\Database\Predicate\Builder::getArrayParameters($pageIds, 'page_id'),
    );

    return array_map(static fn(array $row): int => (int)$row['id'], $rows);
}

/**
 * @param list<int> $ids
 */
protected function deleteRowsByIds(string $table, string $idColumn, array $ids, string $parameterPrefix): int
{
    return $this->deleteRowsByForeignIds($table, $idColumn, $ids, $parameterPrefix);
}

/**
 * @param list<int> $ids
 */
protected function deleteRowsByForeignIds(string $table, string $column, array $ids, string $parameterPrefix): int
{
    if ($ids === []) {
        return 0;
    }

    $placeholders = \Alaress\Database\Predicate\Builder::getArrayPlaceholders($ids, $parameterPrefix);
    $statement = $this->getPreparedStatement($this->deleteStatement($table) . " FROM {$table} WHERE {$column} IN {$placeholders}");
    $statement->execute(\Alaress\Database\Predicate\Builder::getArrayParameters($ids, $parameterPrefix));

    return $statement->rowCount();
}

/**
 * @param array<string, int> $deleted
 */
private function logChunkResult(array $deleted): void
{
    $prefix = $this->perform ? 'Deleted' : 'Would have deleted';

    foreach ($deleted as $table => $count) {
        $this->log->notice(sprintf("%s '%s' rows from %s", $prefix, $count, $table));
    }
}
```

- [ ] **Step 4: Run the page cascade test**

Run:

```bash
docker/exec vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php --filter testDeletedPageCascadesThroughPageOwnedTables
```

Expected: PASS.

- [ ] **Step 5: Commit page cascade deletion**

Run:

```bash
git add library/Schoolbox/Admin/DataRetention/Strategy/Eportfolio.php library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php
git commit -m "Cascade ePortfolio page retention deletes [SA-21885]"
```

## Task 4: Implement Independent Child Cleanup

**Files:**
- Modify: `library/Schoolbox/Admin/DataRetention/Strategy/Eportfolio.php`
- Modify: `library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php`

- [ ] **Step 1: Add failing independent cleanup tests**

Append these tests to `EportfolioStrategyTest`:

```php
public function testIndependentlyDeletedChildrenAreRemovedWithoutDeletingExternalTables(): void
{
    ['strategy' => $strategy, 'state' => $state] = $this->strategy();

    $strategy->exposedDeleteChunk([
        ['table' => self::TABLE_BLOG_COMMENT, 'id' => 30],
        ['table' => self::TABLE_BLOG_IMAGE, 'id' => 40],
        ['table' => self::TABLE_FILELIST_ITEM, 'id' => 50],
    ]);

    $this->assertSame(
        [
            ['method' => 'ids', 'table' => 'eportfolio_component_blog_comment', 'column' => 'id', 'ids' => [30]],
            ['method' => 'ids', 'table' => 'eportfolio_component_blog_image', 'column' => 'id', 'ids' => [40]],
            ['method' => 'ids', 'table' => 'eportfolio_component_filelist_item', 'column' => 'id', 'ids' => [50]],
        ],
        $state->operations,
    );

    $tables = array_column($state->operations, 'table');
    $this->assertNotContains('my_file', $tables);
    $this->assertNotContains('storage', $tables);
}

public function testIndependentlyDeletedBlogRemovesItsChildrenBeforeTheBlog(): void
{
    ['strategy' => $strategy, 'state' => $state] = $this->strategy();

    $strategy->exposedDeleteChunk([
        ['table' => self::TABLE_BLOG, 'id' => 20],
    ]);

    $this->assertSame(
        [
            ['method' => 'foreign', 'table' => 'eportfolio_component_blog_comment', 'column' => 'blog_id', 'ids' => [20]],
            ['method' => 'foreign', 'table' => 'eportfolio_component_blog_image', 'column' => 'blog_id', 'ids' => [20]],
            ['method' => 'ids', 'table' => 'eportfolio_component_blog', 'column' => 'id', 'ids' => [20]],
        ],
        $state->operations,
    );
}
```

- [ ] **Step 2: Run the independent cleanup tests and verify they fail**

Run:

```bash
docker/exec vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php --filter 'testIndependentlyDeletedChildrenAreRemovedWithoutDeletingExternalTables|testIndependentlyDeletedBlogRemovesItsChildrenBeforeTheBlog'
```

Expected: FAIL because `deleteChunk()` only handles page cascade rows.

- [ ] **Step 3: Extend `deleteChunk()` for independent child targets**

In `Eportfolio::deleteChunk()`, after `$idsByTable` is created and before page cascade deletes, add:

```php
$blogIds = $idsByTable[self::TABLE_EPORTFOLIO_COMPONENT_BLOG] ?? [];
$blogCommentIds = $idsByTable[self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT] ?? [];
$blogImageIds = $idsByTable[self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE] ?? [];
$fileListItemIds = $idsByTable[self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM] ?? [];
```

Then place these deletes before deleting page-owned blogs:

```php
$this->recordDeleted($deleted, self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, $this->deleteRowsByIds(self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, 'id', $blogCommentIds, 'blog_comment_id'));
$this->recordDeleted($deleted, self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, $this->deleteRowsByIds(self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, 'id', $blogImageIds, 'blog_image_id'));
$this->recordDeleted($deleted, self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM, $this->deleteRowsByIds(self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM, 'id', $fileListItemIds, 'filelist_item_id'));
$this->recordDeleted($deleted, self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, $this->deleteRowsByForeignIds(self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, 'blog_id', $blogIds, 'blog_id'));
$this->recordDeleted($deleted, self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, $this->deleteRowsByForeignIds(self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, 'blog_id', $blogIds, 'blog_id'));
$this->recordDeleted($deleted, self::TABLE_EPORTFOLIO_COMPONENT_BLOG, $this->deleteRowsByIds(self::TABLE_EPORTFOLIO_COMPONENT_BLOG, 'id', $blogIds, 'blog_id'));
```

Keep the page cascade blog-child deletes after these independent deletes. The selectors already exclude children whose page or blog parent is eligible, so duplicate work should not occur in normal operation.

- [ ] **Step 4: Run the independent cleanup tests**

Run:

```bash
docker/exec vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php --filter 'testIndependentlyDeletedChildrenAreRemovedWithoutDeletingExternalTables|testIndependentlyDeletedBlogRemovesItsChildrenBeforeTheBlog'
```

Expected: PASS.

- [ ] **Step 5: Run all Eportfolio strategy tests**

Run:

```bash
docker/exec vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit independent cleanup**

Run:

```bash
git add library/Schoolbox/Admin/DataRetention/Strategy/Eportfolio.php library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php
git commit -m "Delete independent ePortfolio retention children [SA-21885]"
```

## Task 5: Implement `hasDataToDelete()` Counts

**Files:**
- Modify: `library/Schoolbox/Admin/DataRetention/Strategy/Eportfolio.php`
- Modify: `library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php`

- [ ] **Step 1: Add failing count tests**

Append these tests to `EportfolioStrategyTest`:

```php
public function testHasDataToDeleteReturnsFalseWhenNoEligibleRowsExist(): void
{
    ['strategy' => $strategy] = $this->strategy([
        [[
            'pages' => 0,
            'blogs' => 0,
            'blog_comments' => 0,
            'blog_images' => 0,
            'filelist_items' => 0,
        ]],
    ]);

    $hasData = \Closure::bind(
        fn (): bool => $this->hasDataToDelete(),
        $strategy,
        Eportfolio::class,
    );

    $this->assertFalse($hasData());
}

public function testHasDataToDeleteReturnsTrueWhenAnyEligibleRowsExist(): void
{
    ['strategy' => $strategy] = $this->strategy([
        [[
            'pages' => 1,
            'blogs' => 0,
            'blog_comments' => 0,
            'blog_images' => 0,
            'filelist_items' => 0,
        ]],
    ]);

    $hasData = \Closure::bind(
        fn (): bool => $this->hasDataToDelete(),
        $strategy,
        Eportfolio::class,
    );

    $this->assertTrue($hasData());
}
```

- [ ] **Step 2: Run the count tests and verify they fail**

Run:

```bash
docker/exec vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php --filter 'testHasDataToDeleteReturnsFalseWhenNoEligibleRowsExist|testHasDataToDeleteReturnsTrueWhenAnyEligibleRowsExist'
```

Expected: FAIL because `hasDataToDelete()` is empty.

- [ ] **Step 3: Implement `hasDataToDelete()`**

In `Eportfolio.php`, replace `hasDataToDelete()` with:

```php
protected function hasDataToDelete(): bool
{
    $old = $this->calculateDate();
    if ($old === null) {
        return false;
    }

    $counts = $this->fetchRows(
        "
            SELECT
                (SELECT COUNT(*) FROM " . self::TABLE_EPORTFOLIO_PAGE . " p
                    WHERE p.deleted_at IS NOT NULL AND p.deleted_at < :old_page_root) AS pages,
                (SELECT COUNT(*) FROM " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG . " b
                    INNER JOIN " . self::TABLE_EPORTFOLIO_PAGE . " p ON p.page_id = b.page_id
                    WHERE b.deleted_at IS NOT NULL AND b.deleted_at < :old_blog
                      AND (p.deleted_at IS NULL OR p.deleted_at >= :old_blog_page)) AS blogs,
                (SELECT COUNT(*) FROM " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT . " c
                    INNER JOIN " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG . " b ON b.id = c.blog_id
                    INNER JOIN " . self::TABLE_EPORTFOLIO_PAGE . " p ON p.page_id = b.page_id
                    WHERE c.deleted_at IS NOT NULL AND c.deleted_at < :old_comment
                      AND (b.deleted_at IS NULL OR b.deleted_at >= :old_comment_blog)
                      AND (p.deleted_at IS NULL OR p.deleted_at >= :old_comment_page)) AS blog_comments,
                (SELECT COUNT(*) FROM " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE . " i
                    INNER JOIN " . self::TABLE_EPORTFOLIO_COMPONENT_BLOG . " b ON b.id = i.blog_id
                    INNER JOIN " . self::TABLE_EPORTFOLIO_PAGE . " p ON p.page_id = b.page_id
                    WHERE i.deleted_at IS NOT NULL AND i.deleted_at < :old_image
                      AND (b.deleted_at IS NULL OR b.deleted_at >= :old_image_blog)
                      AND (p.deleted_at IS NULL OR p.deleted_at >= :old_image_page)) AS blog_images,
                (SELECT COUNT(*) FROM " . self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM . " f
                    LEFT JOIN " . self::TABLE_EPORTFOLIO_PAGE . " p ON p.page_id = f.page_id
                    WHERE f.deleted_at IS NOT NULL AND f.deleted_at < :old_filelist_item
                      AND (p.page_id IS NULL OR p.deleted_at IS NULL OR p.deleted_at >= :old_filelist_page)) AS filelist_items
        ",
        [
            'old_page_root' => $old,
            'old_blog' => $old,
            'old_blog_page' => $old,
            'old_comment' => $old,
            'old_comment_blog' => $old,
            'old_comment_page' => $old,
            'old_image' => $old,
            'old_image_blog' => $old,
            'old_image_page' => $old,
            'old_filelist_item' => $old,
            'old_filelist_page' => $old,
        ],
    )[0] ?? [];

    $hasData = false;
    foreach ($counts as $name => $count) {
        $count = (int)$count;
        $this->log->info(sprintf("Found '%s' ePortfolio %s to delete", $count, $name));
        $hasData = $hasData || $count > 0;
    }

    return $hasData;
}
```

- [ ] **Step 4: Run all Eportfolio strategy tests**

Run:

```bash
docker/exec vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit count logging**

Run:

```bash
git add library/Schoolbox/Admin/DataRetention/Strategy/Eportfolio.php library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php
git commit -m "Count ePortfolio retention targets [SA-21885]"
```

## Task 6: Final Verification And Review

**Files:**
- Verify: `library/Schoolbox/Admin/DataRetention/Strategy/Eportfolio.php`
- Verify: `library/Schoolbox/Admin/DataRetention/ValidStrats.php`
- Verify: `library/Schoolbox/Admin/DataRetention/CommandLineTool.php`
- Verify: `library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php`
- Verify: `library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php`
- Verify: `library/Schoolbox/Admin/DataRetention/Test/CommandLineToolStrategyBusTest.php`

- [ ] **Step 1: Run the focused PHPUnit suite**

Run:

```bash
docker/exec vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php library/Schoolbox/Admin/DataRetention/Test/CommandLineToolStrategyBusTest.php
```

Expected: PASS.

- [ ] **Step 2: Run PHPCS on changed PHP files**

Run:

```bash
docker/exec vendor/bin/phpcs library/Schoolbox/Admin/DataRetention/Strategy/Eportfolio.php library/Schoolbox/Admin/DataRetention/ValidStrats.php library/Schoolbox/Admin/DataRetention/CommandLineTool.php library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php library/Schoolbox/Admin/DataRetention/Test/CommandLineToolStrategyBusTest.php
```

Expected: PASS with no errors.

- [ ] **Step 3: Run PHPStan on changed production files**

Run:

```bash
docker/exec vendor/bin/phpstan analyze library/Schoolbox/Admin/DataRetention/Strategy/Eportfolio.php library/Schoolbox/Admin/DataRetention/ValidStrats.php library/Schoolbox/Admin/DataRetention/CommandLineTool.php
```

Expected: PASS or only existing baseline issues outside these files.

- [ ] **Step 4: Inspect the final diff for scope**

Run:

```bash
git diff upstream/develop...HEAD -- library/Schoolbox/Admin/DataRetention/Strategy/Eportfolio.php library/Schoolbox/Admin/DataRetention/ValidStrats.php library/Schoolbox/Admin/DataRetention/CommandLineTool.php library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php library/Schoolbox/Admin/DataRetention/Test/CommandLineToolStrategyBusTest.php
```

Expected: Diff only wires `ePortfolio`, implements the ePortfolio strategy, and adds focused tests.

- [ ] **Step 5: Commit verification fixes if any were needed**

If verification required formatting or small fixes, run:

```bash
git add library/Schoolbox/Admin/DataRetention/Strategy/Eportfolio.php library/Schoolbox/Admin/DataRetention/ValidStrats.php library/Schoolbox/Admin/DataRetention/CommandLineTool.php library/Schoolbox/Admin/DataRetention/Test/EportfolioStrategyTest.php library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php library/Schoolbox/Admin/DataRetention/Test/CommandLineToolStrategyBusTest.php
git commit -m "Verify ePortfolio data retention strategy [SA-21885]"
```

Expected: A commit is created only if verification produced file changes.

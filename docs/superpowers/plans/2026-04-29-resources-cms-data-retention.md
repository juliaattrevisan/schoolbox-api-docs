# Resources CMS Data Retention Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Resources (CMS) DataRetention strategy for `SA-21886` that hard-deletes soft-deleted `folder` trees and their CMS-owned related data after the configured CMS retention period.

**Architecture:** Add `Schoolbox\Admin\DataRetention\Strategy\Cms` beside the currently tracked `Moments` and `MyFiles` strategies. Register it through `ValidStrats` and `CommandLineTool`, then implement folder subtree expansion, excluded relation blocker detection, FK-safe dependency deletion, dry-run counts, and leaf-first folder deletion inside the strategy.

**Tech Stack:** PHP 8.4, PHPUnit, MariaDB 11.8 SQL via `IConnector`, PSR-3 logging, Symfony Lock.

---

## 2026-04-30 Revision: Leaf-Safe CMS Folder Deletion

**Clarified behavior:** CMS retention must not hard-delete child folders that are still active. In this plan, an active child folder means `folder.deleted_at IS NULL`.

**Implementation adjustment:**

- Candidate selection in `Cms::getIdsToDelete()` must skip any retained soft-deleted folder with an active direct child.
- Cascade expansion in `Cms::expandFolderCascade()` must only traverse soft-deleted child folders.
- Folder deletion remains leaf-first for the resulting soft-deleted folder set.
- A soft-deleted parent with active children is left for a later run, after those children have been soft-deleted and hard-deleted.

**Verification adjustment:**

- Add a regression test proving an active child folder is not included in `deleteFolders()`.
- Add a query-level test proving `getIdsToDelete()` includes a `NOT EXISTS` guard against active child folders.

---

## File Map

| Action | Path |
|---|---|
| Create | `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php` |
| Create | `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php` |
| Modify | `library/Schoolbox/Admin/DataRetention/ValidStrats.php` |
| Modify | `library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php` |
| Modify | `library/Schoolbox/Admin/DataRetention/CommandLineTool.php` |

---

## Task 1: Register the CMS Strategy Name

**Files:**
- Modify: `library/Schoolbox/Admin/DataRetention/ValidStrats.php`
- Modify: `library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php`

- [ ] **Step 1: Add failing `ValidStrats` test coverage**

In `library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php`, update `providerValidStrats()` so the `valid and invalid mixed` case includes `cms`:

```php
            'valid and invalid mixed' => [
                'moments,unknown,myFiles,cms',
                ['moments', 'myFiles', 'cms'],
            ],
```

Add a new case to `providerValidStrats()`:

```php
            'cms only' => [
                'cms',
                ['cms'],
            ],
```

Add a new case to `providerStratEnabled()`:

```php
            'cms enabled' => [
                'cms',
                'cms',
                true,
            ],
```

- [ ] **Step 2: Run the focused test and verify it fails**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php
```

Expected: FAIL because `cms` is not yet in `ValidStrats::STRATS_WHITELIST`.

- [ ] **Step 3: Add `cms` to `ValidStrats`**

In `library/Schoolbox/Admin/DataRetention/ValidStrats.php`, add the constant after `MYFILES`:

```php
    // retain soft-deleted Resources (CMS) folders and related folder-owned content
    public const string CMS = 'cms';
```

Update `STRATS_WHITELIST`:

```php
    public const array STRATS_WHITELIST = [
        self::MOMENTS,
        self::MYFILES,
        self::CMS,
    ];
```

- [ ] **Step 4: Run the focused test and verify it passes**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add library/Schoolbox/Admin/DataRetention/ValidStrats.php library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php
git commit -m "Register CMS data retention strategy [SA-21886]" -m "Co-Authored-By: Codex <codex@openai.com>"
```

---

## Task 6: Prevent CMS Retention Deleting Active Child Folders

**Files:**
- Modify: `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php`
- Modify: `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php`

- [ ] **Step 1: Add a failing regression test for active child folders**

In `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php`, extend the test subclass so `findChildFolderIds()` can distinguish all children from soft-deleted children. Add a test where folder `10` has children `11` and `12`, only `11` is soft-deleted, and `12` is active:

```php
public function testDeleteChunkDoesNotDeleteActiveChildFolders(): void
{
    ['strategy' => $strategy, 'state' => $state] = $this->strategy(
        [
            10 => [11, 12],
            11 => [],
            12 => [],
        ],
        [],
        [],
        softDeletedFolderIds: [10, 11],
    );

    $this->delete($strategy, [
        ['id' => 10, 'table' => 'folder'],
    ]);

    $this->assertSame([11, 10], $state->deletedFolders);
    $this->assertNotContains(12, $state->deletedFolders);
}
```

- [ ] **Step 2: Run the regression test and verify it fails**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php --filter testDeleteChunkDoesNotDeleteActiveChildFolders
```

Expected: FAIL because the current cascade includes every child folder returned by `findChildFolderIds()`.

- [ ] **Step 3: Add a query-level test for candidate selection**

In `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php`, add a bound-call helper for `getIdsToDelete()` and a mock statement that captures prepared SQL. Assert the SQL contains:

```sql
NOT EXISTS
child.parent = folder.id
child.deleted_at IS NULL
```

- [ ] **Step 4: Update CMS child lookup and cascade implementation**

In `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php`:

- Change `getIdsToDelete()` to include `NOT EXISTS` against active direct children.
- Add a protected helper for soft-deleted child folder lookup.
- Change `expandFolderCascade()` to call the soft-deleted child lookup, not the all-child lookup.

- [ ] **Step 5: Run the focused CMS strategy tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add library/Schoolbox/Admin/DataRetention/Strategy/Cms.php library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
git commit -m "Prevent CMS retention deleting active child folders [SA-21886]" -m "Co-Authored-By: Codex <codex@openai.com>"
```

---

## Task 2: Add Focused CMS Strategy Tests

**Files:**
- Create: `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php`
- Create later: `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php`

- [ ] **Step 1: Create failing strategy tests**

Create `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php`:

```php
<?php

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Test;

use Alaress\Database\Connector\IConnector;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\DataRetention\RetentionPeriod;
use Schoolbox\Admin\DataRetention\Strategy\Cms;
use Schoolbox\IConfigLoader;
use Symfony\Component\Lock\LockInterface;

class CmsStrategyTest extends TestCase
{
    /**
     * @param array<int, list<int>> $folderChildren
     * @param list<int> $blockedFolderIds
     * @param array<string, array<int, list<int>>> $lookupIds
     * @return array{strategy: Cms, state: object{calls: list<array{0: string, 1: list<int>}>}}
     */
    private function strategy(array $folderChildren, array $blockedFolderIds = [], array $lookupIds = []): array
    {
        $logger = $this->createMock(LoggerInterface::class);
        $database = $this->createMock(IConnector::class);
        $lock = $this->createMock(LockInterface::class);
        $config = $this->createMock(IConfigLoader::class);

        $logger->method('debug');
        $logger->method('info');
        $logger->method('notice');
        $logger->method('warning');

        $state = new class {
            /** @var list<array{0: string, 1: list<int>}> */
            public array $calls = [];
        };

        $strategy = new class (
            $logger,
            true,
            $database,
            $lock,
            $config,
            time() + 3600,
            new RetentionPeriod(30),
            $folderChildren,
            $blockedFolderIds,
            $lookupIds,
            $state,
        ) extends Cms {
            /** @var array<int, list<int>> */
            private array $folderChildren;

            /** @var list<int> */
            private array $blockedFolderIds;

            /** @var array<string, array<int, list<int>>> */
            private array $lookupIds;

            private object $state;

            /**
             * @param array<int, list<int>> $folderChildren
             * @param list<int> $blockedFolderIds
             * @param array<string, array<int, list<int>>> $lookupIds
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
                object $state,
            ) {
                parent::__construct($logger, $perform, $database, $lock, $config, $endTime, $retentionPeriod);
                $this->folderChildren = $folderChildren;
                $this->blockedFolderIds = $blockedFolderIds;
                $this->lookupIds = $lookupIds;
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
             * @param array<int, int> $ids
             */
            protected function setWikiLatestRevisionNull(array $ids): int
            {
                $this->state->calls[] = ['wiki.latestRevision', array_values($ids)];

                return count($ids);
            }

            /**
             * @param array<int, int> $ids
             */
            protected function deleteByIds(string $table, string $column, array $ids): int
            {
                $this->state->calls[] = [$table, array_values($ids)];

                return count($ids);
            }

            /**
             * @param array<int, int> $folderIds
             */
            protected function deleteByFolderIds(string $table, string $column, array $folderIds): int
            {
                $this->state->calls[] = [$table, array_values($folderIds)];

                return count($folderIds);
            }

            /**
             * @param array<int, int> $folderIds
             */
            protected function deleteFolders(array $folderIds): int
            {
                $this->state->calls[] = ['folder', array_values($folderIds)];

                return count($folderIds);
            }
        };

        return [
            'strategy' => $strategy,
            'state' => $state,
        ];
    }

    /**
     * @param Cms $strategy
     * @param array<int, array{id: int, table?: string}> $chunk
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

    public function testStrategyIdentity(): void
    {
        ['strategy' => $strategy] = $this->strategy([]);

        $this->assertSame('Resources (CMS) Retention', $strategy->getRetentionLabel());
        $this->assertSame('cms', $strategy->getStrategyName());
    }

    public function testDeletesExpandedFoldersLeafFirst(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy([
            10 => [11, 12],
            11 => [13],
            12 => [],
            13 => [],
        ]);

        $this->delete($strategy, [
            ['id' => 10],
        ]);

        $this->assertContains(['folder', [13, 11, 12, 10]], $state->calls);
    }

    public function testDeletesNestedDependenciesBeforeFolders(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy(
            [
                20 => [],
            ],
            [],
            [
                'blog' => [20 => [201]],
                'forumThread' => [20 => [301]],
                'poll' => [20 => [401]],
                'wiki' => [20 => [501]],
                'component_instance' => [20 => [601]],
            ],
        );

        $this->delete($strategy, [
            ['id' => 20],
        ]);

        $this->assertSame(
            [
                ['blog_comment', [201]],
                ['blog_image', [201]],
                ['blog', [201]],
                ['forumPost', [301]],
                ['forumThread', [301]],
                ['pollLog', [401]],
                ['poll', [401]],
                ['wiki.latestRevision', [501]],
                ['wikiRevision', [501]],
                ['wiki', [501]],
                ['component_config_instance', [601]],
                ['eportfolio_component_advancedlist_item', [601]],
                ['eportfolio_component_basiclist_item', [601]],
                ['eportfolio_component_linklist_item', [601]],
                ['eportfolio_component_textbox_item', [601]],
                ['component_instance', [601]],
            ],
            array_slice($state->calls, 0, 16),
        );
    }

    public function testSkipsFoldersBlockedByExcludedRelations(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy(
            [
                30 => [31],
                31 => [],
            ],
            [30, 31],
        );

        $this->delete($strategy, [
            ['id' => 30],
        ]);

        $this->assertSame([], $state->calls);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
```

Expected: FAIL because `Schoolbox\Admin\DataRetention\Strategy\Cms` does not exist yet.

- [ ] **Step 3: Commit the failing tests**

```bash
git add library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
git commit -m "Add CMS data retention strategy tests [SA-21886]" -m "Co-Authored-By: Codex <codex@openai.com>"
```

---

## Task 3: Implement the CMS Strategy

**Files:**
- Create: `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php`
- Test: `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php`

- [ ] **Step 1: Create the strategy shell**

Create `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php`:

```php
<?php

/**
 * Resources (CMS), retain soft deleted data for a certain time
 *
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Strategy;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Admin\DataRetention\ValidStrats;
use Schoolbox\Config\ConfigMapper;

class Cms extends Base
{
    protected string $retentionConfigKey = ConfigMapper::NAME_DATA_RETENTION_CMS;

    private const string TABLE_FOLDER = 'folder';

    public function getRetentionLabel(): string
    {
        return 'Resources (CMS) Retention';
    }

    public function getStrategyName(): string
    {
        return ValidStrats::CMS;
    }

    protected function hasDataToDelete(): bool
    {
        $query = "
            SELECT COUNT(*) AS folders
            FROM " . self::TABLE_FOLDER . "
            WHERE deleted_at IS NOT NULL AND deleted_at < :old
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'old' => $this->calculateDate(),
        ]);

        $toDelete = $statement->fetch();
        if (empty($toDelete['folders'])) {
            $this->log->info("Found '0' Resources (CMS) folders to delete");

            return false;
        }

        $this->log->info(sprintf("Found '%s' Resources (CMS) folders to delete", $toDelete['folders']));

        return true;
    }

    protected function getIdsToDelete(): array
    {
        $this->log->info('Deleting Resources (CMS) folders older than ' . $this->calculateDate());

        $query = "
            SELECT id
            FROM " . self::TABLE_FOLDER . "
            WHERE deleted_at IS NOT NULL AND deleted_at < :old
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'old' => $this->calculateDate(),
        ]);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }
}
```

- [ ] **Step 2: Run the focused tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
```

Expected: FAIL because `Cms` does not implement `deleteChunk()`.

- [ ] **Step 3: Add cascade and deletion helpers**

Add these methods to `Cms` after `getIdsToDelete()`:

```php
    /**
     * @param array<int, array{id: int|string}> $chunk
     */
    protected function deleteChunk(array $chunk): void
    {
        $requestedFolderIds = $this->normalizeIds(array_column($chunk, 'id'));
        $folderIds = $this->expandFolderCascade($requestedFolderIds);
        $blockedFolderIds = $this->findBlockedFolderIds($folderIds);

        if ($blockedFolderIds !== []) {
            $this->log->warning(sprintf(
                "Skipping Resources (CMS) chunk because '%s' folders are referenced by excluded assessment/course relations: %s",
                count($blockedFolderIds),
                implode(', ', $blockedFolderIds),
            ));

            return;
        }

        $blogIds = $this->findIdsByFolder('blog', 'id', 'folder_id', $folderIds);
        $forumThreadIds = $this->findIdsByFolder('forumThread', 'threadID', 'categoryID', $folderIds);
        $pollIds = $this->findIdsByFolder('poll', 'id', 'folder_id', $folderIds);
        $wikiIds = $this->findIdsByFolder('wiki', 'wikiID', 'categoryID', $folderIds);
        $componentInstanceIds = $this->findIdsByFolder('component_instance', 'id', 'identifier', $folderIds);

        $counts = [];
        $counts['blog_comment'] = $this->deleteByIds('blog_comment', 'blog_id', $blogIds);
        $counts['blog_image'] = $this->deleteByIds('blog_image', 'blog_id', $blogIds);
        $counts['blog'] = $this->deleteByIds('blog', 'id', $blogIds);
        $counts['forumPost'] = $this->deleteByIds('forumPost', 'threadID', $forumThreadIds);
        $counts['forumThread'] = $this->deleteByIds('forumThread', 'threadID', $forumThreadIds);
        $counts['pollLog'] = $this->deleteByIds('pollLog', 'poll_id', $pollIds);
        $counts['poll'] = $this->deleteByIds('poll', 'id', $pollIds);
        $counts['wiki_unlinked'] = $this->setWikiLatestRevisionNull($wikiIds);
        $counts['wikiRevision'] = $this->deleteByIds('wikiRevision', 'wikiID', $wikiIds);
        $counts['wiki'] = $this->deleteByIds('wiki', 'wikiID', $wikiIds);
        $counts['component_config_instance'] = $this->deleteByIds('component_config_instance', 'component_instance_id', $componentInstanceIds);
        $counts['eportfolio_component_advancedlist_item'] = $this->deleteByIds('eportfolio_component_advancedlist_item', 'component_instance_id', $componentInstanceIds);
        $counts['eportfolio_component_basiclist_item'] = $this->deleteByIds('eportfolio_component_basiclist_item', 'component_instance_id', $componentInstanceIds);
        $counts['eportfolio_component_linklist_item'] = $this->deleteByIds('eportfolio_component_linklist_item', 'component_instance_id', $componentInstanceIds);
        $counts['eportfolio_component_textbox_item'] = $this->deleteByIds('eportfolio_component_textbox_item', 'component_instance_id', $componentInstanceIds);
        $counts['component_instance'] = $this->deleteByIds('component_instance', 'id', $componentInstanceIds);

        foreach ($this->folderOwnedTables() as $table => $column) {
            $counts[$table] = $this->deleteByFolderIds($table, $column, $folderIds);
        }

        $counts['folder'] = $this->deleteFolders($folderIds);

        $prefix = $this->perform ? 'Deleted' : 'Would have deleted';
        $this->log->notice(sprintf(
            "%s Resources (CMS) rows: %s",
            $prefix,
            implode(', ', array_map(
                static fn(string $table, int $count): string => sprintf("%s=%s", $table, $count),
                array_keys($counts),
                $counts,
            )),
        ));
    }

    /**
     * @param array<int, int|string> $ids
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = array_map(static fn(int|string $id): int => (int) $id, $ids);
        $normalized = array_values(array_unique(array_filter($normalized, static fn(int $id): bool => $id > 0)));

        sort($normalized);

        return $normalized;
    }

    /**
     * @param array<int, int> $rootFolderIds
     * @return array<int, int>
     */
    protected function expandFolderCascade(array $rootFolderIds): array
    {
        $pending = array_map(
            static fn(int $folderId): array => ['id' => $folderId, 'expanded' => false],
            array_reverse($this->normalizeIds($rootFolderIds)),
        );
        $visited = [];
        $folderIds = [];

        while ($pending !== []) {
            ['id' => $folderId, 'expanded' => $expanded] = array_pop($pending);

            if ($expanded) {
                $folderIds[] = $folderId;
                continue;
            }

            if (isset($visited[$folderId])) {
                continue;
            }

            $visited[$folderId] = true;
            $pending[] = ['id' => $folderId, 'expanded' => true];

            $childFolderIds = array_reverse($this->findChildFolderIds([$folderId]));
            foreach ($childFolderIds as $childFolderId) {
                if (!isset($visited[$childFolderId])) {
                    $pending[] = ['id' => $childFolderId, 'expanded' => false];
                }
            }
        }

        return array_values($folderIds);
    }

    /**
     * @param array<int, int> $parentIds
     * @return array<int, int>
     */
    protected function findChildFolderIds(array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $placeholders = Builder::getArrayPlaceholders($parentIds, 'parent');
        $statement = $this->getPreparedStatement(
            "SELECT id FROM " . self::TABLE_FOLDER . " WHERE parent IN {$placeholders}",
        );
        $statement->execute(Builder::getArrayParameters($parentIds, 'parent'));

        return array_map(
            static fn(array $row): int => (int) $row['id'],
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }
```

- [ ] **Step 4: Add SQL delete helpers**

Add these methods below the cascade helpers in `Cms`:

```php
    /**
     * @param array<int, int> $folderIds
     * @return array<int, int>
     */
    protected function findBlockedFolderIds(array $folderIds): array
    {
        if ($folderIds === []) {
            return [];
        }

        $blocked = [];
        $placeholders = Builder::getArrayPlaceholders($folderIds, 'folder');
        $params = Builder::getArrayParameters($folderIds, 'folder');

        foreach ([
            'assessment_folder' => 'folder_id',
            'course_implementation' => 'folder_id',
            'course_specification' => 'folder_id',
            'mv_assessment_relation' => 'folder_id',
        ] as $table => $column) {
            $statement = $this->getPreparedStatement(
                "SELECT DISTINCT {$column} AS folder_id FROM {$table} WHERE {$column} IN {$placeholders}",
            );
            $statement->execute($params);
            $blocked = array_merge(
                $blocked,
                array_map(
                    static fn(array $row): int => (int) $row['folder_id'],
                    $statement->fetchAll(\PDO::FETCH_ASSOC),
                ),
            );
        }

        return $this->normalizeIds($blocked);
    }

    /**
     * @param array<int, int> $folderIds
     * @return array<int, int>
     */
    protected function findIdsByFolder(string $table, string $idColumn, string $folderColumn, array $folderIds): array
    {
        if ($folderIds === []) {
            return [];
        }

        $placeholders = Builder::getArrayPlaceholders($folderIds, 'folder');
        $moduleClause = $table === 'component_instance' ? " AND module = 'homepage'" : '';
        $statement = $this->getPreparedStatement(
            "SELECT {$idColumn} AS id FROM {$table} WHERE {$folderColumn} IN {$placeholders}{$moduleClause}",
        );
        $statement->execute(Builder::getArrayParameters($folderIds, 'folder'));

        return array_map(
            static fn(array $row): int => (int) $row['id'],
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }

    /**
     * @param array<int, int> $ids
     */
    protected function setWikiLatestRevisionNull(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = Builder::getArrayPlaceholders($ids, 'id');
        $statement = $this->getPreparedStatement(
            ($this->perform ? 'UPDATE wiki SET latestRevision = NULL' : 'SELECT wiki.* FROM wiki')
            . " WHERE wikiID IN {$placeholders}",
        );
        $statement->execute(Builder::getArrayParameters($ids, 'id'));

        return $statement->rowCount();
    }

    /**
     * @param array<int, int> $ids
     */
    protected function deleteByIds(string $table, string $column, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = Builder::getArrayPlaceholders($ids, 'id');
        $statement = $this->getPreparedStatement(
            $this->deleteStatement($table) . " FROM {$table} WHERE {$column} IN {$placeholders}",
        );
        $statement->execute(Builder::getArrayParameters($ids, 'id'));

        return $statement->rowCount();
    }

    /**
     * @param array<int, int> $folderIds
     */
    protected function deleteByFolderIds(string $table, string $column, array $folderIds): int
    {
        if ($folderIds === []) {
            return 0;
        }

        $placeholders = Builder::getArrayPlaceholders($folderIds, 'folder');
        $statement = $this->getPreparedStatement(
            $this->deleteStatement($table) . " FROM {$table} WHERE {$column} IN {$placeholders}",
        );
        $statement->execute(Builder::getArrayParameters($folderIds, 'folder'));

        return $statement->rowCount();
    }

    /**
     * @param array<int, int> $folderIds
     */
    protected function deleteFolders(array $folderIds): int
    {
        $rowsDeleted = 0;
        foreach ($folderIds as $folderId) {
            $rowsDeleted += $this->deleteByIds(self::TABLE_FOLDER, 'id', [$folderId]);
        }

        return $rowsDeleted;
    }

    /**
     * @return array<string, string>
     */
    private function folderOwnedTables(): array
    {
        return [
            'chat' => 'category_id',
            'chat_poll' => 'category_id',
            'etherpad_pad' => 'homepage_id',
            'event_folder' => 'folder_id',
            'file' => 'category',
            'folder_code' => 'folder_id',
            'folder_year_level' => 'folder_id',
            'google_file' => 'folder_id',
            'homepage_config' => 'cid',
            'homepage_log' => 'folder_id',
            'homepage_visits' => 'category_id',
            'link' => 'category',
            'moment_folder' => 'folder_id',
            'perms' => 'cid',
            'reportcard_comment' => 'category_id',
            'search_index' => 'folder_id',
            'socialstream_post' => 'homepage_id',
            'textbox' => 'folder_id',
            'tile_layout_for_folder' => 'folder_id',
            'userGroups' => 'cid',
        ];
    }
```

- [ ] **Step 5: Run focused tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add library/Schoolbox/Admin/DataRetention/Strategy/Cms.php library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
git commit -m "Add CMS data retention strategy [SA-21886]" -m "Co-Authored-By: Codex <codex@openai.com>"
```

---

## Task 4: Wire CMS Into the Data Retention CLI

**Files:**
- Modify: `library/Schoolbox/Admin/DataRetention/CommandLineTool.php`
- Test: `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php`
- Test: `library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php`

- [ ] **Step 1: Add CMS to the strategy bus**

In `library/Schoolbox/Admin/DataRetention/CommandLineTool.php`, after the `MYFILES` bus block, add:

```php
        if ($validStrats->isStratEnabled(ValidStrats::CMS)) {
            $bus[] = new Strategy\Cms(
                $logger,
                $this->perform(),
                $this->getDatabaseInternal(),
                $lock,
                $this->getConfig(),
                $endTime,
                clone $retentionPeriod,
                $loopCount,
                $chunkSize,
            );
        }
```

- [ ] **Step 2: Run syntax checks**

Run:

```bash
php -l library/Schoolbox/Admin/DataRetention/CommandLineTool.php
php -l library/Schoolbox/Admin/DataRetention/Strategy/Cms.php
php -l library/Schoolbox/Admin/DataRetention/ValidStrats.php
```

Expected output for each file:

```text
No syntax errors detected in <file>
```

- [ ] **Step 3: Run focused PHPUnit tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add library/Schoolbox/Admin/DataRetention/CommandLineTool.php
git commit -m "Run CMS retention from data retention CLI [SA-21886]" -m "Co-Authored-By: Codex <codex@openai.com>"
```

---

## Task 5: Run Required Verification

**Files:**
- Verify: `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php`
- Verify: `library/Schoolbox/Admin/DataRetention/CommandLineTool.php`
- Verify: `library/Schoolbox/Admin/DataRetention/ValidStrats.php`
- Verify: `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php`
- Verify: `library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php`

- [ ] **Step 1: Run PHP syntax checks**

Run:

```bash
php -l library/Schoolbox/Admin/DataRetention/Strategy/Cms.php
php -l library/Schoolbox/Admin/DataRetention/CommandLineTool.php
php -l library/Schoolbox/Admin/DataRetention/ValidStrats.php
php -l library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
php -l library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php
```

Expected output for each file:

```text
No syntax errors detected in <file>
```

- [ ] **Step 2: Run focused PHPUnit tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php
```

Expected: PASS.

- [ ] **Step 3: Run style checks**

Run:

```bash
./vendor/bin/phpcs library/Schoolbox/Admin/DataRetention/Strategy/Cms.php library/Schoolbox/Admin/DataRetention/CommandLineTool.php library/Schoolbox/Admin/DataRetention/ValidStrats.php library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php
```

Expected: command exits with status `0`.

- [ ] **Step 4: Run static analysis**

Run:

```bash
./vendor/bin/phpstan analyze library/Schoolbox/Admin/DataRetention/Strategy/Cms.php library/Schoolbox/Admin/DataRetention/CommandLineTool.php library/Schoolbox/Admin/DataRetention/ValidStrats.php library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php
```

Expected: command exits with status `0`.

- [ ] **Step 5: Run the wider DataRetention PHPUnit set**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test
```

Expected: PASS.

---

## Out of Scope

- Do not delete assessment or course implementation/specification data in this ticket.
- Do not change `updates/26.1.alpha1/data-retention-admin.sql`; `dataRetention_CMS` already exists.
- Do not add database cascades.
- Do not alter soft-delete behavior.

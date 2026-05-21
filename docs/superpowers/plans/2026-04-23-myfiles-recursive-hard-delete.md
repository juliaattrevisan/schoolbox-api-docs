# MyFiles Recursive Hard Delete Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Update `MyFiles::deleteChunk()` so a requested `my_folder` hard delete first hard-deletes every descendant `my_folder` and `my_file`, then deletes the requested folder itself.

**Architecture:** Keep the change inside `library/Schoolbox/Admin/DataRetention/Strategy/MyFiles.php`, but add small helper methods so the recursion and ordering are explicit and testable. `deleteChunk()` will expand folder ids into a full subtree, gather descendant file ids regardless of each child row's own `deleted_at`, delete ePortfolio links for the full file set, hard-delete files, then hard-delete folders leaf-first. This follows the existing soft-delete business rule in `Schoolbox\Resource\My\Folder\Mapper::softDeleteContents()`.

**Tech Stack:** PHP 8.4, PHPUnit 11, MariaDB 11.8 SQL via `IConnector`, PSR-3 logging

---

## File Map

| Action | Path |
|---|---|
| Modify | `library/Schoolbox/Admin/DataRetention/Strategy/MyFiles.php` |
| Create | `library/Schoolbox/Admin/DataRetention/Test/MyFilesStrategyTest.php` |

---

## Task 1: Add focused strategy tests for recursive folder purging

**Files:**
- Create: `library/Schoolbox/Admin/DataRetention/Test/MyFilesStrategyTest.php`
- Modify: `library/Schoolbox/Admin/DataRetention/Strategy/MyFiles.php`

- [ ] **Step 1: Write the failing tests**

Create `library/Schoolbox/Admin/DataRetention/Test/MyFilesStrategyTest.php`:

```php
<?php

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Test;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\DataRetention\RetentionPeriod;
use Schoolbox\Admin\DataRetention\Strategy\MyFiles;
use Schoolbox\IConfigLoader;
use Symfony\Component\Lock\LockInterface;
use Alaress\Database\Connector\IConnector;

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
```

- [ ] **Step 2: Run tests to verify they fail**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/MyFilesStrategyTest.php --no-coverage
```

Expected: FAIL because `MyFiles` does not yet provide `findChildFolderIds()` / `findChildFileIds()` override points, and `deleteFiles()` / `deleteFolders()` / `deleteEportfolioFileJoin()` are `private`.

- [ ] **Step 3: Commit the failing tests**

```bash
git add library/Schoolbox/Admin/DataRetention/Test/MyFilesStrategyTest.php
git commit -m "Add failing tests for recursive MyFiles hard delete [SA-XXXXX]"
```

---

## Task 2: Refactor `MyFiles` so recursive purge logic is explicit and testable

**Files:**
- Modify: `library/Schoolbox/Admin/DataRetention/Strategy/MyFiles.php`
- Test: `library/Schoolbox/Admin/DataRetention/Test/MyFilesStrategyTest.php`

- [ ] **Step 1: Add explicit docblocks for the retention rule and helper boundaries**

In `library/Schoolbox/Admin/DataRetention/Strategy/MyFiles.php`, replace the docblock above `deleteChunk()` with:

```php
    /**
     * Hard-delete the current chunk of MyFiles/MyFolders.
     *
     * Business rule for this strategy:
     * if a retained my_folder is being hard-deleted, every descendant my_folder
     * and my_file beneath that folder is also hard-deleted, regardless of the
     * descendant row's own deleted_at value.
     *
     * Files are always deleted before folders, and folders are deleted in
     * leaf-first order so self-referential foreign keys remain valid.
     *
     * @param array<int, array{id: int|string, table: string}> $chunk
     */
```

Also add this docblock above the new `expandFolderCascade()` helper:

```php
    /**
     * Expand one or more requested root folders into a full delete plan.
     *
     * The returned folder ids are ordered deepest-first so callers can hard-delete
     * folders without violating my_folder.parent foreign keys.
     *
     * @param array<int, int> $rootFolderIds
     * @return array{folderIds: array<int, int>, fileIds: array<int, int>}
     */
```

- [ ] **Step 2: Change the deletion methods from `private` to `protected` and add child lookup helpers**

In `library/Schoolbox/Admin/DataRetention/Strategy/MyFiles.php`, make these signature changes:

```php
    protected function deleteFiles(array $fileIds): int
```

```php
    protected function deleteFolders(array $folderIds): int
```

```php
    protected function deleteEportfolioFileJoin(array $fileIds): int
```

Then add these new helpers above `deleteFiles()`:

```php
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
            "SELECT id FROM " . self::TABLE_NAME_FOLDER . " WHERE parent IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($parentIds, 'parent'));

        return array_map(
            static fn(array $row): int => (int) $row['id'],
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    /**
     * @param array<int, int> $parentIds
     * @return array<int, int>
     */
    protected function findChildFileIds(array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $placeholders = Builder::getArrayPlaceholders($parentIds, 'parent');
        $statement = $this->getPreparedStatement(
            "SELECT id FROM " . self::TABLE_NAME_FILE . " WHERE parent IN {$placeholders}"
        );
        $statement->execute(Builder::getArrayParameters($parentIds, 'parent'));

        return array_map(
            static fn(array $row): int => (int) $row['id'],
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );
    }
```

- [ ] **Step 3: Add recursive cascade expansion and ordering helpers**

Insert these helpers above `deleteFiles()` in `library/Schoolbox/Admin/DataRetention/Strategy/MyFiles.php`:

```php
    /**
     * @param array<int, int|string> $folderIds
     * @return array<int, int>
     */
    private function normalizeIds(array $folderIds): array
    {
        $normalized = array_map(static fn(int|string $id): int => (int) $id, $folderIds);
        $normalized = array_values(array_unique(array_filter($normalized, static fn(int $id): bool => $id > 0)));

        sort($normalized);

        return $normalized;
    }

    /**
     * @param array<int, int|string> $rootFolderIds
     * @return array{folderIds: array<int, int>, fileIds: array<int, int>}
     */
    protected function expandFolderCascade(array $rootFolderIds): array
    {
        $pending = $this->normalizeIds($rootFolderIds);
        $visitedFolders = [];
        $orderedLevels = [];
        $fileIds = [];

        while ($pending !== []) {
            $currentLevel = [];

            foreach ($pending as $folderId) {
                if (isset($visitedFolders[$folderId])) {
                    continue;
                }

                $visitedFolders[$folderId] = true;
                $currentLevel[] = $folderId;
            }

            if ($currentLevel === []) {
                break;
            }

            $orderedLevels[] = $currentLevel;
            $fileIds = array_merge($fileIds, $this->findChildFileIds($currentLevel));
            $pending = $this->findChildFolderIds($currentLevel);
        }

        $folderIds = [];
        for ($i = count($orderedLevels) - 1; $i >= 0; $i--) {
            $folderIds = array_merge($folderIds, $orderedLevels[$i]);
        }

        return [
            'folderIds' => array_values($folderIds),
            'fileIds' => array_values(array_unique(array_map('intval', $fileIds))),
        ];
    }
```

- [ ] **Step 4: Rewrite `deleteChunk()` to expand folder ids before deleting**

Replace the body of `deleteChunk()` in `library/Schoolbox/Admin/DataRetention/Strategy/MyFiles.php` with:

```php
    protected function deleteChunk(array $chunk): void
    {
        $directFileIds = $this->normalizeIds(
            array_column(
                array_filter($chunk, fn(array $item): bool => $item['table'] === self::TABLE_NAME_FILE),
                'id'
            )
        );
        $requestedFolderIds = $this->normalizeIds(
            array_column(
                array_filter($chunk, fn(array $item): bool => $item['table'] === self::TABLE_NAME_FOLDER),
                'id'
            )
        );

        $cascade = $this->expandFolderCascade($requestedFolderIds);
        $allFileIds = array_values(array_unique(array_merge($directFileIds, $cascade['fileIds'])));
        sort($allFileIds);

        $this->log->debug(sprintf("Deleting %s ids: %s", self::TABLE_NAME_FILE, implode(', ', $allFileIds)));

        if ($cascade['folderIds'] !== []) {
            $this->log->debug(sprintf("Deleting %s ids: %s", self::TABLE_NAME_FOLDER, implode(', ', $cascade['folderIds'])));
        }

        $eportfolioDeleted = $this->deleteEportfolioFileJoin($allFileIds);
        $filesDeleted = $this->deleteFiles($allFileIds);
        $foldersDeleted = $this->deleteFolders($cascade['folderIds']);

        if ($this->perform) {
            $this->log->notice(
                sprintf(
                    "Deleted '%s' MyFolders, '%s' MyFiles, '%s' ePortfolioFile links",
                    $foldersDeleted,
                    $filesDeleted,
                    $eportfolioDeleted,
                )
            );
        } else {
            $this->log->notice(
                sprintf(
                    "Would have deleted '%s' MyFolders, '%s' MyFiles, '%s' ePortfolioFile links",
                    $foldersDeleted,
                    $filesDeleted,
                    $eportfolioDeleted,
                )
            );
        }
    }
```

- [ ] **Step 5: Run the focused tests and make them pass**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/MyFilesStrategyTest.php --no-coverage
```

Expected: PASS.

- [ ] **Step 6: Commit the recursive delete implementation**

```bash
git add library/Schoolbox/Admin/DataRetention/Strategy/MyFiles.php library/Schoolbox/Admin/DataRetention/Test/MyFilesStrategyTest.php
git commit -m "Recursively purge MyFiles folder trees [SA-XXXXX]"
```

---

## Task 3: Verify syntax and regression risk for the updated strategy

**Files:**
- Modify: `library/Schoolbox/Admin/DataRetention/Strategy/MyFiles.php`
- Test: `library/Schoolbox/Admin/DataRetention/Test/MyFilesStrategyTest.php`

- [ ] **Step 1: Run syntax checks**

Run:

```bash
php -l library/Schoolbox/Admin/DataRetention/Strategy/MyFiles.php
php -l library/Schoolbox/Admin/DataRetention/Test/MyFilesStrategyTest.php
```

Expected:

```text
No syntax errors detected in library/Schoolbox/Admin/DataRetention/Strategy/MyFiles.php
No syntax errors detected in library/Schoolbox/Admin/DataRetention/Test/MyFilesStrategyTest.php
```

- [ ] **Step 2: Run the nearby retention unit tests**

Run:

```bash
./vendor/bin/phpunit \
  library/Schoolbox/Admin/DataRetention/Test/MyFilesStrategyTest.php \
  library/Schoolbox/Admin/DataRetention/Test/RetentionPeriodTest.php \
  library/Schoolbox/Admin/DataRetention/Test/RetentionPolicyTest.php \
  library/Schoolbox/Admin/DataRetention/Test/ValidRetentionPeriodTest.php \
  library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php \
  --no-coverage
```

Expected: PASS.

- [ ] **Step 3: Verify the changed behavior against the discovered business rule**

Confirm the implementation still matches the existing application delete rule in `library/Schoolbox/Resource/My/Folder/Mapper.php`:

```php
    public function softDeleteContents(Model $folder): bool
    {
        $this->deleteByColumn('parent', $folder->getId());

        $myFileMapper = \Schoolbox\Resource\My\File\Mapper::getInstance();
        $myFileMapper->deleteByColumn('parent', $folder->getId());

        return true;
    }
```

Interpretation for this task:
- if a retained folder is chosen for hard delete, descendant folders are included
- descendant files are included
- descendant rows are deleted even if their own `deleted_at` is newer or `null`

- [ ] **Step 4: Commit the verification pass**

```bash
git add library/Schoolbox/Admin/DataRetention/Strategy/MyFiles.php library/Schoolbox/Admin/DataRetention/Test/MyFilesStrategyTest.php
git commit -m "Verify MyFiles recursive hard delete coverage [SA-XXXXX]"
```

---

## Self-Review

- Spec coverage: This plan covers the requested `deleteChunk()` change, the linked PR requirement from `#discussion_r3121477831`, and the known cascade source in `My\Folder\Mapper::softDeleteContents()`.
- Placeholder scan: No `TODO` / `TBD` markers remain; all code-change steps include concrete file paths and code blocks.
- Type consistency: Helper methods use `array<int, int>` return types, `deleteChunk()` still accepts the strategy chunk shape from `Base`, and overridden methods are made `protected` to support test harnessing without changing external behavior.

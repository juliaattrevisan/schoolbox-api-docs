# CMS Leaf-First Retention Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove `Cms::expandFolderCascade()` and make CMS retention delete only the leaf folder ids supplied by `getIdsToDelete()`.

**Architecture:** Keep the change inside `Cms.php` and `CmsStrategyTest.php`. `getIdsToDelete()` remains the tree-safety boundary by selecting only old soft-deleted folders that have no children. `deleteChunk()` becomes a direct chunk-local cleanup orchestrator: normalize supplied ids, filter blocked assessment/course ids, and run the existing related-table cleanup for the remaining leaf ids without recursive folder traversal.

**Tech Stack:** PHP 8.4, PHPUnit 11, MariaDB 11.8 SQL via `IConnector`, PSR-3 logging

---

## File Map

| Action | Path |
|---|---|
| Modify | `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php` |
| Modify | `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php` |

---

## Task 1: Replace cascade tests with leaf-first chunk tests

**Files:**
- Modify: `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php`
- Test: `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php`

- [ ] **Step 1: Remove cascade-only test fixture state**

In the anonymous `$state` class inside `CmsStrategyTest::strategy()`, remove:

```php
            /**
             * Capture cascade child lookups so branch-cache tests can prove an
             * overlapping descendant root did not trigger a second subtree walk.
             *
             * @var list<list<int>>
             */
            public array $softDeletedChildLookups = [];

            /**
             * Capture active-child safety checks for the same reason as soft-deleted
             * lookups: cache regressions show up as repeated checks on descendants.
             *
             * @var list<list<int>>
             */
            public array $activeChildLookups = [];
```

In the anonymous class constructor and properties, remove the `?array $softDeletedFolderIds` property and constructor parameter. Keep `$folderChildren` for this task only because some old tests still compile until they are removed in later steps.

- [ ] **Step 2: Remove cascade helper overrides from the test double**

Delete these methods from the anonymous class in `CmsStrategyTest::strategy()`:

```php
protected function findChildFolderIds(array $parentIds): array
protected function findSoftDeletedChildFolderIds(array $parentIds): array
protected function findActiveChildFolderIds(array $parentIds): array
```

These methods exist only to model recursive branch expansion. The new leaf-first contract does not call them.

- [ ] **Step 3: Update `strategy()` calls that pass `softDeletedFolderIds`**

Remove the named argument `softDeletedFolderIds: ...` and the final positional argument used for soft-deleted folder ids from any remaining calls to `strategy()`.

For example, replace:

```php
['strategy' => $strategy, 'state' => $state] = $this->strategy(
    [
        10 => [11, 12],
        11 => [],
        12 => [],
    ],
    softDeletedFolderIds: [10, 11],
);
```

with a leaf-focused fixture:

```php
['strategy' => $strategy, 'state' => $state] = $this->strategy();
```

- [ ] **Step 4: Replace `testDeletesFolderTree()` with direct leaf deletion coverage**

Replace the existing `testDeletesFolderTree()` method with:

```php
    /**
     * CMS retention deletes exactly the leaf folder ids supplied by the chunk.
     *
     * `getIdsToDelete()` owns parent/child safety by selecting folders with no
     * children. `deleteChunk()` should not expand the supplied ids into a wider
     * tree; it should run the related CMS cleanup for only the current leaf set.
     *
     * @return void
     */
    public function testDeleteChunkDeletesOnlySuppliedLeafFolders(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy(
            [],
            [],
            [
                'blog' => [
                    10 => [101],
                    20 => [201],
                ],
            ],
        );

        $this->delete($strategy, [
            ['id' => 20, 'table' => 'folder'],
            ['id' => 10, 'table' => 'folder'],
        ]);

        $this->assertSame([10, 20], $state->deletedFolders);
        $this->assertSame([10, 20], $state->deletedHomepageUserAccessFolderIds);
        $this->assertSame([
            'cleanupHomepagePads:10:do',
            'cleanupHomepagePads:20:do',
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
            'deleteSurveys',
            'deleteHomepageUserAccess',
            'deleteFolders',
        ], $state->calls);
        $this->assertSame([10, 20], $state->deletedSurveyFolderIds);
    }
```

- [ ] **Step 5: Add a test that proves descendants are not expanded**

Add this method near `testDeleteChunkDeletesOnlySuppliedLeafFolders()`:

```php
    /**
     * Supplied folder ids are already retention candidates, not cascade roots.
     *
     * A folder graph in the test fixture must not cause extra folder ids to be
     * deleted. Parent folders become eligible in later retention passes after
     * their children have been removed by the leaf-first candidate query.
     *
     * @return void
     */
    public function testDeleteChunkDoesNotExpandDescendantFolders(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy(
            [
                10 => [11],
                11 => [12],
                12 => [],
            ],
        );

        $this->delete($strategy, [
            ['id' => 10, 'table' => 'folder'],
        ]);

        $this->assertSame([10], $state->deletedFolders);
        $this->assertSame([10], $state->deletedHomepageUserAccessFolderIds);
        $this->assertSame([10], $state->deletedSurveyFolderIds);
    }
```

- [ ] **Step 6: Remove old branch-classification tests**

Delete these test methods completely:

```php
public function testDeleteChunkSkipsActiveChildren(): void
public function testDeleteChunkCachesBranchesSkippedForActiveChildren(): void
public function testDeleteChunkSkipsActiveBranchAndContinuesWithUnrelatedRoots(): void
public function testDeleteChunkLogsActiveDescendantParent(): void
public function testDeleteChunkLogsCachedActiveDescendantSkip(): void
public function testExpandFolderCascadeSkipsActiveParents(): void
public function testFindActiveChildFolderIds(): void
public function testFindSoftDeletedChildFolderIds(): void
public function testDeleteChunkSkipsAncestorsWhenRecursiveDescendantIsBlocked(): void
public function testDeleteChunkReusesFolderBranchCache(): void
```

These methods assert the removed full-tree expansion behavior.

- [ ] **Step 7: Update blocked-folder tests to be chunk-local**

Replace `testDeleteChunkExcludesBlockedBranchAndContinues()` with:

```php
    /**
     * Blocked folders are removed from the current leaf chunk only.
     *
     * Assessment/course relations are outside CMS retention ownership. Under the
     * leaf-first contract, a blocked folder does not imply branch-wide skipping;
     * the strategy simply excludes that id and continues with the other supplied
     * leaf folders.
     *
     * @return void
     */
    public function testDeleteChunkExcludesBlockedLeafAndContinues(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy(
            [],
            [10],
        );

        $this->delete($strategy, [
            ['id' => 10, 'table' => 'folder'],
            ['id' => 20, 'table' => 'folder'],
        ]);

        $this->assertSame([20], $state->deletedFolders);
        $this->assertSame([20], $state->deletedHomepageUserAccessFolderIds);
        $this->assertSame([
            'cleanupHomepagePads:20:do',
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
            'deleteSurveys',
            'deleteHomepageUserAccess',
            'deleteFolders',
        ], $state->calls);
    }
```

Keep `testDeleteChunkSkipsWhenAllFoldersAreBlocked()` if it already asserts that a fully blocked chunk performs no deletes. Update its docblock to say "leaf folders" instead of "branch" if needed.

- [ ] **Step 8: Run the focused test and confirm failure before production changes**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
```

Expected: FAIL. The new tests should fail because `deleteChunk()` still expands supplied folders through `expandFolderCascade()`, and the deleted method references or fixture removals may still expose production calls to now-removed test overrides.

- [ ] **Step 9: Commit the test-contract change**

Only commit if the team accepts red-state test commits. Otherwise skip this commit and commit Task 1 and Task 2 together after the production change passes.

```bash
git add library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
git commit -m "Update CMS retention tests for leaf-first chunks [SA-21886]"
```

Expected: commit created only if red-state commits are allowed.

---

## Task 2: Simplify `Cms::deleteChunk()` to use only supplied leaf ids

**Files:**
- Modify: `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php`
- Test: `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php`

- [ ] **Step 1: Remove branch-classification properties**

Delete these properties from `Cms`:

```php
    /**
     * Remember every folder seen during branch expansion so descendant roots in
     * the chunks do not expand their subtree again. The branch expansion
     * performs recursive child lookups and active-folder safety checks, so
     * repeating it for already-seen descendants is unnecessary database work.
     *
     * @var array<int, bool>
     */
    private array $classifiedFolderIds = [];

    /**
     * Keep the skip warnings for classified folders so later chunks can still
     * explain why a descendant root is ignored without re-expanding the same tree.
     *
     * @var array<int, array<int, string>>
     */
    private array $classifiedFolderSkipWarnings = [];
```

- [ ] **Step 2: Update the `getIdsToDelete()` docblock**

Replace the docblock above `getIdsToDelete()` with:

```php
    /**
     * Load soft-deleted CMS leaf folder ids that are old enough to hard-delete.
     *
     * Parent folders are deliberately excluded while any child folder still
     * exists. This makes retention leaf-first across runs: children are purged in
     * earlier chunks, and parents become eligible only after the tree below them
     * has gone.
     *
     * @return array<int, array{id: int|string}>
     */
```

Keep the SQL query unchanged, including the `NOT EXISTS` child guard.

- [ ] **Step 3: Replace the delete-set calculation in `deleteChunk()`**

In `deleteChunk()`, replace everything from the comment above `$folderIdsToDelete = [];` through `$folderIdsToDelete = array_values($folderIdsToDelete);` with:

```php
        /**
         * The candidate query already enforces leaf-first deletion by returning
         * folders with no children. Treat this chunk as the complete delete set
         * for this pass instead of expanding ids into a folder tree.
         */
        $folderIdsToDelete = $requestedFolderIds;
        $blockedFolderIds = $this->findBlockedFolderIds($folderIdsToDelete);

        if ($blockedFolderIds !== []) {
            $this->log->warning(sprintf(
                "Excluding blocked Resources (CMS) folders from chunk due to excluded assessment/course relations: %s",
                implode(', ', $blockedFolderIds),
            ));

            $folderIdsToDelete = array_values(array_diff($folderIdsToDelete, $blockedFolderIds));
        }
```

Keep the existing empty-delete-set guard immediately after this block:

```php
        if ($folderIdsToDelete === []) {
            $this->log->warning("Skipping Resources (CMS) chunk because no folders are safe to delete");

            return false;
        }
```

- [ ] **Step 4: Remove tree functions**

Delete this whole section from `Cms.php`:

```php
    // ---------------
    // start tree functions
```

through the end of:

```php
    protected function findActiveChildFolderIds(array $parentIds): array
```

This removes:

```php
protected function expandFolderCascade(int $rootFolderId): array
private function recordClassifiedFolderSkipWarnings(array $warnings): void
protected function findChildFolderIds(array $parentIds): array
protected function findSoftDeletedChildFolderIds(array $parentIds): array
protected function findActiveChildFolderIds(array $parentIds): array
```

Do not remove `findBlockedFolderIds()`.

- [ ] **Step 5: Check for stale references**

Run:

```bash
rg -n "expandFolderCascade|classifiedFolder|findActiveChildFolderIds|findSoftDeletedChildFolderIds|findChildFolderIds|branch expansion|branch classification|active descendant" library/Schoolbox/Admin/DataRetention/Strategy/Cms.php library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
```

Expected: no references to removed methods or removed branch cache state. References to "leaf-first" and `getIdsToDelete()` are fine.

- [ ] **Step 6: Run focused tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit the implementation**

If Task 1 was not committed separately, include the test file here too:

```bash
git add library/Schoolbox/Admin/DataRetention/Strategy/Cms.php library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
git commit -m "Remove CMS folder cascade expansion [SA-21886]"
```

Expected: one buildable commit with passing targeted tests.

---

## Task 3: Clean up wording and run verification

**Files:**
- Modify: `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php`
- Modify: `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php`

- [ ] **Step 1: Search comments for outdated tree language**

Run:

```bash
rg -n "cascade|recursive|descendant|ancestor|branch|tree|active child|active descendant" library/Schoolbox/Admin/DataRetention/Strategy/Cms.php library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
```

Expected: any remaining matches should either refer to historical test names that still make sense outside the removed behavior, or should be rewritten to leaf-first language.

- [ ] **Step 2: Replace stale comments with leaf-first wording**

Use this wording where a replacement is needed:

```php
     * `getIdsToDelete()` owns parent/child safety by returning only folders that
     * currently have no children. This method therefore treats the chunk as a
     * set of leaf folder ids and does not expand it into a wider folder tree.
```

For blocked-folder tests, prefer:

```php
     * Blocked assessment/course folders are excluded from the current leaf set.
     * They do not cause branch-wide skips because `deleteChunk()` no longer
     * expands or classifies folder trees.
```

- [ ] **Step 3: Run syntax checks**

Run:

```bash
php -l library/Schoolbox/Admin/DataRetention/Strategy/Cms.php
php -l library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
```

Expected for each file:

```text
No syntax errors detected
```

- [ ] **Step 4: Run style checks**

Run:

```bash
./vendor/bin/phpcs library/Schoolbox/Admin/DataRetention/Strategy/Cms.php library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
```

Expected: exits with no reported violations.

- [ ] **Step 5: Run static analysis**

Run:

```bash
./vendor/bin/phpstan analyze library/Schoolbox/Admin/DataRetention/Strategy/Cms.php library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
```

Expected: exits with no new PHPStan errors.

- [ ] **Step 6: Run targeted PHPUnit again**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
```

Expected: PASS.

- [ ] **Step 7: Run Docker verification when available**

Run the same checks inside the Schoolbox Docker environment:

```bash
./docker/exec ./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
./docker/exec ./vendor/bin/phpcs library/Schoolbox/Admin/DataRetention/Strategy/Cms.php library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
./docker/exec ./vendor/bin/phpstan analyze library/Schoolbox/Admin/DataRetention/Strategy/Cms.php library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
```

Expected: all pass. If Docker is not running or cannot be used, report that host-shell checks passed but Docker verification was not completed.

- [ ] **Step 8: Commit verification cleanup if any files changed**

If comment/style cleanup changed files after Task 2, commit those changes:

```bash
git add library/Schoolbox/Admin/DataRetention/Strategy/Cms.php library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
git commit -m "Tidy CMS leaf-first retention docs [SA-21886]"
```

Expected: commit created only if Task 3 changed tracked files.

---

## Self-Review

- Spec coverage: the plan removes `expandFolderCascade()`, removes branch cache state, keeps `findBlockedFolderIds()`, and preserves the `getIdsToDelete()` leaf guard.
- Placeholder scan: no `TBD`, `TODO`, or unspecified implementation steps remain.
- Type consistency: all referenced methods and files match the current CMS strategy/test naming.
- Risk control: targeted tests verify leaf-only deletion, no descendant expansion, blocked leaf filtering, and the SQL child guard.

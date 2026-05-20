# Design: CMS Leaf-First Retention

**Date:** 2026-05-21  
**Status:** Draft

---

## Background

`library/Schoolbox/Admin/DataRetention/Strategy/Cms.php` currently treats each folder id in a retention chunk as the start of a folder branch. `Cms::deleteChunk()` calls `expandFolderCascade()` to walk soft-deleted descendants, detect active descendants, cache classified folders, replay cached skip warnings, and return a leaf-first delete plan.

That branch expansion is no longer the desired contract. CMS retention should now rely on the candidate query to provide only leaf folders. Once `getIdsToDelete()` returns leaf folders, `deleteChunk()` should delete only those folder ids from the current chunk. Parent folders become eligible in a later retention pass after their children have been removed.

---

## Problem

The current implementation is more complex than the new leaf-first contract requires:

- `deleteChunk()` expands a supplied folder id into a full branch instead of trusting the leaf candidates it receives.
- `expandFolderCascade()` performs recursive soft-deleted child lookups that are no longer needed.
- active descendant detection and branch classification duplicate the protection already enforced by `getIdsToDelete()` selecting folders with no children.
- branch caches and cached warning replay exist only because descendants can be discovered and classified as part of an expanded branch.
- several tests assert the old full-tree expansion model, so they now describe behavior the strategy should stop providing.

Keeping the branch expansion increases maintenance cost and makes the runtime behavior less obvious: a chunk containing one folder id can delete other folder ids that were not selected by the leaf-first candidate query.

---

## Goals

- Remove `Cms::expandFolderCascade()`.
- Make `Cms::deleteChunk()` operate only on the folder ids present in the current chunk.
- Keep `getIdsToDelete()` as the tree-safety boundary by selecting old soft-deleted folders with no children.
- Continue excluding folder ids that belong to out-of-scope assessment/course relations.
- Keep all existing folder-owned cleanup order for the final safe leaf folder set.
- Replace old cascade-focused tests with tests for the leaf-first contract.

## Non-goals

- Do not add a replacement tree traversal helper.
- Do not recursively inspect active descendants in `deleteChunk()`.
- Do not cache classified folders across chunks.
- Do not change the list or order of CMS related table cleanup outside the delete set calculation.
- Do not change schema constraints or add database migrations.
- Do not change MyFiles, Eportfolio, Moments, StudentSubmissions, or other retention strategies.

---

## Architecture

The change stays inside the CMS retention strategy and its PHPUnit test file.

### Files

| File | Change |
|---|---|
| `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php` | Remove branch expansion state and helpers. Change `deleteChunk()` to filter the current chunk's folder ids directly. |
| `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php` | Remove tests that assert recursive branch expansion and cached branch classification. Add or update tests for leaf-only chunk behavior. |

No schema update is required.

---

## Intended Behavior

### Candidate selection

`getIdsToDelete()` remains responsible for selecting folders that are safe to consider in this pass:

- `folder.deleted_at IS NOT NULL`
- `folder.deleted_at < :old`
- no rows exist in `folder` where `child.parent = folder.id`

This means a parent folder with any child is not selected. It can be selected in a later pass after child folders have been hard-deleted.

### Chunk deletion

`deleteChunk()` receives a chunk of candidate folder ids and treats those ids as the complete delete set for this chunk.

For a chunk containing:

```text
folder 10
folder 20
folder 30
```

the strategy should:

1. normalize and de-duplicate the supplied ids
2. find blocked assessment/course folder ids among exactly `[10, 20, 30]`
3. remove blocked ids from the current delete set
4. run the existing related-table cleanup for the remaining ids
5. hard-delete only the remaining folder rows

The strategy must not look up child folders or add descendant folder ids to the delete set.

### Blocked folder handling

Blocked assessment/course folders are filtered from the current chunk only.

If folder `20` is blocked and the chunk contains `[10, 20, 30]`, the cleanup should continue for `[10, 30]`. The presence of a blocked folder does not cause ancestor or descendant branch skipping because `deleteChunk()` no longer reasons about branches.

If every folder in the chunk is blocked, `deleteChunk()` should log that no folders are safe to delete and return `false`, matching the existing empty-delete-set behavior.

### Delete ordering

Within one chunk, the folder ids should remain normalized and deterministic. The current `normalizeIds()` behavior sorts ids ascending. That is acceptable because every id in the chunk is expected to be a leaf folder, so parent-before-child ordering is not needed inside `deleteChunk()`.

Parent/child ordering is handled across retention passes by `getIdsToDelete()` selecting only folders with no children.

---

## Components

### `Cms::deleteChunk()`

`deleteChunk()` should become a direct leaf-set orchestrator:

- normalize ids from `$chunk`
- log the supplied ids
- assign `$folderIdsToDelete = $requestedFolderIds`
- call `findBlockedFolderIds($folderIdsToDelete)`
- log excluded blocked ids when present
- remove blocked ids using `array_diff`
- return `false` if the result is empty
- run the existing cleanup sequence unchanged

The method should no longer call any folder traversal helper.

### Removed state

The following properties should be deleted:

```php
private array $classifiedFolderIds = [];
private array $classifiedFolderSkipWarnings = [];
```

They only support branch expansion and warning replay for previously classified descendants.

### Removed helpers

Delete these methods:

```php
protected function expandFolderCascade(int $rootFolderId): array
private function recordClassifiedFolderSkipWarnings(array $warnings): void
protected function findChildFolderIds(array $parentIds): array
protected function findSoftDeletedChildFolderIds(array $parentIds): array
protected function findActiveChildFolderIds(array $parentIds): array
```

Keep `findBlockedFolderIds()` because assessment/course relation filtering still applies to the current leaf set.

### Test double cleanup

The anonymous CMS test strategy no longer needs tree traversal hooks. Remove the fixture fields and overrides used only by cascade tests:

- `$folderChildren`
- `$softDeletedFolderIds`
- `$state->softDeletedChildLookups`
- `$state->activeChildLookups`
- `findChildFolderIds()`
- `findSoftDeletedChildFolderIds()`
- `findActiveChildFolderIds()`

Keep the blocked-folder override, lookup override, delete capture methods, and homepage cleanup fixtures.

---

## Testing

Update `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php` so the tests describe the leaf-first contract:

- a chunk containing leaf folder ids deletes exactly those ids
- the strategy does not expand descendants from a supplied parent id
- blocked ids are removed from the current chunk while unblocked ids continue
- a fully blocked chunk returns no deletes and logs the existing empty-delete-set warning
- `getIdsToDelete()` keeps the `NOT EXISTS` child guard

Remove or rewrite tests whose only purpose is recursive branch behavior:

- `testDeletesFolderTree`
- `testDeleteChunkSkipsActiveChildren`
- `testDeleteChunkCachesBranchesSkippedForActiveChildren`
- `testDeleteChunkSkipsActiveBranchAndContinuesWithUnrelatedRoots`
- `testDeleteChunkLogsActiveDescendantParent`
- `testDeleteChunkLogsCachedActiveDescendantSkip`
- `testExpandFolderCascadeSkipsActiveParents`
- `testFindActiveChildFolderIds`
- `testFindSoftDeletedChildFolderIds`
- `testDeleteChunkSkipsAncestorsWhenRecursiveDescendantIsBlocked`
- `testDeleteChunkReusesFolderBranchCache`

Existing tests for related CMS table deletion, survey cleanup, homepage pad cleanup, dry-run behavior, logging counts, and blocker filtering should remain where they still match the new delete-set behavior.

---

## Error Handling

The existing `Base::process()` transaction and error handling remain unchanged.

This design does not introduce partial commits inside `deleteChunk()`. If cleanup fails after blocked ids have been filtered, the existing transaction rollback behavior applies.

---

## Risks

The main risk is deleting a parent folder while it still has children. The mitigation is that `getIdsToDelete()` must keep its `NOT EXISTS` child guard, and the test for that SQL shape must remain.

A second risk is leaving old cascade tests in place after changing the implementation. Those tests would either fail or force the implementation back toward full-tree expansion. The test suite should describe the new leaf-first model clearly.

---

## Out of Scope Follow-up

If operators need a separate tool to compact an entire CMS folder tree in one run, that should be designed separately. This retention strategy should stay leaf-first and chunk-local.

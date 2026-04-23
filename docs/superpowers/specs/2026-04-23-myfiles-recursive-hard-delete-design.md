# Design: MyFiles Recursive Hard Delete

**Date:** 2026-04-23  
**Status:** Approved

---

## Background

`library/Schoolbox/Admin/DataRetention/Strategy/MyFiles.php` currently hard-deletes `my_file` and `my_folder` rows by taking mixed ids from `getIdsToDelete()` and deleting them in a chunk. That approach does not model the business rule for My Files folder trees and risks foreign key failures when a parent folder and its descendants are deleted together.

The relevant review thread for PR `#34631` clarified the intended behavior:

- if a `my_folder` row is being hard-deleted by the retention strategy, all descendants of that folder should also be hard-deleted
- this applies even when descendant rows have a newer `deleted_at`, or no `deleted_at` at all
- if the implementation cannot safely rely on the application’s folder cascade behavior, the strategy should fall back to only deleting rows that are provably safe and document the limitation

We have already identified the existing application behavior in `library/Schoolbox/Resource/My/Folder/Mapper.php::softDeleteContents()`, which soft-deletes direct child folders and direct child files when a parent folder is deleted. Because child folder deletes trigger the same mapper callback again, this becomes recursive tree deletion in application code.

---

## Problem

`MyFiles::deleteChunk()` currently:

- accepts mixed `my_file` and `my_folder` ids in the same chunk
- deletes direct `my_file` ids
- then deletes direct `my_folder` ids with a single `DELETE ... WHERE id IN (...)`

This has three issues:

1. `my_folder.parent` is a self-referential foreign key without `ON DELETE CASCADE`, so parent and child folders cannot be hard-deleted in arbitrary order.
2. `my_file.parent` also references `my_folder`, so descendant files must be removed before their ancestor folders.
3. The strategy’s business rule is tree-based, not row-based. Once a retained parent folder is selected for purge, its whole subtree becomes purgeable, regardless of each descendant row’s own `deleted_at`.

---

## Goals

- Make `MyFiles` hard deletion respect the existing My Files folder-tree business rule.
- Ensure a requested `my_folder` is deleted only after all descendant files and descendant folders have been deleted.
- Keep the logic local to the `MyFiles` retention strategy rather than relying on database cascades.
- Make the behavior explicit in code via docblocks and small helper methods.
- Add focused PHPUnit coverage for recursive tree expansion and delete ordering.

## Non-goals

- Changing the soft-delete behavior of My Files folders.
- Adding database-level `ON DELETE CASCADE` constraints for `my_folder` or `my_file`.
- Solving historical orphan data beyond what this strategy can now safely purge.
- Generalizing this tree-deletion behavior to other retention strategies.

---

## Architecture

The change stays inside `library/Schoolbox/Admin/DataRetention/Strategy/MyFiles.php`.

### Files

| File | Change |
|---|---|
| `library/Schoolbox/Admin/DataRetention/Strategy/MyFiles.php` | Refactor `deleteChunk()` to expand folder roots into a full subtree, gather descendant files, delete files first, then delete folders leaf-first |
| `library/Schoolbox/Admin/DataRetention/Test/MyFilesStrategyTest.php` | Add focused tests for recursive expansion, de-duplication, and child-before-parent deletion ordering |

No database migration is required for this change.

---

## Intended Behavior

### Folder-driven purge rule

If a chunk contains a `my_folder` id selected for hard deletion, that folder is treated as the root of a subtree purge:

- every descendant `my_folder` beneath that root is included
- every descendant `my_file` beneath that root is included
- descendants are included even if their own `deleted_at` is newer than the retention cutoff or is `NULL`

This mirrors the existing My Files delete behavior used by the application layer and makes the retention strategy consistent with user-visible folder deletion.

### File-driven purge rule

If a chunk contains direct `my_file` ids that are not part of a folder subtree being purged, those files are still deleted normally.

If a direct `my_file` id is also discovered as a descendant of a retained folder root, it is deleted once only.

### Delete ordering

Deletion order must be:

1. remove `eportfolio_component_filelist_item` rows for all files being purged
2. delete all file rows in the purge set
3. delete folder rows deepest-first, children before parents

This order is required to satisfy foreign key constraints.

---

## Component Detail

### `MyFiles::deleteChunk()`

`deleteChunk()` will stop treating the chunk as a flat list of unrelated rows.

Instead it will:

- split the chunk into direct file ids and requested folder ids
- normalize ids to integers and de-duplicate them
- expand requested folder ids into a full cascade set of descendant folders and descendant files
- merge direct file ids with cascade-derived file ids
- delete files and then folders in safe order

The method should clearly document that this strategy deliberately treats retained parent folders as the source of truth for subtree purging.

### New helper methods in `MyFiles`

The strategy should gain small helpers for:

- finding child folder ids for a set of parent folder ids
- finding child file ids for a set of parent folder ids
- expanding a folder root set into a full cascade result
- normalizing and de-duplicating id lists

These helpers should stay internal to the strategy except where visibility needs to be widened to support focused unit tests.

### Testability

The current delete helpers in `MyFiles` are `private`, which makes them hard to observe in a focused unit test.

For testability only, the strategy should make the low-level delete methods `protected`:

- `deleteFiles()`
- `deleteFolders()`
- `deleteEportfolioFileJoin()`

This allows a test subclass to record what would have been deleted without changing external behavior.

---

## Data Flow

For a chunk containing:

```text
folder 10
file   203
```

where folder `10` contains folders `11`, `12`, and folder `11` contains folder `13`, the strategy should behave like this:

1. requested folder ids = `[10]`
2. direct file ids = `[203]`
3. recursive folder walk discovers folder ids `[10, 11, 12, 13]`
4. recursive file lookup discovers all files directly under `10`, `11`, `12`, and `13`
5. direct file ids and discovered subtree file ids are merged and de-duplicated
6. files are deleted
7. folders are deleted in reverse depth order, for example `[13, 11, 12, 10]`

The exact sibling order is not important. The parent-after-child rule is important.

---

## Error Handling

- If a chunk cannot be processed due to a database error, the existing `Base::process()` behavior remains unchanged: the transaction is rolled back, the error is logged, and the tool continues according to its current strategy control flow.
- This design does not introduce partial commits inside `deleteChunk()`.
- The strategy should not silently ignore folders that were requested for purge but not deleted. If future edge cases appear, that should be surfaced in logging rather than hidden.

---

## Testing

Add `library/Schoolbox/Admin/DataRetention/Test/MyFilesStrategyTest.php` with a lightweight test subclass of `MyFiles`.

The tests should verify:

- a requested parent folder causes descendant files to be included
- folders are deleted leaf-first
- direct file ids are merged with cascade-derived file ids without duplicates
- nested requested folders are not processed twice

These tests do not need a real database. They can override child lookup and delete methods in a test subclass and assert on the computed deletion order.

---

## Out of Scope Follow-up

The PR discussion also notes possible historical data cleanup and migration concerns for rows whose parent was previously deleted. That remains follow-up work and should be tracked separately if needed.

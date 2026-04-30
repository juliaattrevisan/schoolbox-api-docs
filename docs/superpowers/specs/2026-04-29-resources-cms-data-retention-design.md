# Design: Resources CMS Data Retention Hard Delete

**Date:** 2026-04-29  
**Status:** Drafted from Jira SA-21886

---

## Background

Jira `SA-21886` asks for a data retention deletion function for Resources (CMS) data under the `SA-21767` epic, "Hard deletion of soft-deleted data".

The existing data retention system lives in `library/Schoolbox/Admin/DataRetention` and already provides strategy-based hard deletion for other data classes. The current shape is:

- strategy classes extend `Schoolbox\Admin\DataRetention\Strategy\Base`
- retention period configuration comes from `ConfigMapper::NAME_DATA_RETENTION_*`
- CLI strategy selection is handled by `ValidStrats`
- `CommandLineTool` builds a strategy bus and runs each selected strategy
- `Base::process()` handles cutoff calculation, chunking, transactions, dry-run rollback, lock refresh, and logging

Resources (CMS) already has an admin config key: `ConfigMapper::NAME_DATA_RETENTION_CMS`.

---

## Problem

Soft-deleted `folder` rows can only be hard-deleted after folder-owned CMS data has been removed in a safe order. Many resource tables hold foreign keys to `folder`, and several of those tables have their own child tables.

The deletion must support dry-run mode and perform mode:

- in dry-run mode, identify and count rows that would be deleted without committing changes
- in perform mode, delete identified rows inside the existing chunk transaction

The ticket also lists assessment and course relations as excluded for now. Those tables still hold foreign keys to `folder`, so the CMS strategy must not silently fail when a candidate folder is still referenced by an excluded relation.

---

## Goals

- Add a CMS data retention strategy using `ConfigMapper::NAME_DATA_RETENTION_CMS`.
- Select folders where `folder.deleted_at` is older than the configured CMS retention cutoff.
- Select only leaf-safe retained folders: a folder must not have any child folder where `deleted_at IS NULL`.
- Expand selected folders only through soft-deleted descendant folders, never through active folders.
- Delete content related only to the purged folders before deleting the folders.
- Delete nested dependencies in foreign-key-safe order.
- Delete folders leaf-first so child folders are removed before parent folders.
- Register the strategy so `tools/dataRetention/process.php --strats=cms` can run only CMS retention.
- Keep the implementation consistent with the existing `MyFiles` and `Moments` strategy patterns.
- Add focused PHPUnit coverage for strategy registration, cascade expansion, delete ordering, component cleanup, wiki cleanup, and excluded blocker handling.

## Non-goals

- Deleting assessment, course implementation, course specification, or `mv_assessment_relation` data.
- Changing the CMS soft-delete behavior.
- Adding or changing database foreign keys.
- Removing storage blobs referenced by deleted CMS rows. Existing garbage collection/storage cleanup remains responsible for unreferenced physical content.
- Building a generic dependency graph framework for all data retention strategies.

---

## Architecture

Add one new strategy class: `Schoolbox\Admin\DataRetention\Strategy\Cms`.

The class should follow the current `MyFiles` and `Moments` strategy patterns:

- typed constants for table names
- small protected helper methods for lookup and deletion
- `Builder::getArrayPlaceholders()` and `Builder::getArrayParameters()`
- `Base::getPreparedStatement()` for reusable prepared statements
- `Base::deleteStatement()` so dry-run mode uses `SELECT ...` and perform mode uses `DELETE ...`

The strategy should chunk by candidate root folders selected from `folder.deleted_at`, then expand each chunk to a deduplicated subtree before deleting dependent rows.

### Files

| File | Change |
|---|---|
| `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php` | New strategy for Resources CMS retention |
| `library/Schoolbox/Admin/DataRetention/CommandLineTool.php` | Add CMS strategy to the bus |
| `library/Schoolbox/Admin/DataRetention/ValidStrats.php` | Add `cms` to valid strategy names |
| `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php` | New focused tests for cascade planning and delete order |
| `library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php` | Add `cms` filtering/enabled coverage |

No database migration is required because `dataRetention_CMS` already exists in `updates/26.1.alpha1/data-retention-admin.sql` and `ConfigMapper`.

---

## Intended Behavior

### Candidate Selection

A folder is a retention candidate when:

```sql
folder.deleted_at IS NOT NULL
AND folder.deleted_at < :old
AND NOT EXISTS (
    SELECT 1
    FROM folder child
    WHERE child.parent = folder.id
    AND child.deleted_at IS NULL
)
```

The cutoff `:old` comes from `Base::calculateDate()`, using either the CLI retention override or `ConfigMapper::NAME_DATA_RETENTION_CMS`.

If the configured retention period is empty, invalid, zero, or blocked by existing `RetentionPeriod` validation, the existing base behavior applies and the strategy skips work.

### Folder Cascade

When a candidate folder is selected, soft-deleted descendant folders beneath it are included in the same purge plan.

Active descendant folders are never included in the hard-delete plan. For this behavior, "active" means `folder.deleted_at IS NULL`. This prevents a retained parent folder from hard-deleting child folders that are still visible or otherwise not soft-deleted.

Nested candidate folders must be deduplicated. If both folder `10` and child folder `11` are selected in the same chunk, the soft-deleted subtree should be processed once.

This makes CMS retention bottom-up. A soft-deleted parent that still has active children is skipped for that run. Once those children are soft-deleted and past the retention cutoff, the child folders can be hard-deleted first, and a later run can hard-delete the parent when no active children remain.

### Excluded Relation Blockers

Before deleting a folder subtree, the strategy should detect whether any folder in the expanded set is referenced by excluded assessment/course tables:

- `assessment_folder`
- `course_implementation`
- `course_specification`
- `mv_assessment_relation`

If blockers are found anywhere in the expanded folder set for a chunk, the strategy should skip that expanded chunk for this run and log a warning with the affected count.

This keeps SA-21886 scoped to CMS data while avoiding repeated transaction failures on known out-of-scope foreign keys.

### Delete Ordering

For the unblocked folder ids in a chunk, deletion should run in this order:

1. derive dependent ids:
   - `blog.id` by `blog.folder_id`
   - `forumThread.threadID` by `forumThread.categoryID`
   - `poll.id` by `poll.folder_id`
   - `wiki.wikiID` by `wiki.categoryID`
   - homepage `component_instance.id` where `module = 'homepage'` and `identifier IN folderIds`
2. delete child dependency tables:
   - `blog_comment`, `blog_image`, then `blog`
   - `forumPost`, then `forumThread`
   - `pollLog`, then `poll`
   - set `wiki.latestRevision = NULL`, then delete `wikiRevision`, then `wiki`
   - `component_config_instance`
   - `eportfolio_component_advancedlist_item`
   - `eportfolio_component_basiclist_item`
   - `eportfolio_component_linklist_item`
   - `eportfolio_component_textbox_item`
   - `component_instance`
3. delete direct folder-owned rows:
   - `chat`
   - `chat_poll`
   - `etherpad_pad`
   - `event_folder`
   - `file`
   - `folder_code`
   - `folder_year_level`
   - `google_file`
   - `homepage_config`
   - `homepage_log`
   - `homepage_visits`
   - `link`
   - `moment_folder`
   - `perms`
   - `reportcard_comment`
   - `search_index`
   - `socialstream_post`
   - `textbox`
   - `tile_layout_for_folder`
   - `userGroups`
4. delete folders leaf-first

The strategy should use the actual schema table name `textbox`, even though the Jira table list uses `textBox`.

---

## Component Detail

### `ValidStrats`

Add:

```php
public const string CMS = 'cms';
```

and include it in `STRATS_WHITELIST` after `MYFILES`.

### `CommandLineTool`

When `ValidStrats::CMS` is enabled, append a new `Strategy\Cms` instance to `$bus`, passing the same constructor arguments used by `Moments` and `MyFiles`.

### `Strategy\Cms`

The strategy should implement:

- `getRetentionLabel(): string` returning `Resources (CMS) Retention`
- `getStrategyName(): string` returning `ValidStrats::CMS`
- `hasDataToDelete(): bool`
- `getIdsToDelete(): array`
- `deleteChunk(array $chunk): void`

Internal helpers should cover:

- id normalization
- descendant folder discovery
- folder cascade expansion
- excluded blocker detection
- id lookups by folder ids
- row deletion by ids
- row deletion by folder ids
- wiki latest revision unlinking
- leaf-first folder deletion

Keep the strategy explicit rather than storing a large generic table graph. This makes the FK order reviewable and avoids creating a new abstraction for one ticket.

---

## Logging

The strategy should log:

- the cutoff date used for CMS deletion
- how many root folders are eligible
- how many folders are included after subtree expansion
- how many folders caused a chunk to be skipped due to excluded relation blockers
- per-table delete counts in perform mode
- per-table would-delete counts in dry-run mode

Use the existing "Deleted ..." / "Would have deleted ..." pattern from `MyFiles` and `Moments`.

---

## Error Handling

`Base::process()` remains responsible for:

- transactions
- dry-run rollback
- perform commit
- chunk rollback on `PDOException` or mapper exceptions
- lock refresh
- max runtime handling

`Cms::deleteChunk()` should not commit or roll back directly.

Known excluded relation blockers should be handled before delete statements run. Unexpected database failures should be allowed to bubble to `Base::process()` so the existing logging and rollback behavior applies.

---

## Testing

Add focused PHPUnit coverage without requiring a real database by using a test subclass that overrides lookup and low-level delete helpers.

Tests should cover:

- `ValidStrats` accepts and filters `cms`.
- The CMS strategy label/name use the expected values.
- A candidate folder expands to descendants and deletes folders leaf-first.
- A soft-deleted parent with an active child does not hard-delete that active child.
- Candidate SQL excludes folders with active child folders.
- Nested candidate folders are deduplicated.
- Blog, forum, poll, wiki, and component child rows are deleted before their parent rows.
- Direct folder-owned tables are deleted before folders.
- Chunks with excluded blocker folders are skipped and do not call delete helpers.

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php
./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php
```

Then run the standard backend checks for changed files:

```bash
php -l library/Schoolbox/Admin/DataRetention/Strategy/Cms.php
php -l library/Schoolbox/Admin/DataRetention/ValidStrats.php
php -l library/Schoolbox/Admin/DataRetention/CommandLineTool.php
./vendor/bin/phpcs library/Schoolbox/Admin/DataRetention/Strategy/Cms.php library/Schoolbox/Admin/DataRetention/ValidStrats.php library/Schoolbox/Admin/DataRetention/CommandLineTool.php library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php
./vendor/bin/phpstan analyze library/Schoolbox/Admin/DataRetention/Strategy/Cms.php library/Schoolbox/Admin/DataRetention/ValidStrats.php library/Schoolbox/Admin/DataRetention/CommandLineTool.php library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php
```

---

## Out of Scope Follow-up

The excluded assessment/course relation cleanup should be tracked separately. Once that work exists, this CMS strategy can be revisited to remove or narrow the excluded blocker skip path.

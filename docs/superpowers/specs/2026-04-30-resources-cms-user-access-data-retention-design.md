# Design: Resources CMS userAccess Data Retention

**Date:** 2026-04-30  
**Status:** Draft

## Background

The Resources (CMS) data retention strategy already hard-deletes soft-deleted `folder` rows and associated folder-owned CMS data. However, homepage-specific permission rows in `userAccess` are also keyed by folder id via `sid`, and they must be purged when the corresponding `folder` tree is removed.

The existing strategy is implemented in `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php`, and it already handles:

- selecting candidate `folder` rows by `deleted_at < cutoff`
- expanding folder subtrees using `folder.parent`
- skipping chunks blocked by excluded assessment/course relations
- deleting dependent tables in FK-safe order
- deleting folders leaf-first
- preserving dry-run semantics via `Base::deleteStatement()`

This design extends that strategy to include homepage-scoped `userAccess` cleanup.

## Problem

`userAccess` is a generic permissions table with columns:

- `uid` — user id
- `service` — service id
- `sid` — service identifier, in this case a homepage folder id
- `type` — access level

A CMS folder hard delete should remove `userAccess` rows only when:

- `service = UserAccess::SERVICE_HOMEPAGE`
- `sid IN <deleted folder ids>`

A generic `userAccess` delete by `sid` without narrowing `service` would risk removing unrelated permissions for calendar, news, pastoral, and other services. The cleanup therefore requires a dedicated method.

## Detailed Design

### 1. New helper in `Cms.php`

Add a protected method:

```php
protected function deleteHomepageUserAccess(array $folderIds): int
```

Behavior:

- return `0` if `$folderIds` is empty
- build `IN` placeholders from `$folderIds`
- use `deleteStatement('userAccess')` to preserve dry-run semantics
- execute with `service = UserAccess::SERVICE_HOMEPAGE`
- return `rowCount()`

### 2. Invoke cleanup in `deleteChunk()`

In `deleteChunk(array $chunk): void`, after the existing `deleteFolderOwnedRows($folderIds)` call, add:

```php
$counts['userAccess'] = $this->deleteHomepageUserAccess($folderIds);
```

Log the result with the same `Deleted` / `Would have deleted` summary pattern.

### 3. Keep userAccess cleanup service-scoped

The SQL query must include:

```sql
WHERE service = :service AND sid IN (...) 
```

With `:service` bound to `UserAccess::SERVICE_HOMEPAGE`.

### 4. Ordering

The new cleanup belongs after dependent table deletes and before the folder rows themselves are removed. This maintains the existing folder-oriented delete order:

1. delete child records by child ids
2. delete folder-owned rows
3. delete homepage `userAccess`
4. delete folders leaf-first

### 5. Tests

Extend `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php` with coverage that asserts:

- a valid chunk triggers `deleteHomepageUserAccess()`
- the helper is not called when a chunk is blocked
- the helper returns a count and that count is aggregated into `counts`
- dry-run mode still works because the helper uses `deleteStatement('userAccess')`

The existing anonymous subclass pattern is the appropriate approach, overriding only the database lookup and delete helper methods.

### 6. No database migration

No `sql/` or `updates/` changes are required, because `userAccess` already exists in the schema.

## Acceptance Criteria

- `Cms.php` includes a dedicated homepage `userAccess` cleanup method.
- `deleteChunk()` calls the cleanup method for each non-blocked chunk.
- `userAccess` cleanup is restricted to `UserAccess::SERVICE_HOMEPAGE`.
- Dry-run mode uses `SELECT` rather than `DELETE` for the new helper.
- New PHPUnit coverage confirms the new cleanup path and block-skip behavior.
- No unrelated `userAccess` rows for other services are deleted.

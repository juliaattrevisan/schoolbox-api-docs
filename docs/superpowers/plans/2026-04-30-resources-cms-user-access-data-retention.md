# Resources CMS userAccess Data Retention Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Extend the existing Resources (CMS) data retention strategy so soft-deleted `folder` trees also remove homepage-scoped `userAccess` rows that reference deleted folders.

**Architecture:** Update `Schoolbox\Admin\DataRetention\Strategy\Cms` to delete `userAccess` rows for `service = UserAccess::SERVICE_HOMEPAGE` and `sid IN (...)` alongside the existing folder-owned cleanup. Preserve dry-run semantics through `Base::deleteStatement()` and guard against unrelated `userAccess` rows from other services.

**Tech Stack:** PHP 8.4, PHPUnit, MariaDB 11.8 SQL via `IConnector`, PSR-3 logging, Symfony Lock.

---

## File Map

| Action | Path |
|---|---|
| Modify | `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php` |
| Modify | `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php` |

---

## Task 1: Add homepage `userAccess` cleanup to CMS strategy

**File:** `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php`

- [ ] Add a protected helper method to delete `userAccess` by folder ids and homepage service.
- [ ] Use `Builder::getArrayPlaceholders()` and `Builder::getArrayParameters()` for safe query generation.
- [ ] Ensure the method uses `deleteStatement('userAccess')` so dry-run mode executes `SELECT` instead of `DELETE`.
- [ ] Add the helper call to `deleteChunk()` in the delete phase alongside `deleteFolderOwnedRows()`.
- [ ] Count rows deleted from `userAccess` and include them in the strategy summary logs.
- [ ] Keep deletion restricted to `service = UserAccess::SERVICE_HOMEPAGE` to avoid deleting unrelated permissions.

## Task 2: Add or update focused test coverage

**File:** `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php`

- [ ] Add a test asserting that `deleteChunk()` calls the new `deleteHomepageUserAccess()` helper.
- [ ] If needed, extend the anonymous test strategy subclass with a stubbed `deleteHomepageUserAccess()` method.
- [ ] Verify that `deleteChunk()` still skips blocked folder chunks and does not call `userAccess` cleanup when expansion is blocked.
- [ ] Verify that a valid chunk with folders triggers the `userAccess` deletion count.

## Task 3: Verify behavior

- [ ] Run the focused unit tests for `CmsStrategyTest.php`.
- [ ] Confirm the existing CMS strategy flow still works in dry-run mode.
- [ ] Confirm the new `userAccess` cleanup method passes with dry-run and perform modes.

## Notes

- No schema migration is required; `userAccess` already exists in `sql/initial.sql`.
- This is a specific CMS permissions cleanup only, not a generic cleanup for all services stored in `userAccess`.
- The additional cleanup should happen after folder-owned row deletion and before deleting the folders themselves.

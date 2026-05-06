# Design: SA-21889 Student Submission Data Retention

**Date:** 2026-05-06  
**Status:** Approved

---

## Background

SA-21889 belongs to the hard deletion of soft-deleted data work. The data retention subsystem already has strategies for Learning Moments and My Files, and the admin configuration already includes `ConfigMapper::NAME_DATA_RETENTION_STUDENTSUBMISSIONS`.

The missing piece is a strategy that permanently removes eligible soft-deleted Student Submission rows and the directly related records that block those rows from being hard-deleted.

---

## Problem

`student_submission` rows can be soft-deleted and retained indefinitely. Their related `student_submission_file` rows keep references to uploaded files, and integration-specific rows can reference each submission file.

The relevant dependency chain from the ticket is:

- `student_submission`
- `student_submission_file`
- `kami_document`
- `plagscan_document`
- `turnitin_report`

Without a dedicated retention strategy, these rows are not purged by the data retention CLI and associated storage references remain in use.

---

## Goals

- Add a Student Submissions data retention strategy.
- Select eligible `student_submission` rows using `student_submission.deleted_at` and the configured Student Submissions retention period.
- Delete related rows in foreign-key-safe order before deleting the submission rows.
- Support existing dry-run and perform modes through `Strategy\Base`.
- Register the strategy so it runs by default and can be selected through the CLI.
- Add focused PHPUnit coverage for delete ordering and strategy registration.

## Non-goals

- Deleting `storage` rows directly; storage garbage collection remains responsible for orphaned file cleanup.
- Deleting `submission_return` rows or other business-linked data not listed in SA-21889.
- Adding or changing database schema.
- Changing how Student Submissions are soft-deleted by application code.

---

## Architecture

The change follows the existing `Schoolbox\Admin\DataRetention\Strategy\Base` pattern.

### Files

| File | Change |
|---|---|
| `library/Schoolbox/Admin/DataRetention/Strategy/StudentSubmissions.php` | New strategy for selecting and hard-deleting eligible Student Submission data |
| `library/Schoolbox/Admin/DataRetention/ValidStrats.php` | Add `studentSubmissions` to the CLI strategy whitelist |
| `library/Schoolbox/Admin/DataRetention/CommandLineTool.php` | Instantiate the new strategy when enabled |
| `library/Schoolbox/Admin/DataRetention/Test/StudentSubmissionsStrategyTest.php` | Add focused unit tests for file resolution and delete ordering |
| `library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php` | Extend registration tests for the new strategy name |

No migration is required because the Student Submissions retention config already exists.

---

## Intended Behavior

### Eligibility

A `student_submission` row is eligible when:

- `student_submission.deleted_at IS NOT NULL`
- `student_submission.deleted_at < :old`, where `:old` is calculated from the configured Student Submissions retention period or CLI override

Only `student_submission.deleted_at` controls eligibility. Related `student_submission_file` rows are deleted because their parent submission is eligible, regardless of the file row's own `deleted_at` value.

### Delete ordering

For a chunk of eligible submission ids, the strategy should:

1. Resolve all `student_submission_file.id` values for those submissions.
2. Delete `turnitin_report` rows for those file ids.
3. Delete `plagscan_document` rows for those file ids.
4. Delete `kami_document` rows for those file ids.
5. Delete `student_submission_file` rows for those file ids.
6. Delete `student_submission` rows for the chunk.

This order keeps the implementation explicit and safe even where a database constraint would cascade one of the child tables.

### CLI registration

The new strategy name should be `studentSubmissions`.

When no `--strats` filter is supplied, the strategy runs with the rest of the data retention bus. When `--strats=studentSubmissions` is supplied, only this strategy runs.

---

## Error Handling

The new strategy should rely on the existing `Base::process()` transaction handling:

- each chunk runs inside a transaction
- perform mode commits the chunk
- dry-run mode rolls back the chunk
- database and mapper exceptions roll back the chunk and are logged

The strategy should not introduce partial commits or side effects outside the database transaction.

---

## Testing

Add focused PHPUnit tests that do not require a real database by using a test subclass or mocked connector where appropriate.

The tests should verify:

- submission ids resolve to related file ids
- child tables are deleted before `student_submission_file`
- `student_submission_file` rows are deleted before `student_submission`
- submissions with no files still allow the parent submission delete step
- duplicate or mixed ids are normalized into deterministic delete sets
- `ValidStrats` accepts and enables `studentSubmissions`

Final verification should run the targeted PHPUnit tests, PHP syntax checks for changed files, PHPCS, PHPStan for changed PHP files, and authoritative backend verification inside `docker/ssh` when available.

---

## Plan

The implementation checklist is in `docs/superpowers/plans/2026-05-06-sa-21889-student-submission-data-retention.md`.

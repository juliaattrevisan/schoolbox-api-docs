# SA-21889 Student Submission Data Retention Plan

## Summary

Add a new Data Retention strategy that hard-deletes soft-deleted `student_submission` rows older than the configured Student Submissions retention period. The strategy will delete only the Jira-listed dependency chain: `turnitin_report`, `plagscan_document`, `kami_document`, `student_submission_file`, then `student_submission`.

## Key Changes

- Add `Schoolbox\Admin\DataRetention\Strategy\StudentSubmissions`.
- Register a new CLI strategy name, recommended as `studentSubmissions`, in `ValidStrats`.
- Add the strategy to `CommandLineTool` so it runs by default and can be selected with `--strats=studentSubmissions`.
- Use existing config key `ConfigMapper::NAME_DATA_RETENTION_STUDENTSUBMISSIONS`.
- Do not delete `storage`; existing garbage collection should later remove orphaned file hashes.

## Implementation Steps

1. Create `library/Schoolbox/Admin/DataRetention/Strategy/StudentSubmissions.php` extending `Strategy\Base`.
2. Implement `hasDataToDelete()` with counts for eligible `student_submission` rows and related child rows.
3. Implement `getIdsToDelete()` selecting `student_submission.id` where `deleted_at IS NOT NULL AND deleted_at < :old`.
4. Implement chunk deletion:
   - Resolve submission IDs from the chunk.
   - Find `student_submission_file.id` values for those submissions.
   - Delete child rows in this order: `turnitin_report`, `plagscan_document`, `kami_document`.
   - Delete `student_submission_file` rows.
   - Delete `student_submission` rows.
   - Use `Base::deleteStatement()` so dry-run mode performs `SELECT` statements inside the existing rollback flow.
5. Update `ValidStrats` whitelist and tests for the new `studentSubmissions` value.
6. Update `CommandLineTool` to instantiate `Strategy\StudentSubmissions` with the same constructor arguments as existing strategies.

## Test Plan

- Add `StudentSubmissionsStrategyTest` covering:
  - Related file IDs are resolved from submission IDs.
  - Child document tables are deleted before `student_submission_file`.
  - Submission files are deleted before `student_submission`.
  - Empty chunks or submissions without files do not attempt child deletes and still delete eligible submissions.
  - Direct deletion set is normalized and deterministic.
- Extend `ValidStratsTest` for:
  - `studentSubmissions` as a valid single strategy.
  - mixed `moments,myFiles,studentSubmissions`.
  - `isStratEnabled()` behavior when only `studentSubmissions` is selected.
- Run:
  - `php -l library/Schoolbox/Admin/DataRetention/Strategy/StudentSubmissions.php`
  - `php -l library/Schoolbox/Admin/DataRetention/CommandLineTool.php`
  - `php -l library/Schoolbox/Admin/DataRetention/ValidStrats.php`
  - `./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/StudentSubmissionsStrategyTest.php`
  - `./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php`
  - `./vendor/bin/phpcs` and `./vendor/bin/phpstan analyze` on changed PHP files
  - Final authoritative verification inside `docker/ssh` when available.

## Assumptions

- Scope is the Jira-listed tables only; `submission_return.student_submission_id` is not touched.
- All `student_submission_file` rows belonging to an eligible deleted submission are deleted, regardless of each file row's own `deleted_at`.
- `kami_document` is explicitly deleted for predictable dry-run/log counts even though its FK has `ON DELETE CASCADE`.
- No database migration is needed because the Student Submissions retention config already exists.

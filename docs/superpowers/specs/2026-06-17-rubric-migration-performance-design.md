---
title: Rubric Migration Performance — Replace per-row INSERTs with INSERT...SELECT
date: 2026-06-17
author: jleckie
ticket: SA-22643
---

## Problem

`updates/26.1.alpha1/rubric/02-replicate-settings.php` replicates rubric settings from
`submission_box` into the new `rubric_instance` table by executing one `INSERT INTO rubric_instance`
per row inside a PHP `while` loop. On large customer deployments this produces tens of thousands of
individual database round-trips.

Observed impact: 82,305 submission boxes → 82,305 separate INSERTs → **226.73 seconds**.

## Goal

Reduce the migration time from minutes to seconds by eliminating the per-row round-trip overhead.

## Approach

Replace the prepared-statement loop with a single `INSERT ... SELECT` statement that performs the
join and insert entirely within MySQL. This is the same pattern applied to
`updates/26.1.alpha1/reporting/04-courseOutlineFilters.php` (commit c2d38b4), which eliminated up
to 25,000 UPDATE round-trips.

## What Changes

### Removed

- The `$insertStatement` prepared statement (the `INSERT INTO rubric_instance ... VALUES (...)` form).
- The `$statement` cursor and `$statement->execute()` call (the `SELECT` that fetched all rows into PHP).
- The `while ($row = $statement->fetch(...))` loop and everything inside it, including:
  - `$numFound++`
  - `$insertStatement->execute([...])`
  - The per-row `debug` log line (was only visible with `-vvv`; no impact on normal upgrade runs).

### Added

A single `executeQuery` call using `INSERT ... SELECT`:

```sql
INSERT INTO rubric_instance (
    assessment_id,
    title,
    weight,
    rubric_capability_mark_type_id,
    rubric_capability_grade_group_id,
    rubric_mark_range,
    created_at,
    updated_at
)
SELECT
    sb.assessment_id,
    a.title,
    :weight,
    sb.rubric_capability_mark_type_id,
    sb.rubric_capability_grade_group_id,
    sb.rubric_mark_range,
    :createdAt,
    :updatedAt
FROM submission_box sb
INNER JOIN assessment a ON sb.assessment_id = a.id
```

Parameters: `weight` (constant `100.0`), `createdAt` and `updatedAt` (both `$now`).

`$numFound` is populated from `PDOStatement::rowCount()` on the insert result — MySQL returns the
correct affected-row count for `INSERT` statements.

### Unchanged

- `$now` timestamp (still used to bind parameters and to scope the count-verification query).
- `$start` / `$end` timer.
- The count-verification `SELECT COUNT(*) FROM rubric_instance WHERE created_at = :createdAt` query.
- `reportDivergence()`.
- The top-level info log line.

## Expected Outcome

82,305 database round-trips → 1. Expected migration time: under 5 seconds (from ~227 seconds).
The count-verification and divergence report continue to work without modification.

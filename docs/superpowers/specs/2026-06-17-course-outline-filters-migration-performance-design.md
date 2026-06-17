---
title: Course Outline Filters Migration Performance Improvement
date: 2026-06-17
author: james.leckie@schoolbox.education
ticket: SA-26.1.alpha1
file: updates/26.1.alpha1/reporting/04-courseOutlineFilters.php
---

# Course Outline Filters Migration — Performance Design

## Problem

The migration script `04-courseOutlineFilters.php` updates `component_config_instance` rows for
course outline components that still have default `assessmentTypes` filter values, adding
`"reporting": "1"` to each. It currently does this with:

1. A `SELECT` that fetches all candidate rows (up to 25,000) into PHP memory
2. A `foreach` loop that JSON-decodes each row, checks if it's still at defaults, and
   executes an individual `UPDATE` prepared statement per matching row

At 25,000 rows this results in up to 25,000 database round-trips, making the script
unacceptably slow on large installations.

## Solution

Replace the PHP loop with 1–2 SQL `UPDATE` queries that use MySQL's `JSON_EXTRACT` for
the "still at defaults" filter and `JSON_SET` to add the `reporting` key. No `SELECT` or
PHP-side row processing is needed.

This is viable because:
- Values in `component_config_instance.value` are stored as plain JSON (confirmed via DB
  inspection) — `html_entity_decode()` is not required and can be removed
- `JSON_SET` adds a key without disturbing existing key order, sidestepping the ordering
  concern noted in the original code comment
- The only runtime variable (LTI enabled/disabled) affects which keys are expected in the
  "default" set, handled by branching on two pre-built query strings in PHP

## Query Design

The LTI config flag is read once in PHP (unchanged from current behaviour). Based on it,
one of two `UPDATE` queries is executed directly against the database.

### LTI enabled

Checks that all 7 non-reporting types are `"1"` and `reporting` is not already `"1"`:

```sql
UPDATE component_config_instance cci
INNER JOIN component_config_definition ccd
    ON cci.component_config_definition_id = ccd.id
    AND ccd.`key` = 'assessmentTypes'
INNER JOIN component_instance ci
    ON cci.component_instance_id = ci.id
INNER JOIN component_definition cd
    ON ci.component_definition_id = cd.id
    AND cd.class = 'Schoolbox\\Learning\\Assessment\\Component\\Homepage\\Outline\\Controller'
SET cci.value = JSON_SET(cci.value, '$.reporting', '1')
WHERE JSON_EXTRACT(cci.value, '$.project')     = '1'
  AND JSON_EXTRACT(cci.value, '$.dueWork')     = '1'
  AND JSON_EXTRACT(cci.value, '$.task')        = '1'
  AND JSON_EXTRACT(cci.value, '$.quiz')        = '1'
  AND JSON_EXTRACT(cci.value, '$.lessonPlan')  = '1'
  AND JSON_EXTRACT(cci.value, '$.dialogAgent') = '1'
  AND JSON_EXTRACT(cci.value, '$.lti')         = '1'
  AND (JSON_EXTRACT(cci.value, '$.reporting') IS NULL
    OR JSON_EXTRACT(cci.value, '$.reporting') != '1')
```

### LTI disabled

Identical to the above but without the `$.lti` condition. Sites that never had LTI enabled
would not have `"lti": "1"` in their stored defaults, so requiring it would incorrectly
exclude those rows.

## Logging

The final `info`-level log line is preserved, reporting the number of updated rows.
The affected row count returned by the DB layer's statement result replaces the manual `$updated` counter.

The total-candidate count (previously `count($configInstances)`) is omitted from the log
message — it is no longer meaningful because unfiltered/skipped rows are now excluded by
the SQL `WHERE` clause rather than counted in PHP.

Per-row `debug` log calls are removed. At scale (25k rows) these added noise without
actionable value.

## What Changes

| Before | After |
|---|---|
| `SELECT` + `fetchAll()` (up to 25k rows) | Removed |
| `foreach` loop with JSON decode/encode per row | Removed |
| `$updateStatement` prepared statement executed N times | Removed |
| Per-row `debug` log calls | Removed |
| `html_entity_decode()` call | Removed (not needed) |
| LTI config check | Kept |
| Final `info` log with updated count | Kept |
| `runAsRoot()`, CLI options, `CliTool` scaffolding | Untouched |

## What Does Not Change

- The migration's logical outcome is identical: only rows that are still at defaults are
  updated; rows with custom filters are untouched
- The LTI branching logic is preserved (two query variants)
- The script remains idempotent — rows where `reporting` is already `"1"` are excluded
  by the `WHERE` clause

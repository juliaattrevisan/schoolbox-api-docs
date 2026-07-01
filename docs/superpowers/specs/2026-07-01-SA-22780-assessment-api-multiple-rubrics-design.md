# Assessment API: multiple titled rubrics

**Ticket:** SA-22780
**Date:** 2026-07-01
**Status:** Design approved

## Problem

Rubrics have been re-schema'd. Previously a rubric was tied to due work (`submission_box`)
and an assessment had at most one rubric. The migrated schema decouples rubrics from due
work — `rubric_instance` now hangs directly off `assessment_id`, carries its own `title` and
`weight`, and an assessment can have **many** rubric instances.

The Assessment API (`GET /api/assessment` list and `GET /api/assessment/{id}`) has not caught
up. It still returns a **single** `rubric` object in two places — the assessment template and
each feedback item — and both queries are still coupled to `submission_box`. We need the API
to return a **set of titled rubrics**, decoupled from due work, on both endpoints and in both
places.

## Relevant current state

All read paths flow through `library/Schoolbox/Learning/Assessment/API/Helper.php`:

- `getRubric($assessmentId)` — assessment-level template, emitted at `Helper.php:170` as `rubric`.
  Query joins `v_rubric` **INNER JOIN `submission_box`** (due-work coupling), returns one object
  `{ capabilities: [...] }` or `null`.
- `getRubricFeedback($assessmentId, $feedbackId)` — marked rubric per feedback item, emitted at
  `Helper.php:694` inside `feedback`, and again at `Helper.php:713` in the deprecated top-level
  spot. Also joins through `submission_box`; filters `sr.review_route = 'instructor'`.

The `v_rubric` view (last defined in `updates/26.1.beta4/rubric/16-v_rubric.sql`) **already**
joins `rubric_instance` by `assessment_id` and exposes `rubric_instance_id`, but does **not**
expose `rubric_instance.title` or `.weight`.

## Response contract (decided)

Non-breaking. Add a new `rubrics` array; keep the singular `rubric` populated as a deprecated
alias of the first rubric.

Assessment template item shape:

```json
"rubric": { "capabilities": [...] },          // deprecated, == rubrics[0] minus id/title/weight
"rubrics": [
  { "id": 1, "title": "Written", "weight": 60, "capabilities": [...] },
  { "id": 2, "title": "Oral",    "weight": 40, "capabilities": [...] }
]
```

Feedback item shape (inside each `feedback`; marked capabilities keep `value`/`mark`/`descriptor`):

```json
"rubric": { "capabilities": [...] },          // deprecated, == rubrics[0] minus id/title
"rubrics": [
  { "id": 1, "title": "Written", "capabilities": [...] },
  { "id": 2, "title": "Oral",    "capabilities": [...] }
]
```

- Each **template** rubric exposes: `id` (= `rubric_instance_id`), `title`, `weight`, `capabilities`.
- Each **feedback** rubric exposes: `id`, `title`, `capabilities` (no `weight` on the feedback side).
- When an assessment has no rubrics, `rubrics` is `[]` (always an array). The deprecated singular
  `rubric` stays `null` in that case (unchanged from today).

### Deprecation window

The singular `rubric` is a **6-month** backwards-compat shim to let customers migrate, then it
will be removed. It only carries the **first** rubric — for assessments with multiple rubrics the
singular field is intentionally lossy. This is accepted.

## Approach

### 1. `v_rubric` view

New migration `updates/26.1.beta7/rubric/<n>-v_rubric.sql` (CREATE OR REPLACE VIEW), adding to
**both** UNION arms:

```sql
rubric_instance.title  AS rubric_instance_title,
rubric_instance.weight AS rubric_instance_weight,
```

`getRubric` selects `t.*` from `v_rubric`, so it picks these up automatically. The feedback path
reads the derived `v_rubric_mark` view (see below), which in turn selects from `v_rubric`.

### 1a. `v_rubric_mark` view (feedback source)

The feedback side is rewritten to read the existing, purpose-built `v_rubric_mark` view rather than
a hand-rolled `submission_box` join. `v_rubric_mark` already `UNION`s the two places rubric marks
live — `submission_return` (due work) and `reporting_feedback` (reporting) — and exposes
`assessment_id`, `feedback_id`, `feedback_table`, `feedback_type`, `rubric_instance_id`, and the
capability/indicator mark columns. This makes feedback genuinely agnostic and fixes reporting
feedback rubrics (silently dropped today because the old query hardcodes
`feedback_table = 'submission_return'`).

**Performance:** verified on the target DB (MariaDB 11.8.6, `condition_pushdown_for_derived=on`).
`EXPLAIN` of `SELECT * FROM v_rubric_mark WHERE assessment_id=? AND feedback_id=?` pushes the
predicates into the UNION arms — every base table resolves as `const`/`ref` index lookups, no
whole-DB materialization, ~3ms. No regression versus the current query.

New migration `updates/26.1.beta7/rubric/17-v_rubric_mark.sql` (CREATE OR REPLACE VIEW) redefines
`v_rubric_mark` with three changes in **both** UNION arms:

1. **Title:** add `vr.rubric_instance_title AS rubric_instance_title` (available once §1 lands).
2. **Descriptor gap:** `v_rubric_mark` currently exposes only `descriptor_id`/`descriptor_name`. The
   feedback contract also promises `descriptor.maxValue` and `descriptor.sequence`, so add
   `LEFT JOIN rubric_descriptor rd ON rim.descriptor_id = rd.id AND rd.deleted_at IS NULL` and
   `LEFT JOIN rubric_level rl ON rd.level_id = rl.id AND vr.rubric_instance_id = rl.rubric_instance_id
   AND rl.deleted_at IS NULL`, selecting `rd.max_value AS descriptor_max_value` and
   `rl.sequence AS descriptor_sequence`. Both joins are 0:1 (PK/eq_ref), so they do not change the
   view's row count for its existing consumer (`Rubric\Markbook\OverallDataset`).
3. **Collation:** the reporting arm's string literal `'instructor' AS feedback_type` is created under
   a `utf8mb3` connection, so `feedback_type` becomes a mixed-charset UNION column and
   `WHERE feedback_type = 'instructor'` throws *Illegal mix of collations*. Normalise every string
   expression the view emits to `utf8mb4_unicode_ci` — wrap `feedback_type` in both arms as
   `CONVERT(<expr> USING utf8mb4) COLLATE utf8mb4_unicode_ci` (arm 1 `<expr>` = `sr.review_route`,
   arm 2 `<expr>` = `'instructor'`). This is the "use `utf8mb4_unicode_ci` everywhere" rule applied to
   the view's own output.

Do **not** change the view's `INNER JOIN v_rubric_indicator_mark_extend` to a `LEFT JOIN`: that
join semantics is shared with the markbook consumer, and row-for-row parity with the old feedback
query was confirmed on real data (7 == 7 rows for a sample feedback).

Base-table charset normalisation (some columns are `utf8mb3` at rest) is a broader concern and is
**out of scope**; we only normalise the view's emitted string columns here.

### 2. `getRubric` → `getRubrics` (rename)

- Return `array` (list), never `null`; empty `[]` when the assessment has no rubric instances.
- **Remove** the `INNER JOIN submission_box` — query `v_rubric` directly by `assessment_id`. This
  is what makes it agnostic: reporting and other non-due-work types with rubric instances now
  return rubrics too.
- Group rows first by `rubric_instance_id`, then build `capabilities` within each instance exactly
  as today.
- Each element: `{ id: rubric_instance_id, title, weight, capabilities: [...] }`.
- Call site (`Helper.php:170`):
  - `'rubrics' => $rubrics`
  - `'rubric'  => isset($rubrics[0]) ? ['capabilities' => $rubrics[0]['capabilities']] : null`
    (strip `id`/`title`/`weight` so the deprecated shape matches the old payload exactly).

### 3. `getRubricFeedback` → `getRubricsFeedback` (rewrite onto `v_rubric_mark`)

- New signature: `getRubricsFeedback(string $assessmentId, int $feedbackID, string $feedbackTable): array`.
  The extra `$feedbackTable` disambiguates `feedback_id` across sources (`submission_return.id` and
  `reporting_feedback.id` share no id-space guarantee) and is already available at the call site
  (each participation-union arm selects a `feedback_table` literal).
- Body becomes a thin read of `v_rubric_mark`:
  `SELECT ... FROM v_rubric_mark WHERE assessment_id = :assessmentID AND feedback_id = :feedbackID
  AND feedback_table = :feedbackTable AND feedback_type = 'instructor'` — preserving the current
  instructor-only behaviour (`feedback_type` is the normalised `review_route`; the reporting arm is
  always `'instructor'`). No `submission_box`/`submission_return` joins in PHP anymore.
- Group rows by `rubric_instance_id`, return an array. Each element: `{ id, title, capabilities: [...] }`
  (marked capabilities unchanged in shape; `descriptor.maxValue`/`sequence` now come from the
  extended view columns `descriptor_max_value`/`descriptor_sequence`).
- Call sites (`Helper.php:677`, `Helper.php:694`, deprecated block `Helper.php:713`):
  - `$rubrics = $this->getRubricsFeedback($rowAssessmentId, (int)$row['feedback_id'], $row['feedback_table']);`
  - `feedback['rubrics'] = $rubrics`
  - `feedback['rubric']  = isset($rubrics[0]) ? ['capabilities' => $rubrics[0]['capabilities']] : null`
  - The deprecated top-level `rubric` (713) continues to point at the singular for backwards compat.

### 4. Caller sweep

Before renaming, grep for `getRubric(` / `getRubricFeedback(` across the codebase and update any
other callers. (Expected to be internal to this Helper, but verify.)

### 5. API docs (`/home/james/Sites/schoolbox-api-docs`)

- New `openapi/components/schemas/assessment-rubrics.yaml`: array of
  `{ id, title, weight, capabilities }`, reusing the existing `assessment-rubric.yaml` capabilities
  block.
- New `openapi/components/schemas/assessment-rubrics-feedback.yaml`: array of
  `{ id, title, capabilities }`, reusing `assessment-rubric-feedback.yaml` capabilities block.
- `assessment-item.yaml`: add `rubrics` (→ new schema); mark existing `rubric` `deprecated: true`
  with a note that it carries only the first rubric and will be removed after the 6-month window.
- `assessment-participation-item.yaml`: add `rubrics` under `feedback` (→ new feedback schema);
  ensure both `rubric` references are `deprecated`.

## Testing

- The pure row→structure reshaping logic is extracted into `private static` methods and unit-tested
  with fixture rows (via reflection, matching the `MapperProxyRubricPersistenceTest` style). This
  covers grouping-by-instance, title/weight, descriptor handling, and the deprecated-singular shim
  without a database.
- View changes are verified directly against the DB (MariaDB 11.8.6, connect as `schoolboxdocker`):
  new columns present, `WHERE feedback_type = 'instructor'` no longer throws a collation error,
  row-count parity of the extended `v_rubric_mark` versus the old feedback query, and the existing
  `OverallDataset` markbook query still runs.
- **Dev-data caveat:** the current dev dataset has **no** assessment with 2+ `rubric_instance` rows
  and **zero** `reporting_feedback` rubric marks (all 880 marks are `submission_return`). To exercise
  the two headline behaviours, seed a second `rubric_instance` (multi-rubric) and, ideally, a
  reporting rubric mark before live-testing; otherwise those paths are covered only by the unit tests
  and `EXPLAIN`/parity checks.
- Live verification against the dev instance using the provided JWT:
  - `GET /api/assessment/{id}` (and the list endpoint) on a seeded 2+ rubric assessment → titled
    `rubrics[]`, per-rubric `weight`, deprecated `rubric == rubrics[0]` (capabilities only).
  - Single-rubric due-work assessment → payload matches the pre-change shape (regression).
  - Reporting assessment with a rubric mark → feedback `rubrics[]` now populated (previously dropped).
  - Assessment with no rubric → `rubrics: []`, `rubric: null`.

## Out of scope

- Write/modify paths for rubrics (create/update). This is read-API only.
- Frontend consumption of the new `rubrics` array.
- Removal of the deprecated singular `rubric` (separate ticket after the 6-month window).
- Normalising base-table column charsets (`utf8mb3` at rest). We only normalise the string columns
  the redefined views emit to `utf8mb4_unicode_ci`.
- Eliminating the pre-existing per-feedback N+1 (one `getRubricsFeedback` call per participation
  item). Batching it into a single `v_rubric_mark` read is a worthwhile follow-up but not required
  here; `EXPLAIN` shows each call is a cheap index-lookup.

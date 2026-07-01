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

`getRubric` selects `t.*` from `v_rubric`, so it picks these up automatically. `getRubricFeedback`
selects explicit columns and will reference the new ones as needed.

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

### 3. `getRubricFeedback` → `getRubricsFeedback` (rename)

- Same treatment: remove `submission_box` coupling, group by `rubric_instance_id`, return an array.
- Preserve existing filters, including `sr.review_route = 'instructor'`.
- Each element: `{ id, title, capabilities: [...] }` (marked capabilities unchanged in shape).
- Call sites (`Helper.php:694` and deprecated block `Helper.php:713`):
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

- No existing unit test covers these Helper methods (query-heavy). Primary verification is **live
  against the dev instance** (`docker.dev.schoolbox.cloud`) using the provided JWT:
  - `GET /api/assessment/{id}` on an assessment with **2+ rubric instances** → confirm titled
    `rubrics[]`, per-rubric `weight`, and a populated deprecated `rubric == rubrics[0]` (capabilities).
  - Same on the list endpoint.
  - A due-work assessment with a single rubric → payload matches the pre-change shape (regression).
  - An agnostic (e.g. reporting) assessment with a rubric → now returns rubrics (previously empty
    due to the `submission_box` join).
  - An assessment with no rubric → `rubrics: []`, `rubric: null`.
- Add a focused regression test where a seedable path exists, following
  `library/Schoolbox/Learning/Assessment/Test/DataModel/MapperProxyRubricPersistenceTest.php`
  conventions (assert array shape and `rubric`/`rubrics[0]` equivalence).

## Out of scope

- Write/modify paths for rubrics (create/update). This is read-API only.
- Frontend consumption of the new `rubrics` array.
- Removal of the deprecated singular `rubric` (separate ticket after the 6-month window).

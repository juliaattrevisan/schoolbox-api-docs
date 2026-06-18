# SA-21382: Add Additional Fields to the Assessment API

## Overview

Add 4 new fields to both the GET (`/api/assessment/{id}`) and LIST (`/api/assessment`) endpoints to support increasingly complex integration use cases.

## Fields to Add

| Field | Source | Applicable Types | Null when |
|---|---|---|---|
| `specificationAssessmentId` | `assessment_specification_implementation.specification_assessment_id` | All | No specification link exists |
| `markVisibility` | `submission_box.mark_visibility` / `assess.mark_visibility` / `project.mark_visibility` / `lti_assessment.mark_visibility` | dueWork, quiz, project, LTI | lessonPlan, task, dialogAgent |
| `markType` | `submission_mark_type.slug` (via dueWork/quiz/project joins) | dueWork, quiz, project | lessonPlan, task, LTI, dialogAgent |
| `description` | `assessment.description` | All (**GET single only**) | No description set; never returned by the LIST endpoint |

> **`markVisibility` is a string ENUM**, not an integer. As of [SA-22007](https://github.com/alaress/schoolbox/pull/34805) the `mark_visibility` columns are `ENUM('staffOnly','staffAndStudent','staffAndParent','staffStudentAndParent')`. The API passes the raw column value through (nullable for LTI).
>
> **`description` is omitted from the LIST endpoint** (`/api/assessment`) to keep the list payload small. It is only fetched and returned for the single GET (`/api/assessment/{id}`).

## Implementation

### File: `library/Schoolbox/Learning/Assessment/API/Helper.php`

#### `buildSearch()` — new JOINs in `$from`

```sql
LEFT JOIN assessment_specification_implementation AS asi ON asi.implementation_assessment_id = assessment.id
LEFT JOIN submission_mark_type AS quizMarkType ON assess.mark_type_id = quizMarkType.mark_type_id
LEFT JOIN submission_mark_type AS projectMarkType ON project.mark_type_id = projectMarkType.mark_type_id
```

#### `buildSearch()` — new columns in `$select`

```sql
assessment.description,
asi.specification_assessment_id AS specificationAssessmentId,
COALESCE(submission_box.mark_visibility, assess.mark_visibility, project.mark_visibility, lti_assessment.mark_visibility) AS markVisibility,
COALESCE(markTypeTable.slug, quizMarkType.slug, projectMarkType.slug) AS markType
```

The existing `markTypeTable.hr_name AS markType` is replaced by the COALESCE above. The existing `markTypeTable` JOIN remains but references `slug` instead of `hr_name`.

#### `searchForAssessments()` — output array additions

```php
'specificationAssessmentId' => $row['specificationAssessmentId'] !== null ? (int)$row['specificationAssessmentId'] : null,
'markVisibility'            => $row['markVisibility'] ?? null, // ENUM string, passed through (nullable for LTI)
'markType'                  => $row['markType'] ?? null,
// description added separately, only when $includeDescription is true (single GET)
```

`description` is selected and emitted only when `$includeDescription` is `true`. `searchForAssessments()` and `buildSearch()` take a `bool $includeDescription = false` parameter; `getAssessment()` (the single GET path) passes `true`, while the LIST endpoint uses the default `false`.

### File: `openapi/components/schemas/assessment-item.yaml` (schoolbox-api-docs repo)

Add 4 new properties to the schema:

**`specificationAssessmentId`** — integer, nullable. The ID of the specification assessment this was created from. Enables identifying common assessments across classes.

**`markVisibility`** — string, nullable, enum `[staffOnly, staffAndStudent, staffAndParent, staffStudentAndParent]`.
- `staffOnly`: Staff Only
- `staffAndStudent`: Staff & Student
- `staffAndParent`: Staff & Parents
- `staffStudentAndParent`: Staff, Student & Parent

Applicable to dueWork, quiz, project, LTI. Null for other types.

**`markType`** — string, nullable, enum `[percent, letter, raw, free, rubric, none, completion]`. The type of mark used. Applicable to dueWork, quiz, project. Null for other types.

**`description`** — string, nullable. A longer description of the assessment.

## Testing

The existing `ApiAssessmentCest.php` validates responses against `assessment-item.yaml`. Since new fields are nullable, existing schema validation tests will cover them automatically. Verify that at least one dueWork assessment in the test data returns non-null values for `markVisibility` and `markType` after changes.

## PR Strategy

**PR 1 — `schoolbox` repo** (branch: `task-develop/SA-21382` → `develop`)
- `Helper::buildSearch()` — new JOINs + SELECT columns
- `Helper::searchForAssessments()` — 4 new output fields
- Verify `ApiAssessmentCest` passes

**PR 2 — `schoolbox-api-docs` repo** (new branch: `task-develop/SA-21382` → `develop`)
- `openapi/components/schemas/assessment-item.yaml` — 4 new properties
- Open alongside PR 1, merge after PR 1 is deployed

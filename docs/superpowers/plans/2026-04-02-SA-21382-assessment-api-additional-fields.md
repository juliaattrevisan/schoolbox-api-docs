# SA-21382: Add Additional Fields to Assessment API — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `specificationAssessmentId`, `markVisibility`, `markType`, and `description` to both the GET and LIST assessment API endpoints.

**Architecture:** Extend `Helper::buildSearch()` with new SQL SELECT columns and JOINs, then output the new fields in `Helper::searchForAssessments()`. Update the OpenAPI schema in the `schoolbox-api-docs` repo first to drive TDD — the existing `checkSchema` test asserts every schema property exists in the response, so adding fields to the schema causes immediate test failure.

**Tech Stack:** PHP, MySQL/PDO, OpenAPI YAML, Codeception

---

## File Map

| File | Change |
|---|---|
| `/home/james/Sites/schoolbox-api-docs/openapi/components/schemas/assessment-item.yaml` | Add 4 new nullable properties |
| `library/Schoolbox/Learning/Assessment/API/Helper.php` | Extend `buildSearch()` SELECT + FROM; extend `searchForAssessments()` output array |

---

## Task 1: Create feature branch in schoolbox-api-docs and update schema

**Files:**
- Modify: `/home/james/Sites/schoolbox-api-docs/openapi/components/schemas/assessment-item.yaml`

- [ ] **Step 1: Create feature branch in schoolbox-api-docs**

```bash
cd /home/james/Sites/schoolbox-api-docs
git checkout develop
git pull
git checkout -b task-develop/SA-21382
```

- [ ] **Step 2: Add 4 new properties to `assessment-item.yaml`**

Append the following after the `participants` property (after line 110, before the final blank line):

```yaml
  specificationAssessmentId:
    type: integer
    nullable: true
    description: |
      The ID of the specification assessment that this assessment was created from.
      Enables identifying common assessments across classes.
      Null if this assessment has no specification link.
    example: 42
  markVisibility:
    type: integer
    nullable: true
    enum: [0, 1, 2]
    description: |
      Controls who can view the mark for this assessment.
      - `0`: Staff Only
      - `1`: Staff & Student
      - `2`: Staff, Student & Parent
      Applicable to assessmentTypes 'dueWork', 'quiz', 'project', and 'LTI' (null for other types).
    example: 1
  markType:
    type: string
    nullable: true
    enum:
      - percent
      - letter
      - raw
      - free
      - rubric
      - none
      - completion
    description: |
      The type of mark used for this assessment.
      Applicable to assessmentTypes 'dueWork', 'quiz', and 'project' (null for other types).
    example: percent
  description:
    type: string
    nullable: true
    description: |
      A longer description of the assessment. Null if no description has been set.
    example: "Write a 500 word essay on the causes of WW1."
```

- [ ] **Step 3: Copy updated schema to `/tmp` so the Codeception test can use it**

```bash
cp /home/james/Sites/schoolbox-api-docs/openapi/components/schemas/assessment-item.yaml \
   /tmp/schoolbox-api-docs/openapi/components/schemas/assessment-item.yaml
```

- [ ] **Step 4: Run the assessment test to confirm it now fails**

```bash
/usr/share/schoolbox/vendor/bin/codecept run api ApiAssessmentCest
```

Expected: FAIL — `Checked whether property specificationAssessmentId in document exists in response.` (and similar for the other 3 fields). This confirms TDD red phase.

- [ ] **Step 5: Commit the schema change**

```bash
cd /home/james/Sites/schoolbox-api-docs
git add openapi/components/schemas/assessment-item.yaml
git commit -m "Add specificationAssessmentId, markVisibility, markType, description to assessment schema [SA-21382]"
```

---

## Task 2: Update `buildSearch()` — extend SELECT

**Files:**
- Modify: `library/Schoolbox/Learning/Assessment/API/Helper.php:266`

- [ ] **Step 1: Replace the existing `markType` SELECT line and add new columns**

At line 266, replace:

```php
                markTypeTable.hr_name AS markType,
                projectParent.assessment_id AS `project`
```

With:

```php
                assessment.description,
                asi.specification_assessment_id AS specificationAssessmentId,
                COALESCE(submission_box.mark_visibility, assess.mark_visibility, project.mark_visibility, lti_assessment.mark_visibility) AS markVisibility,
                COALESCE(markTypeTable.slug, quizMarkType.slug, projectMarkType.slug) AS markType,
                projectParent.assessment_id AS `project`
```

---

## Task 3: Update `buildSearch()` — extend FROM with new JOINs

**Files:**
- Modify: `library/Schoolbox/Learning/Assessment/API/Helper.php:280`

- [ ] **Step 1: Add 3 new JOINs after the existing `markTypeTable` JOIN**

At line 280, the existing JOIN is:

```php
            LEFT JOIN submission_mark_type AS markTypeTable ON submission_box.mark_type = markTypeTable.mark_type_id
```

After that line, add:

```php
            LEFT JOIN assessment_specification_implementation AS asi ON asi.implementation_assessment_id = assessment.id
            LEFT JOIN submission_mark_type AS quizMarkType ON assess.mark_type_id = quizMarkType.mark_type_id
            LEFT JOIN submission_mark_type AS projectMarkType ON project.mark_type_id = projectMarkType.mark_type_id
```

---

## Task 4: Update `searchForAssessments()` — add new fields to output array

**Files:**
- Modify: `library/Schoolbox/Learning/Assessment/API/Helper.php:142-172`

- [ ] **Step 1: Add 4 new fields to the `$output[$assessmentId]` array**

The output array currently ends with:

```php
                    'rubric'           => $this->getRubric($assessmentId),
                    'participants'     => (
                        // Participants is not applicable to 'lessonPlan'.
                        (AssessmentType::TYPE_LESSON_PLAN === $row['assessmentType']) ? null : []
                    )
```

Add the 4 new fields after `'participants'`:

```php
                    'rubric'           => $this->getRubric($assessmentId),
                    'participants'     => (
                        // Participants is not applicable to 'lessonPlan'.
                        (AssessmentType::TYPE_LESSON_PLAN === $row['assessmentType']) ? null : []
                    ),
                    'specificationAssessmentId' => $row['specificationAssessmentId'] !== null ? (int)$row['specificationAssessmentId'] : null,
                    'markVisibility'            => $row['markVisibility'] !== null ? (int)$row['markVisibility'] : null,
                    'markType'                  => $row['markType'] ?? null,
                    'description'               => ($row['description'] !== null && $row['description'] !== '') ? $row['description'] : null,
```

---

## Task 5: Run tests, commit, and push both PRs

- [ ] **Step 1: Run the full assessment test suite**

```bash
/usr/share/schoolbox/vendor/bin/codecept run api ApiAssessmentCest
```

Expected: All tests pass. Verify in the output that `markVisibility` and `markType` are non-null for the dueWork assessment used in the test.

- [ ] **Step 2: Commit the PHP changes in the schoolbox repo**

```bash
git add library/Schoolbox/Learning/Assessment/API/Helper.php
git commit -m "Add specificationAssessmentId, markVisibility, markType, description to assessment API [SA-21382]"
```

- [ ] **Step 3: Push the schoolbox branch and open PR 1**

```bash
git push -u origin task-develop/SA-21382
```

Open a PR targeting `develop` in the `schoolbox` repo.

- [ ] **Step 4: Push the schoolbox-api-docs branch and open PR 2**

```bash
cd /home/james/Sites/schoolbox-api-docs
git push -u origin task-develop/SA-21382
```

Open a PR targeting `develop` in the `schoolbox-api-docs` repo.

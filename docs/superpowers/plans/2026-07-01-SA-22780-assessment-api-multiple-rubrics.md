# Assessment API: Multiple Titled Rubrics — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Assessment read API (`GET /api/assessment` and `GET /api/assessment/{id}`) return a set of titled rubrics per assessment (and per feedback item) instead of a single rubric, decoupled from due work, while keeping the old singular `rubric` field as a deprecated alias.

**Architecture:** Both rubric payloads are produced in `library/Schoolbox/Learning/Assessment/API/Helper.php`. The migrated schema already links `rubric_instance` to `assessment_id` (many-per-assessment) and the `v_rubric` view already joins it. We (1) expose the instance `title`/`weight` through `v_rubric`, (2) extract the flat-rows→nested-structure reshaping into pure, unit-testable methods that group by `rubric_instance_id`, and (3) rewire the two producing methods and their call sites to emit a `rubrics` array plus a deprecated singular `rubric`.

**Tech Stack:** PHP 8 (no `declare(strict_types=1)` in `Helper.php`), PDO, MariaDB 11.8 views (`condition_pushdown_for_derived=on`), PHPUnit 12, Redocly OpenAPI docs (separate repo `schoolbox-api-docs`).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-01-SA-22780-assessment-api-multiple-rubrics-design.md`.
- Ticket tag for commits: `[SA-22780]`.
- **Non-breaking contract:** always emit `rubrics` (an array, `[]` when none). Keep singular `rubric` populated as `rubrics[0]` reduced to `{capabilities: [...]}`, or `null` when there are no rubrics. The singular is a 6-month deprecation shim and is intentionally lossy for multi-rubric assessments.
- Template rubric item shape: `{ id, title, weight, capabilities: [...] }`. Feedback rubric item shape: `{ id, title, capabilities: [...] }` (no `weight`).
- Feedback is now **agnostic**: it reads the `v_rubric_mark` view, which unions `submission_return` (due work) + `reporting_feedback` (reporting) rubric marks. Preserve instructor-only behaviour via `feedback_type = 'instructor'` (this replaces the old `review_route='instructor'` + hardcoded `feedback_table='submission_return'`).
- **Collation:** every string column the redefined views emit must be `utf8mb4_unicode_ci`. Normalise string literals with `CONVERT(<expr> USING utf8mb4) COLLATE utf8mb4_unicode_ci` (base-table charset normalisation is out of scope).
- `Helper.php` has **no** `declare(strict_types=1)`; int→string arg coercion at call sites is intentional — do not add strict types.
- PHP test command: `vendor/bin/phpunit <path-to-test-file>` from the repo root.
- DB access for view verification: `mariadb -u schoolboxdocker -pabc123 -h 127.0.0.1 schoolbox` (MariaDB 11.8.6).
- Docs lint command: `npm test` (runs `redocly lint`) from the `schoolbox-api-docs` repo root.

---

## File Structure

**`schoolbox` repo:**
- Create: `updates/26.1.beta7/rubric/16-v_rubric.sql` — redefines the `v_rubric` view to also expose `rubric_instance.title` and `rubric_instance.weight`.
- Create: `updates/26.1.beta7/rubric/17-v_rubric_mark.sql` — redefines the `v_rubric_mark` view to add `rubric_instance_title`, `descriptor_max_value`/`descriptor_sequence`, and normalise `feedback_type` to `utf8mb4_unicode_ci`.
- Modify: `library/Schoolbox/Learning/Assessment/API/Helper.php` — rename + rewire the two rubric methods (template reads `v_rubric`, feedback reads `v_rubric_mark`), add two pure reshaping methods and one legacy-shim method, update three call sites.
- Create: `library/Schoolbox/Learning/Assessment/Test/API/RubricReshapeTest.php` — unit tests for the pure reshaping methods (via reflection, matching the module's existing test style).

**`schoolbox-api-docs` repo (`/home/james/Sites/schoolbox-api-docs`):**
- Create: `openapi/components/schemas/assessment-rubrics.yaml` — array of `{id,title,weight,capabilities}`.
- Create: `openapi/components/schemas/assessment-rubrics-feedback.yaml` — array of `{id,title,capabilities}`.
- Modify: `openapi/components/schemas/assessment-item.yaml` — add `rubrics`, deprecate `rubric`.
- Modify: `openapi/components/schemas/assessment-participation-item.yaml` — add `rubrics` under `feedback`, ensure both `rubric` refs are deprecated.

---

## Task 1: Expose rubric instance title & weight through `v_rubric`

**Files:**
- Create: `updates/26.1.beta7/rubric/16-v_rubric.sql`

**Interfaces:**
- Produces: `v_rubric` columns `rubric_instance_title` (varchar, nullable) and `rubric_instance_weight` (decimal, nullable), present in **both** UNION arms. Task 2 (`v_rubric_mark`) and Task 3 (template rubrics) read these.

This is a `CREATE OR REPLACE VIEW` migration copied from `updates/26.1.beta4/rubric/16-v_rubric.sql` with two extra selected columns in each arm. There is no PHP test; verification is by applying it and querying the view.

- [ ] **Step 1: Create the migration file**

Create `updates/26.1.beta7/rubric/16-v_rubric.sql` with this exact content:

```sql
-- Expose rubric_instance.title and rubric_instance.weight so the Assessment API can return a set
-- of titled, weighted rubrics per assessment (SA-22780). Otherwise identical to the beta4 definition.

CREATE OR REPLACE VIEW v_rubric AS
SELECT *
FROM
    (
        SELECT
            a.id AS assessment_id,
            a.title AS assessment_title,
            sb.mark_type AS assessment_mark_type,
            sb.max_mark AS assessment_max_mark,
            sb.submission_grade_group_id AS assessment_grade_group_id,
            a.weight AS assessment_weight,
            rubric_instance.id AS rubric_instance_id,
            rubric_instance.title AS rubric_instance_title,
            rubric_instance.weight AS rubric_instance_weight,
            rubric_instance.rubric_capability_mark_type_id AS capability_mark_type_id,
            rubric_instance.rubric_capability_grade_group_id AS capability_grade_group_id,
            rc.id AS capability_id,
            rc.name AS capability_name,
            rcu.max_value AS capability_max_value,
            rcu.sequence AS capability_sequence,
            ri.id AS indicator_id,
            ri.name AS indicator_name,
            riu.sequence AS indicator_sequence,
            riu.max_value AS indicator_max_value
        FROM assessment a
        INNER JOIN submission_box sb ON a.id = sb.assessment_id
        INNER JOIN rubric_instance ON a.id = rubric_instance.assessment_id
        INNER JOIN rubric_capability_usage rcu ON rcu.rubric_instance_id = rubric_instance.id
        INNER JOIN rubric_capability rc ON rc.id = rcu.rubric_capability_id
        INNER JOIN rubric_indicator_usage riu ON riu.rubric_instance_id = rubric_instance.id
        INNER JOIN rubric_indicator ri ON ri.id = riu.rubric_indicator_id
        WHERE a.deleted_at IS NULL
            AND rc.deleted_at IS NULL
            AND ri.deleted_at IS NULL
            AND ri.rubric_capability_id = rc.id
            AND rubric_instance.deleted_at IS NULL

        UNION

        SELECT
            a.id AS assessment_id,
            a.title AS assessment_title,
            r.mark_type AS assessment_mark_type,
            r.max_mark AS assessment_max_mark,
            r.grade_group_id AS assessment_grade_group_id,
            a.weight AS assessment_weight,
            rubric_instance.id AS rubric_instance_id,
            rubric_instance.title AS rubric_instance_title,
            rubric_instance.weight AS rubric_instance_weight,
            rubric_instance.rubric_capability_mark_type_id AS capability_mark_type_id,
            rubric_instance.rubric_capability_grade_group_id AS capability_grade_group_id,
            rc.id AS capability_id,
            rc.name AS capability_name,
            rcu.max_value AS capability_max_value,
            rcu.sequence AS capability_sequence,
            ri.id AS indicator_id,
            ri.name AS indicator_name,
            riu.sequence AS indicator_sequence,
            riu.max_value AS indicator_max_value
        FROM assessment a
        INNER JOIN reporting r ON a.id = r.assessment_id
        INNER JOIN rubric_instance ON a.id = rubric_instance.assessment_id
        INNER JOIN rubric_capability_usage rcu ON rcu.rubric_instance_id = rubric_instance.id
        INNER JOIN rubric_capability rc ON rc.id = rcu.rubric_capability_id
        INNER JOIN rubric_indicator_usage riu ON riu.rubric_instance_id = rubric_instance.id
        INNER JOIN rubric_indicator ri ON ri.id = riu.rubric_indicator_id
        WHERE a.deleted_at IS NULL
            AND rc.deleted_at IS NULL
            AND ri.deleted_at IS NULL
            AND ri.rubric_capability_id = rc.id
            AND rubric_instance.deleted_at IS NULL
    ) rubricSummary
ORDER BY assessment_id, rubric_instance_id, capability_sequence, capability_id, indicator_sequence, indicator_id;
```

- [ ] **Step 2: Apply the view to the dev database**

Run:

```bash
mariadb -u schoolboxdocker -pabc123 -h 127.0.0.1 schoolbox < updates/26.1.beta7/rubric/16-v_rubric.sql
```

Expected: no error.

- [ ] **Step 3: Verify the new columns exist and populate**

Run:

```bash
mariadb -u schoolboxdocker -pabc123 -h 127.0.0.1 schoolbox -e "SELECT DISTINCT rubric_instance_id, rubric_instance_title, rubric_instance_weight FROM v_rubric LIMIT 5;"
```

Expected: rows returned including non-null `rubric_instance_title` values (given seeded rubric data). No "Unknown column" error.

- [ ] **Step 4: Commit**

```bash
git add updates/26.1.beta7/rubric/16-v_rubric.sql
git commit -m "feat: expose rubric instance title & weight via v_rubric [SA-22780]"
```

---

## Task 2: Redefine `v_rubric_mark` (title, descriptor columns, collation)

**Files:**
- Create: `updates/26.1.beta7/rubric/17-v_rubric_mark.sql`

**Interfaces:**
- Consumes: `v_rubric.rubric_instance_title` from Task 1.
- Produces: `v_rubric_mark` gains `rubric_instance_title`, `descriptor_max_value`, `descriptor_sequence`, and a `feedback_type` normalised to `utf8mb4_unicode_ci`. Task 4 (`getRubricsFeedback`) reads these.

This is a `CREATE OR REPLACE VIEW` migration based on `updates/26.1.beta4/rubric/17-v_rubric_mark.sql` with three changes in **both** UNION arms: (1) select `vr.rubric_instance_title`, (2) `LEFT JOIN rubric_descriptor` + `rubric_level` to expose `descriptor_max_value`/`descriptor_sequence`, (3) wrap `feedback_type` in `CONVERT(... USING utf8mb4) COLLATE utf8mb4_unicode_ci` to fix the mixed-collation error. The `INNER JOIN v_rubric_indicator_mark_extend` is left unchanged (shared with the markbook consumer; row parity verified). No PHP test; verified by querying the view.

- [ ] **Step 1: Create the migration file**

Create `updates/26.1.beta7/rubric/17-v_rubric_mark.sql` with this exact content:

```sql
-- Extend v_rubric_mark for the agnostic Assessment feedback API (SA-22780):
--  * expose rubric_instance_title (from v_rubric)
--  * expose descriptor_max_value / descriptor_sequence (LEFT JOIN rubric_descriptor + rubric_level)
--  * normalise feedback_type to utf8mb4_unicode_ci (the reporting arm's 'instructor' literal was
--    created under a utf8mb3 connection, causing "Illegal mix of collations" on WHERE feedback_type=...)
-- The INNER JOIN to v_rubric_indicator_mark_extend is unchanged (shared with the rubric markbook).

CREATE OR REPLACE VIEW v_rubric_mark AS
SELECT *
FROM
    (
        SELECT DISTINCT
            vr.assessment_id AS assessment_id,
            rcm.feedback_id AS feedback_id,
            rcm.feedback_table AS feedback_table,
            sr.owner AS student_id,
            sr.marker AS marker_id,
            sr.published_at AS feedback_date,
            CONVERT(sr.review_route USING utf8mb4) COLLATE utf8mb4_unicode_ci AS feedback_type,
            vr.rubric_instance_id AS rubric_instance_id,
            vr.rubric_instance_title AS rubric_instance_title,
            vr.capability_mark_type_id AS capability_mark_type_id,
            vr.capability_grade_group_id AS capability_grade_group_id,
            vr.capability_id AS capability_id,
            vr.capability_name AS capability_name,
            vr.capability_max_value AS capability_max_value,
            IF (rcm.valid, rcm.value, NULL) AS capability_mark,
            IF (rcm.valid, rcm.human_readable_value, NULL) AS capability_human_readable_mark,
            vr.capability_sequence AS capability_sequence,
            vr.indicator_id AS indicator_id,
            vr.indicator_name AS indicator_name,
            vr.indicator_max_value AS indicator_max_value,
            IF (rim.valid, rim.value, NULL) AS indicator_mark,
            vr.indicator_sequence AS indicator_sequence,
            rim.descriptor_id AS descriptor_id,
            rim.descriptor_name AS descriptor_name,
            rd.max_value AS descriptor_max_value,
            rl.sequence AS descriptor_sequence
        FROM v_rubric vr
        INNER JOIN submission_box sb ON vr.assessment_id = sb.assessment_id
        INNER JOIN submission_return sr ON sb.id = sr.box_id
        INNER JOIN rubric_capability_mark rcm ON vr.rubric_instance_id = rcm.rubric_instance_id
            AND rcm.rubric_capability_id = vr.capability_id
            AND rcm.feedback_id = sr.id
            AND rcm.feedback_table = 'submission_return'
        INNER JOIN v_rubric_indicator_mark_extend rim ON rim.rubric_capability_mark_id = rcm.id
            AND vr.indicator_id = rim.rubric_indicator_id
        LEFT JOIN rubric_descriptor rd ON rim.descriptor_id = rd.id AND rd.deleted_at IS NULL
        LEFT JOIN rubric_level rl ON rd.level_id = rl.id
            AND vr.rubric_instance_id = rl.rubric_instance_id
            AND rl.deleted_at IS NULL
        WHERE sr.deleted_at IS NULL
          AND sr.published_at IS NOT NULL
          AND (rcm.valid IS TRUE OR rim.valid IS TRUE)

        UNION

        SELECT DISTINCT
            vr.assessment_id AS assessment_id,
            rcm.feedback_id AS feedback_id,
            rcm.feedback_table AS feedback_table,
            rf.learner_id AS student_id,
            rf.instructor_id AS marker_id,
            rf.published_at AS feedback_date,
            CONVERT('instructor' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS feedback_type,
            vr.rubric_instance_id AS rubric_instance_id,
            vr.rubric_instance_title AS rubric_instance_title,
            vr.capability_mark_type_id AS capability_mark_type_id,
            vr.capability_grade_group_id AS capability_grade_group_id,
            vr.capability_id AS capability_id,
            vr.capability_name AS capability_name,
            vr.capability_max_value AS capability_max_value,
            IF (rcm.valid, rcm.value, NULL) AS capability_mark,
            IF (rcm.valid, rcm.human_readable_value, NULL) AS capability_human_readable_mark,
            vr.capability_sequence AS capability_sequence,
            vr.indicator_id AS indicator_id,
            vr.indicator_name AS indicator_name,
            vr.indicator_max_value AS indicator_max_value,
            IF (rim.valid, rim.value, NULL) AS indicator_mark,
            vr.indicator_sequence AS indicator_sequence,
            rim.descriptor_id AS descriptor_id,
            rim.descriptor_name AS descriptor_name,
            rd.max_value AS descriptor_max_value,
            rl.sequence AS descriptor_sequence
        FROM v_rubric vr
        INNER JOIN reporting r ON vr.assessment_id = r.assessment_id
        INNER JOIN reporting_feedback rf ON r.id = rf.reporting_id
        INNER JOIN rubric_capability_mark rcm ON vr.rubric_instance_id = rcm.rubric_instance_id
            AND rcm.rubric_capability_id = vr.capability_id
            AND rcm.feedback_id = rf.id
            AND rcm.feedback_table = 'reporting_feedback'
        INNER JOIN v_rubric_indicator_mark_extend rim ON rim.rubric_capability_mark_id = rcm.id
            AND vr.indicator_id = rim.rubric_indicator_id
        LEFT JOIN rubric_descriptor rd ON rim.descriptor_id = rd.id AND rd.deleted_at IS NULL
        LEFT JOIN rubric_level rl ON rd.level_id = rl.id
            AND vr.rubric_instance_id = rl.rubric_instance_id
            AND rl.deleted_at IS NULL
        WHERE rf.deleted_at IS NULL
          AND rf.published_at IS NOT NULL
          AND (rcm.valid IS TRUE OR rim.valid IS TRUE)
) rubricSummary
ORDER BY assessment_id, student_id, feedback_date, capability_sequence, capability_id, indicator_sequence, indicator_id;
```

- [ ] **Step 2: Apply the view (Task 1 must already be applied so `v_rubric.rubric_instance_title` exists)**

Run:

```bash
mariadb -u schoolboxdocker -pabc123 -h 127.0.0.1 schoolbox < updates/26.1.beta7/rubric/17-v_rubric_mark.sql
```

Expected: no error. (If it errors with "Unknown column 'vr.rubric_instance_title'", Task 1's migration has not been applied yet — apply it first.)

- [ ] **Step 3: Verify the new columns exist**

Run:

```bash
mariadb -u schoolboxdocker -pabc123 -h 127.0.0.1 schoolbox -N -e \
  "SELECT rubric_instance_title, descriptor_max_value, descriptor_sequence FROM v_rubric_mark LIMIT 3;"
```

Expected: rows returned, no "Unknown column" error.

- [ ] **Step 4: Verify the collation fix — filtering feedback_type no longer errors**

Run:

```bash
mariadb -u schoolboxdocker -pabc123 -h 127.0.0.1 schoolbox -N -e \
  "SELECT COUNT(*) FROM v_rubric_mark WHERE feedback_type = 'instructor';"
```

Expected: a number (e.g. hundreds), **not** `ERROR 1267 ... Illegal mix of collations`.

- [ ] **Step 5: Verify row-count parity with the old feedback query (no rows lost/gained)**

Pick an assessment+feedback that has marks (e.g. from `SELECT assessment_id, feedback_id, feedback_table FROM v_rubric_mark LIMIT 1;`) and compare counts:

```bash
mariadb -u schoolboxdocker -pabc123 -h 127.0.0.1 schoolbox -N -e "
SELECT (SELECT COUNT(*) FROM v_rubric_mark WHERE assessment_id=38 AND feedback_id=34 AND feedback_table='submission_return') AS new_rows,
       (SELECT COUNT(*) FROM v_rubric vr
          JOIN submission_box sb ON vr.assessment_id=sb.assessment_id
          JOIN submission_return sr ON sb.id=sr.box_id
          JOIN rubric_capability_mark rcm ON rcm.feedback_table='submission_return' AND rcm.feedback_id=sr.id AND rcm.rubric_instance_id=vr.rubric_instance_id AND rcm.rubric_capability_id=vr.capability_id
          LEFT JOIN v_rubric_indicator_mark_extend rim ON rim.rubric_indicator_id=vr.indicator_id AND rcm.id=rim.rubric_capability_mark_id
          WHERE sr.deleted_at IS NULL AND sr.published_at IS NOT NULL AND (rcm.valid IS TRUE OR rim.valid IS TRUE) AND sb.assessment_id=38 AND sr.id=34) AS old_rows;"
```

Expected: `new_rows` == `old_rows` (adjust the ids to a real marked feedback in your DB).

- [ ] **Step 6: Verify the existing markbook consumer still runs**

The other consumer of this view is `library/Schoolbox/Assessment/Rubric/Markbook/OverallDataset.php`. Sanity-check the view is still selectable the way that code joins it:

```bash
mariadb -u schoolboxdocker -pabc123 -h 127.0.0.1 schoolbox -N -e \
  "SELECT COUNT(*) FROM v_rubric_mark vrm WHERE vrm.assessment_id IN (38);"
```

Expected: a number, no error.

- [ ] **Step 7: Commit**

```bash
git add updates/26.1.beta7/rubric/17-v_rubric_mark.sql
git commit -m "feat: extend v_rubric_mark with title, descriptor detail, utf8mb4 collation [SA-22780]"
```

---

## Task 3: Template rubrics — `getRubric` → `getRubrics` (array of titled rubrics)

**Files:**
- Modify: `library/Schoolbox/Learning/Assessment/API/Helper.php` (method at 1009, call site at 170)
- Test: `library/Schoolbox/Learning/Assessment/Test/API/RubricReshapeTest.php`

**Interfaces:**
- Produces:
  - `Helper::buildTemplateRubrics(array $rows): array` (private static) — flat `v_rubric` rows → `[{id:int, title:string, weight:float|null, capabilities:[{id,name,maxValue,indicators:[{id,name,maxValue,descriptors:[{id,description,maxValue,sequence}]}]}]}]`. Returns `[]` for `[]`.
  - `Helper::firstRubricAsLegacy(array $rubrics): ?array` (private static) — `rubrics[0]` reduced to `{capabilities: [...]}`, or `null`. Shared with Task 4.
  - `Helper::getRubrics(string $assessmentId): array` — runs the template query and delegates to `buildTemplateRubrics`.
- Consumes: `v_rubric.rubric_instance_id/title/weight` from Task 1.

- [ ] **Step 1: Write the failing test**

Create `library/Schoolbox/Learning/Assessment/Test/API/RubricReshapeTest.php`:

```php
<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Test\API;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Schoolbox\Learning\Assessment\API\Helper;

#[CoversClass(Helper::class)]
class RubricReshapeTest extends TestCase
{
    public function testBuildTemplateRubricsGroupsRowsByInstanceWithTitleAndWeight(): void
    {
        $rows = [
            $this->templateRow(1, 'Written', '60.00', 10, 'Skills', 100, 'Communication', 1000, 'Developing', 4),
            $this->templateRow(1, 'Written', '60.00', 10, 'Skills', 100, 'Communication', 1001, 'Proficient', 5),
            $this->templateRow(2, 'Oral', '40.00', 20, 'Speaking', 200, 'Clarity', 2000, 'Clear', 3),
        ];

        $rubrics = $this->invokeStatic('buildTemplateRubrics', $rows);

        $this->assertCount(2, $rubrics);
        $this->assertSame(1, $rubrics[0]['id']);
        $this->assertSame('Written', $rubrics[0]['title']);
        $this->assertSame(60.0, $rubrics[0]['weight']);
        $this->assertCount(1, $rubrics[0]['capabilities']);
        $this->assertSame(10, $rubrics[0]['capabilities'][0]['id']);
        $this->assertCount(2, $rubrics[0]['capabilities'][0]['indicators'][0]['descriptors']);
        $this->assertSame('Oral', $rubrics[1]['title']);
        $this->assertSame(40.0, $rubrics[1]['weight']);
    }

    public function testBuildTemplateRubricsReturnsEmptyArrayForNoRows(): void
    {
        $this->assertSame([], $this->invokeStatic('buildTemplateRubrics', []));
    }

    public function testFirstRubricAsLegacyReturnsFirstCapabilitiesOnly(): void
    {
        $rubrics = [
            ['id' => 1, 'title' => 'Written', 'weight' => 60.0, 'capabilities' => [['id' => 10]]],
            ['id' => 2, 'title' => 'Oral', 'weight' => 40.0, 'capabilities' => [['id' => 20]]],
        ];

        $this->assertSame(['capabilities' => [['id' => 10]]], $this->invokeStatic('firstRubricAsLegacy', $rubrics));
    }

    public function testFirstRubricAsLegacyReturnsNullForEmpty(): void
    {
        $this->assertNull($this->invokeStatic('firstRubricAsLegacy', []));
    }

    /**
     * Build a single flat v_rubric template row with all keys buildTemplateRubrics reads.
     */
    private function templateRow(
        int $instanceId,
        string $instanceTitle,
        string $instanceWeight,
        int $capabilityId,
        string $capabilityName,
        int $indicatorId,
        string $indicatorName,
        int $descriptorId,
        string $descriptorDescription,
        int $descriptorSequence,
    ): array {
        return [
            'rubric_instance_id'     => $instanceId,
            'rubric_instance_title'  => $instanceTitle,
            'rubric_instance_weight' => $instanceWeight,
            'capability_id'          => $capabilityId,
            'capability_name'        => $capabilityName,
            'capability_max_value'   => '0',
            'indicator_id'           => $indicatorId,
            'indicator_name'         => $indicatorName,
            'indicator_max_value'    => '0',
            'descriptor_id'          => $descriptorId,
            'descriptor_descriptor'  => $descriptorDescription,
            'descriptor_max_value'   => '0',
            'descriptor_sequence'    => $descriptorSequence,
        ];
    }

    private function invokeStatic(string $method, array $arg): mixed
    {
        $reflection = new ReflectionMethod(Helper::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $arg);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit library/Schoolbox/Learning/Assessment/Test/API/RubricReshapeTest.php`
Expected: FAIL — `ReflectionException: Method Schoolbox\Learning\Assessment\API\Helper::buildTemplateRubrics() does not exist`.

- [ ] **Step 3: Add the two static reshaping methods**

In `library/Schoolbox/Learning/Assessment/API/Helper.php`, replace the entire `getRubric` method (currently lines 1004–1073, the docblock `get full rubric template` through its closing `}`) with the following three methods:

```php
    /**
     * Get all rubric templates attached to an assessment.
     *
     * Returns a list of rubrics (each with its own title and weight). Empty when the assessment has
     * no rubric instances. Decoupled from submission_box so it works for any assessment type.
     *
     * @param string $assessmentId
     * @return array<int, array>
     */
    public function getRubrics(string $assessmentId): array
    {
        $statement = $this->mapper->getDatabase()->prepare("
        SELECT
            rd.id as `descriptor_id`,
            rd.max_value as `descriptor_max_value`,
            rd.description as `descriptor_descriptor`,
            rl.sequence as `descriptor_sequence`,
            t.*
        FROM v_rubric t
                 INNER JOIN rubric_descriptor rd ON (t.indicator_id = rd.rubric_indicator_id and rd.deleted_at is null)
                 INNER JOIN rubric_level rl on (
                    rd.level_id = rl.id
                    and t.rubric_instance_id = rl.rubric_instance_id
                    and rl.deleted_at is null
                 )
        WHERE t.assessment_id = :assessmentID
        ORDER BY t.rubric_instance_id, t.capability_sequence, t.capability_id, t.indicator_sequence, t.indicator_id
        ");
        $statement->execute(['assessmentID' => $assessmentId]);

        return self::buildTemplateRubrics($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Reshape flat v_rubric template rows into a list of nested rubrics grouped by rubric instance.
     *
     * @param array<int, array> $rows
     * @return array<int, array>
     */
    private static function buildTemplateRubrics(array $rows): array
    {
        $rubrics = [];
        foreach ($rows as $row) {
            $instanceId = (int)$row['rubric_instance_id'];

            if (!isset($rubrics[$instanceId])) {
                $rubrics[$instanceId] = [
                    'id'           => $instanceId,
                    'title'        => $row['rubric_instance_title'],
                    'weight'       => (null === $row['rubric_instance_weight']) ? null : (float)$row['rubric_instance_weight'],
                    'capabilities' => [],
                ];
            }

            $capabilities = &$rubrics[$instanceId]['capabilities'];
            if (!isset($capabilities[$row['capability_id']])) {
                $capabilities[$row['capability_id']] = [
                    'id'         => (int)$row['capability_id'],
                    'name'       => $row['capability_name'],
                    'maxValue'   => (float)$row['capability_max_value'],
                    'indicators' => [],
                ];
            }

            $indicators = &$capabilities[$row['capability_id']]['indicators'];
            if (!isset($indicators[$row['indicator_id']])) {
                $indicators[$row['indicator_id']] = [
                    'id'          => (int)$row['indicator_id'],
                    'name'        => $row['indicator_name'],
                    'maxValue'    => (float)$row['indicator_max_value'],
                    'descriptors' => [],
                ];
            }

            $descriptors = &$indicators[$row['indicator_id']]['descriptors'];
            if (!isset($descriptors[$row['descriptor_id']])) {
                $descriptors[$row['descriptor_id']] = [
                    'id'          => (int)$row['descriptor_id'],
                    'description' => $row['descriptor_descriptor'],
                    'maxValue'    => (float)$row['descriptor_max_value'],
                    'sequence'    => (int)$row['descriptor_sequence'],
                ];
            }

            unset($capabilities, $indicators, $descriptors);
        }

        // Strip the associative keys used only for grouping.
        foreach ($rubrics as &$rubric) {
            foreach ($rubric['capabilities'] as &$capability) {
                foreach ($capability['indicators'] as &$indicator) {
                    $indicator['descriptors'] = array_values($indicator['descriptors']);
                }
                unset($indicator);
                $capability['indicators'] = array_values($capability['indicators']);
            }
            unset($capability);
            $rubric['capabilities'] = array_values($rubric['capabilities']);
        }
        unset($rubric);

        return array_values($rubrics);
    }

    /**
     * Reduce a list of rubrics to the deprecated singular `rubric` payload: the first rubric's
     * capabilities only (no id/title/weight), or null when there are no rubrics.
     *
     * @param array<int, array> $rubrics
     * @return array|null
     */
    private static function firstRubricAsLegacy(array $rubrics): ?array
    {
        if (!isset($rubrics[0])) {
            return null;
        }

        return ['capabilities' => $rubrics[0]['capabilities']];
    }
```

- [ ] **Step 4: Run the reshape test to verify it passes**

Run: `vendor/bin/phpunit library/Schoolbox/Learning/Assessment/Test/API/RubricReshapeTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Update the assessment call site to emit `rubrics` + deprecated `rubric`**

In `library/Schoolbox/Learning/Assessment/API/Helper.php`, inside `searchForAssessments`, the block that builds `$output[$assessmentId]` currently starts at the `if (!isset($output[$assessmentId])) {` guard (around line 145). Immediately after that `if (!isset($output[$assessmentId])) {` line and before the `$output[$assessmentId] = [` assignment, add:

```php
                $rubrics = $this->getRubrics((string)$assessmentId);
```

Then, within the `$output[$assessmentId] = [ ... ]` array literal, replace this single line (currently line 170):

```php
                    'rubric'           => $this->getRubric($assessmentId),
```

with these two lines:

```php
                    'rubric'           => self::firstRubricAsLegacy($rubrics),
                    'rubrics'          => $rubrics,
```

- [ ] **Step 6: Verify no other caller references the old method name**

Run: `grep -rn "getRubric(" library/ | grep -v getRubricFeedback`
Expected: only the definition is gone; **no** results referencing `->getRubric(` remain. (If any appear outside this file, update them to `getRubrics` — but per the pre-plan sweep there are none.)

- [ ] **Step 7: Run the reshape test again (regression) and commit**

Run: `vendor/bin/phpunit library/Schoolbox/Learning/Assessment/Test/API/RubricReshapeTest.php`
Expected: PASS.

```bash
git add library/Schoolbox/Learning/Assessment/API/Helper.php library/Schoolbox/Learning/Assessment/Test/API/RubricReshapeTest.php
git commit -m "feat: return a set of titled rubrics on the assessment [SA-22780]"
```

---

## Task 4: Feedback rubrics — `getRubricFeedback` → `getRubricsFeedback` (agnostic, via `v_rubric_mark`)

**Files:**
- Modify: `library/Schoolbox/Learning/Assessment/API/Helper.php` (method at 1082, call site at 677 / feedbackFields at 681)
- Test: `library/Schoolbox/Learning/Assessment/Test/API/RubricReshapeTest.php` (add cases)

**Interfaces:**
- Produces:
  - `Helper::buildFeedbackRubrics(array $rows): array` (private static) — flat feedback rows → `[{id:int, title:string, capabilities:[{id,name,value,maxValue,mark,indicators:[{id,name,maxValue,value,descriptor:null|{id,description,maxValue,sequence}}]}]}]`. Returns `[]` for `[]`.
  - `Helper::getRubricsFeedback(string $assessmentId, int $feedbackID, string $feedbackTable): array` — reads `v_rubric_mark` (agnostic across `submission_return` + `reporting_feedback`), filtered to `feedback_type = 'instructor'`.
- Consumes: `firstRubricAsLegacy` (Task 3), the extended `v_rubric_mark` (Task 2: `rubric_instance_title`, `descriptor_max_value`, `descriptor_sequence`, normalised `feedback_type`).

- [ ] **Step 1: Add the failing feedback reshape test**

In `library/Schoolbox/Learning/Assessment/Test/API/RubricReshapeTest.php`, add these two test methods (place them after `testBuildTemplateRubricsReturnsEmptyArrayForNoRows`) and the `feedbackRow` helper (place it after the `templateRow` helper):

```php
    public function testBuildFeedbackRubricsGroupsRowsByInstanceWithTitleAndMarks(): void
    {
        $rows = [
            $this->feedbackRow(1, 'Written', 10, 'Skills', '2', '3', 100, 'Communication', '2', 1000, 'Proficient', 5),
            $this->feedbackRow(2, 'Oral', 20, 'Speaking', '1', '2', 200, 'Clarity', '1', null, null, 0),
        ];

        $rubrics = $this->invokeStatic('buildFeedbackRubrics', $rows);

        $this->assertCount(2, $rubrics);
        $this->assertSame(1, $rubrics[0]['id']);
        $this->assertSame('Written', $rubrics[0]['title']);
        $this->assertArrayNotHasKey('weight', $rubrics[0]);
        $this->assertSame(2.0, $rubrics[0]['capabilities'][0]['value']);
        $this->assertSame('Proficient', $rubrics[0]['capabilities'][0]['indicators'][0]['descriptor']['description']);
        $this->assertNull($rubrics[1]['capabilities'][0]['indicators'][0]['descriptor']);
    }

    public function testBuildFeedbackRubricsReturnsEmptyArrayForNoRows(): void
    {
        $this->assertSame([], $this->invokeStatic('buildFeedbackRubrics', []));
    }
```

Add this helper (after `templateRow`):

```php
    /**
     * Build a single flat feedback rubric row with all keys buildFeedbackRubrics reads.
     * Pass $descriptorId = null (with $descriptorName = null) to represent an indicator with no descriptor.
     */
    private function feedbackRow(
        int $instanceId,
        string $instanceTitle,
        int $capabilityId,
        string $capabilityName,
        ?string $capabilityMark,
        ?string $capabilityMax,
        int $indicatorId,
        string $indicatorName,
        ?string $indicatorMark,
        ?int $descriptorId,
        ?string $descriptorName,
        int $descriptorSequence,
    ): array {
        return [
            'rubric_instance_id'             => $instanceId,
            'rubric_instance_title'          => $instanceTitle,
            'capability_id'                  => $capabilityId,
            'capability_name'                => $capabilityName,
            'capability_mark'                => $capabilityMark,
            'capability_human_readable_mark' => $capabilityMark,
            'capability_max_value'           => $capabilityMax,
            'indicator_id'                   => $indicatorId,
            'indicator_name'                 => $indicatorName,
            'indicator_max_value'            => '0',
            'indicator_mark'                 => $indicatorMark,
            'descriptor_id'                  => $descriptorId,
            'descriptor_name'                => $descriptorName,
            'descriptor_max_value'           => '0',
            'descriptor_sequence'            => $descriptorSequence,
        ];
    }
```

- [ ] **Step 2: Run the test to verify the new cases fail**

Run: `vendor/bin/phpunit library/Schoolbox/Learning/Assessment/Test/API/RubricReshapeTest.php`
Expected: FAIL — `Method ...::buildFeedbackRubrics() does not exist`.

- [ ] **Step 3: Add `buildFeedbackRubrics` and rework `getRubricFeedback` → `getRubricsFeedback`**

In `library/Schoolbox/Learning/Assessment/API/Helper.php`, replace the entire `getRubricFeedback` method (currently the docblock `get feedback rubric` at 1075 through its closing `}` at 1183) with the following two methods. The hand-rolled `submission_box`/`submission_return` join is gone — `getRubricsFeedback` now reads the agnostic `v_rubric_mark` view (Task 2), so it covers both due-work and reporting feedback and no longer couples to `submission_box`.

```php
    /**
     * Get all marked feedback rubrics for a single feedback record.
     *
     * Reads the agnostic v_rubric_mark view (submission_return + reporting_feedback marks), so it works
     * for any feedback source. Returns a list of rubrics (each with its own title); empty when the
     * feedback has no rubric marks. Restricted to instructor feedback, matching prior behaviour.
     *
     * @param string $assessmentId
     * @param int    $feedbackID
     * @param string $feedbackTable Source table for this feedback (e.g. 'submission_return',
     *                              'reporting_feedback'); disambiguates feedback_id across sources.
     * @return array<int, array>
     */
    public function getRubricsFeedback(string $assessmentId, int $feedbackID, string $feedbackTable): array
    {
        $statement = $this->mapper->getDatabase()->prepare("
            SELECT
                `rubric_instance_id`,
                `rubric_instance_title`,
                `capability_id`,
                `capability_name`,
                `capability_mark`,
                `capability_human_readable_mark`,
                `capability_max_value`,
                `indicator_id`,
                `indicator_name`,
                `indicator_max_value`,
                `indicator_mark`,
                `descriptor_id`,
                `descriptor_name`,
                `descriptor_max_value`,
                `descriptor_sequence`
            FROM `v_rubric_mark`
            WHERE `assessment_id` = :assessmentID
                AND `feedback_id` = :feedbackID
                AND `feedback_table` = :feedbackTable
                AND `feedback_type` = 'instructor'
            ORDER BY `rubric_instance_id`, `capability_sequence`, `capability_id`, `indicator_sequence`, `indicator_id`
        ");
        $statement->execute(
            [
                'assessmentID'  => $assessmentId,
                'feedbackID'    => $feedbackID,
                'feedbackTable' => $feedbackTable,
            ]
        );

        return self::buildFeedbackRubrics($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Reshape flat feedback rubric rows into a list of nested, marked rubrics grouped by rubric instance.
     *
     * @param array<int, array> $rows
     * @return array<int, array>
     */
    private static function buildFeedbackRubrics(array $rows): array
    {
        $rubrics = [];
        foreach ($rows as $row) {
            $instanceId = (int)$row['rubric_instance_id'];

            if (!isset($rubrics[$instanceId])) {
                $rubrics[$instanceId] = [
                    'id'           => $instanceId,
                    'title'        => $row['rubric_instance_title'],
                    'capabilities' => [],
                ];
            }

            $capabilities = &$rubrics[$instanceId]['capabilities'];
            if (!isset($capabilities[$row['capability_id']])) {
                $capabilities[$row['capability_id']] = [
                    'id'         => (int)$row['capability_id'],
                    'name'       => $row['capability_name'],
                    'value'      => (null === $row['capability_mark']) ? null : (float)$row['capability_mark'],
                    'maxValue'   => (float)$row['capability_max_value'],
                    'mark'       => $row['capability_human_readable_mark'],
                    'indicators' => [],
                ];
            }

            $indicators = &$capabilities[$row['capability_id']]['indicators'];
            if (!isset($indicators[$row['indicator_id']])) {
                $indicators[$row['indicator_id']] = [
                    'id'         => (int)$row['indicator_id'],
                    'name'       => $row['indicator_name'],
                    'maxValue'   => (float)$row['indicator_max_value'],
                    'value'      => (float)$row['indicator_mark'],
                    'descriptor' => null,
                ];

                if (null != $row['descriptor_name']) {
                    $indicators[$row['indicator_id']]['descriptor'] = [
                        'id'          => (int)$row['descriptor_id'],
                        'description' => $row['descriptor_name'],
                        'maxValue'    => (float)$row['descriptor_max_value'],
                        'sequence'    => (int)$row['descriptor_sequence'],
                    ];
                }
            }

            unset($capabilities, $indicators);
        }

        foreach ($rubrics as &$rubric) {
            foreach ($rubric['capabilities'] as &$capability) {
                $capability['indicators'] = array_values($capability['indicators']);
            }
            unset($capability);
            $rubric['capabilities'] = array_values($rubric['capabilities']);
        }
        unset($rubric);

        return array_values($rubrics);
    }
```

- [ ] **Step 4: Run the reshape test to verify it passes**

Run: `vendor/bin/phpunit library/Schoolbox/Learning/Assessment/Test/API/RubricReshapeTest.php`
Expected: PASS (6 tests).

- [ ] **Step 5: Update the feedback call site to emit `rubrics` + deprecated `rubric`**

In `library/Schoolbox/Learning/Assessment/API/Helper.php`, in `getAssessmentParticipation`:

Replace this line (currently line 677):

```php
            $rubric = $this->getRubricFeedback($rowAssessmentId, $row['feedback_id']);
```

with (passing `feedback_table`, which each participation-union arm already selects, so the new agnostic view can disambiguate the feedback source):

```php
            $rubrics = $this->getRubricsFeedback($rowAssessmentId, (int)$row['feedback_id'], (string)$row['feedback_table']);
```

Then in the `$feedbackFields` array immediately below, replace this line (currently line 694):

```php
                'rubric'         => $rubric,
```

with these two lines:

```php
                'rubric'         => self::firstRubricAsLegacy($rubrics),
                'rubrics'        => $rubrics,
```

(`$feedbackFields` is spread into both the `feedback` object and the deprecated top-level fields, so both automatically gain `rubrics` plus the deprecated singular `rubric`.)

- [ ] **Step 6: Verify no other caller references the old method name**

Run: `grep -rn "getRubricFeedback(" library/`
Expected: **no** results (definition renamed, sole caller updated).

- [ ] **Step 7: Run the reshape test again and commit**

Run: `vendor/bin/phpunit library/Schoolbox/Learning/Assessment/Test/API/RubricReshapeTest.php`
Expected: PASS (6 tests).

```bash
git add library/Schoolbox/Learning/Assessment/API/Helper.php library/Schoolbox/Learning/Assessment/Test/API/RubricReshapeTest.php
git commit -m "feat: return a set of titled rubrics in assessment feedback [SA-22780]"
```

---

## Task 5: Update the OpenAPI docs

**Files (in `/home/james/Sites/schoolbox-api-docs`):**
- Create: `openapi/components/schemas/assessment-rubrics.yaml`
- Create: `openapi/components/schemas/assessment-rubrics-feedback.yaml`
- Modify: `openapi/components/schemas/assessment-item.yaml`
- Modify: `openapi/components/schemas/assessment-participation-item.yaml`

**Interfaces:**
- Consumes: the JSON shapes produced by Tasks 2 and 3.

All commands in this task run from the `schoolbox-api-docs` repo root.

- [ ] **Step 1: Create the template rubrics schema**

Create `openapi/components/schemas/assessment-rubrics.yaml`:

```yaml
title: A set of Assessment Rubrics
description: The rubrics attached to an assessment. Empty when the assessment has no rubrics.
readOnly: true
type: array
items:
  type: object
  properties:
    id:
      type: integer
      example: 1
      description: The rubric instance ID.
    title:
      type: string
      nullable: true
      example: Written
      description: The title of this rubric.
    weight:
      type: number
      nullable: true
      example: 60
      description: How much this rubric contributes to the assessment's overall mark.
    capabilities:
      $ref: ./assessment-rubric.yaml#/properties/capabilities
```

- [ ] **Step 2: Create the feedback rubrics schema**

Create `openapi/components/schemas/assessment-rubrics-feedback.yaml`:

```yaml
title: A set of marked Assessment Rubrics
description: The marked rubrics for a feedback item. Empty when the feedback has no rubric marks.
readOnly: true
type: array
items:
  type: object
  properties:
    id:
      type: integer
      example: 1
      description: The rubric instance ID.
    title:
      type: string
      nullable: true
      example: Written
      description: The title of this rubric.
    capabilities:
      $ref: ./assessment-rubric-feedback.yaml#/properties/capabilities
```

- [ ] **Step 3: Add `rubrics` and deprecate `rubric` on the assessment item**

In `openapi/components/schemas/assessment-item.yaml`, replace this block (currently lines 95-96):

```yaml
  rubric:
    $ref: ./assessment-rubric.yaml
```

with:

```yaml
  rubric:
    allOf:
      - $ref: ./assessment-rubric.yaml
      - deprecated: true
        description: |
          A single Assessment Rubric item.
          (Deprecated: use `rubrics`. This carries only the first rubric and will be removed
          after a 6-month migration window.)
  rubrics:
    $ref: ./assessment-rubrics.yaml
```

- [ ] **Step 4: Add `rubrics` under feedback and confirm deprecations on the participation item**

In `openapi/components/schemas/assessment-participation-item.yaml`, in the `feedback` object, replace this block (currently lines 65-66):

```yaml
      rubric:
        $ref: ./assessment-rubric-feedback.yaml
```

with:

```yaml
      rubric:
        allOf:
          - $ref: ./assessment-rubric-feedback.yaml
          - deprecated: true
            description: |
              A single marked Assessment Rubric item.
              (Deprecated: use `rubrics`. This carries only the first rubric and will be removed
              after a 6-month migration window.)
      rubrics:
        $ref: ./assessment-rubrics-feedback.yaml
```

The top-level deprecated `rubric` (currently lines 97-102) already has `deprecated: true`; leave it unchanged.

- [ ] **Step 5: Lint the docs**

Run: `npm test`
Expected: `redocly lint` completes with no errors (warnings pre-existing in the repo are acceptable, but there must be no new errors referencing the files you changed).

- [ ] **Step 6: Commit**

```bash
git add openapi/components/schemas/assessment-rubrics.yaml \
        openapi/components/schemas/assessment-rubrics-feedback.yaml \
        openapi/components/schemas/assessment-item.yaml \
        openapi/components/schemas/assessment-participation-item.yaml
git commit -m "docs: document rubrics array on assessment API, deprecate rubric [SA-22780]"
```

---

## Task 6: Live verification against the dev instance

**Files:** none (manual verification).

This task confirms the end-to-end behaviour the unit tests can't: real SQL against the views, backwards compatibility, and the agnostic decoupling. Use the JWT from the task brief (host `docker.dev.schoolbox.cloud`). Export it once:

```bash
export SB_TOKEN='eyJ0eXAiOiJKV1Qi...'   # the full token from the task brief
export SB_HOST='https://docker.dev.schoolbox.cloud'
```

> **Data caveat (verified during design):** the current dev DB has **no** assessment with 2+ `rubric_instance` rows, and **zero** `reporting_feedback` rubric marks (all 880 marks are `submission_return`). Steps 1 and 5 below therefore require seeding first, or they can only confirm the single-rubric / due-work paths. The multi-rubric and reporting-feedback behaviours are otherwise covered by the Task 3/4 unit tests and the Task 2 view checks.

- [ ] **Step 1: Find (or seed) an assessment with 2+ rubric instances**

Run:

```bash
mariadb -u schoolboxdocker -pabc123 -h 127.0.0.1 schoolbox -e "SELECT assessment_id, COUNT(*) AS n FROM rubric_instance WHERE deleted_at IS NULL GROUP BY assessment_id HAVING n >= 2 LIMIT 5;"
```

Expected: at least one `assessment_id`. Note it as `MULTI_ID`. Per the data caveat this returns nothing on the current dev DB, so seed a second `rubric_instance` (plus its `rubric_capability_usage`/`rubric_indicator_usage` rows) against an assessment that already has one rubric, or fall back to a single-rubric assessment to verify the array shape.

- [ ] **Step 2: Verify the GET endpoint returns a titled `rubrics` array + deprecated `rubric`**

Run (replace `MULTI_ID`):

```bash
curl -s -H "Authorization: Bearer $SB_TOKEN" "$SB_HOST/api/assessment/MULTI_ID" \
  | python3 -m json.tool | grep -A3 -E '"rubrics"|"rubric"|"title"|"weight"'
```

Expected: a `rubrics` array with one entry per instance, each with `id`/`title`/`weight`/`capabilities`; a singular `rubric` equal to `rubrics[0]` reduced to `{capabilities: [...]}`.

- [ ] **Step 3: Verify the list endpoint mirrors the shape**

Run:

```bash
curl -s -H "Authorization: Bearer $SB_TOKEN" \
  "$SB_HOST/api/assessment?filter=%7B%22assessmentId%22%3AMULTI_ID%7D&limit=5" \
  | python3 -m json.tool | grep -A3 -E '"rubrics"|"rubric"'
```

Expected: same `rubrics`/`rubric` shape inside `data[0]`.

- [ ] **Step 4: Regression — single-rubric due work assessment is unchanged**

Pick a due-work assessment with exactly one rubric instance and confirm `rubric` still holds `{capabilities: [...]}` and `rubrics` has a single entry whose `capabilities` matches `rubric.capabilities`.

- [ ] **Step 5: Agnostic decoupling — a non-due-work (e.g. reporting) assessment now returns its rubric**

Pick a `reporting` assessment that has a `rubric_instance` and confirm `rubrics` is now populated (before this change the `submission_box` join would have returned nothing).

- [ ] **Step 6: Empty case — assessment with no rubric**

Confirm an assessment with no rubric instances returns `"rubrics": []` and `"rubric": null`.

- [ ] **Step 7: Verify feedback rubrics**

For an assessment with published instructor feedback that used rubric marks, confirm each participation item's `feedback.rubrics` is a titled array and `feedback.rubric` mirrors the first entry's capabilities.

---

## Self-Review Notes

- **Spec coverage:** `v_rubric` title/weight (Task 1) → §Approach.1; `v_rubric_mark` title/descriptor/collation (Task 2) → §Approach.1a; `getRubrics` decoupling + array + deprecated singular (Task 3) → §Approach.2; agnostic feedback array via `v_rubric_mark` + deprecated singular (Task 4) → §Approach.3; caller sweep (Task 3 Step 6, Task 4 Step 6) → §Approach.4; docs (Task 5) → §Approach.5; testing incl. view checks + live JWT (Task 2 checks, Task 3/4 unit, Task 6) → §Testing.
- **Feedback is agnostic:** `getRubricsFeedback` reads `v_rubric_mark` (submission_return + reporting_feedback), disambiguated by `feedback_table` from the call site and restricted to `feedback_type = 'instructor'` — this fixes reporting feedback rubrics (dropped today) and removes the `submission_box` coupling entirely. Performance confirmed non-regressive via `EXPLAIN` (predicate pushdown, ~3ms). The pre-existing per-feedback N+1 is unchanged and explicitly out of scope.
- **Ordering dependency:** Task 2 (`v_rubric_mark`) selects `vr.rubric_instance_title`, which only exists after Task 1 is applied — Task 2 Step 2 calls this out. Tasks must run in order 1 → 2 → 3 → 4.
- **Type consistency:** `buildTemplateRubrics`, `buildFeedbackRubrics`, `firstRubricAsLegacy`, `getRubrics`, `getRubricsFeedback` names are used identically across tasks. `firstRubricAsLegacy` is defined in Task 3 and reused in Task 4. `getRubricsFeedback` takes `(string $assessmentId, int $feedbackID, string $feedbackTable)` — the call site (Task 4 Step 5) passes all three.

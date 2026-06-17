# Rubric Migration Performance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the per-row INSERT loop in `02-replicate-settings.php` with a single `INSERT ... SELECT` to reduce ~82,000 database round-trips to 1.

**Architecture:** Delete the SELECT cursor + prepared INSERT statement + while loop. Replace with one `executeQuery` call using `INSERT ... SELECT` that joins `submission_box` to `assessment` entirely within MySQL. Use `rowCount()` on the result to get the affected-row count.

**Tech Stack:** PHP, PDO, MySQL

## Global Constraints

- Do not alter the count-verification query or `reportDivergence()` — they are correct and must remain unchanged.
- `$now` must remain a bound parameter (not interpolated) — it scopes the count-verification query.
- Follow existing `executeQuery($sql, $params)` → `rowCount()` pattern established in `04-courseOutlineFilters.php`.

---

### Task 1: Replace per-row INSERT loop with single INSERT...SELECT

**Files:**
- Modify: `updates/26.1.alpha1/rubric/02-replicate-settings.php`

**Interfaces:**
- Consumes: `$this->getDatabaseInternal()->executeQuery(string $sql, array $params): PDOStatement`
- Produces: `$numFound` (int, row count of the INSERT) — consumed by the existing info log and count-verification block beneath it (unchanged).

- [ ] **Step 1: Remove the SELECT cursor**

Delete lines 114–127 (the `$statement = $this->getDatabaseInternal()->prepare(...)` block and `$statement->execute()`):

```php
// DELETE this entire block:
$statement = $this->getDatabaseInternal()->prepare("
    SELECT 
        sb.id, 
        sb.assessment_id,
        a.title,
        sb.rubric_capability_mark_type_id,
        sb.rubric_capability_grade_group_id,
        sb.rubric_mark_range
    FROM submission_box sb
    INNER JOIN assessment a on sb.assessment_id = a.id
");

$statement->execute();
```

- [ ] **Step 2: Remove the prepared INSERT statement**

Delete lines 129–154 (the `$insertStatement = $this->getDatabaseInternal()->prepare(...)` block):

```php
// DELETE this entire block:
/* How many rows were eventually found in the result set
 */
$numFound = 0;

/* Prepare a statement that we'll reuse throughout this loop
 */
$insertStatement = $this->getDatabaseInternal()->prepare("
    INSERT INTO rubric_instance (
        assessment_id,
        title,
        weight,
        rubric_capability_mark_type_id,
        rubric_capability_grade_group_id,
        rubric_mark_range,
        created_at,
        updated_at
    ) VALUES (
        :assessmentId,
        :title,
        :weight,
        :rubricCapabilityMarkTypeId,
        :rubricCapabilityGradeGroupId,
        :rubricMarkRange,
        :createdAt,
        :updatedAt
    )
");
```

- [ ] **Step 3: Remove the while loop**

Delete lines 170–194 (the entire `while ($row = $statement->fetch(...))` block):

```php
// DELETE this entire block:
while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
    $numFound++;

    $insertStatement->execute([
        'assessmentId' => $row['assessment_id'],
        'title' => $row['title'],
        'weight' => self::DEFAULT_WEIGHT,
        'rubricCapabilityMarkTypeId' => $row['rubric_capability_mark_type_id'],
        'rubricCapabilityGradeGroupId' => $row['rubric_capability_grade_group_id'],
        'rubricMarkRange' => $row['rubric_mark_range'],
        'createdAt' => $now,
        'updatedAt' => $now,
    ]);

    $this->getLogger()->debug(sprintf(
        'Submission box %s (assessment %s) had these rubric settings: title "%s", weight %s, mark type %s, grade group %s, use mark range %s',
        $row['id'],
        $row['assessment_id'],
        $row['title'],
        self::DEFAULT_WEIGHT,
        $row['rubric_capability_mark_type_id'] ?: 'none',
        $row['rubric_capability_grade_group_id'] ?: 'none',
        $row['rubric_mark_range']
    ));
}
```

- [ ] **Step 4: Add the INSERT...SELECT in place of the removed blocks**

Replace the removed code with the block below. Place it so that `$start = microtime(true)` comes immediately before the `executeQuery` call and `$end = microtime(true)` follows it — the timer must wrap the insert, not fall after it. The final order in the file is: `$now` → `$start` → `executeQuery` → `$end`.

```php
$numFound = $this->getDatabaseInternal()->executeQuery("
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
", [
    'weight'    => self::DEFAULT_WEIGHT,
    'createdAt' => $now,
    'updatedAt' => $now,
])->rowCount();
```

- [ ] **Step 5: Verify the file reads correctly**

The `run()` method body should now look like this (key structure only — verify your file matches):

```php
$this->getLogger()->info('Cloning submission_box rubric settings into rubric_instance...');

$now = \Alaress_Date_Format::now()->getDatabase();

$numFound = $this->getDatabaseInternal()->executeQuery("
    INSERT INTO rubric_instance ( ... )
    SELECT ... FROM submission_box sb INNER JOIN assessment a ON sb.assessment_id = a.id
", [
    'weight'    => self::DEFAULT_WEIGHT,
    'createdAt' => $now,
    'updatedAt' => $now,
])->rowCount();

$start = microtime(true);   // <-- this should still exist ABOVE the insert in context of timing

// ... $end, $numInserted count query, info log, reportDivergence() all unchanged
```

> **Note on timer placement:** `$start = microtime(true)` currently sits between the removed blocks and the loop. After the removal it will land just after `$numFound = ...`. Move `$start` to just before the `executeQuery` call so the timer wraps the insert.

- [ ] **Step 6: Verify the final `run()` method structure by reading the file**

Confirm:
- No reference to `$statement`, `$insertStatement`, or `$row` remains in `run()`.
- `$numFound` is assigned from `->rowCount()`.
- `$start = microtime(true)` is before the `executeQuery` call.
- `$end = microtime(true)` is after it.
- The count-verification query and `reportDivergence()` call are untouched.

- [ ] **Step 7: Manual verification on a dev environment**

```bash
php updates/26.1.alpha1/rubric/02-replicate-settings.php
```

Expected output (values will differ, but shape must match):
```
[INFO] Cloning submission_box rubric settings into rubric_instance...
[INFO] Replication of XXXX submission boxes into XXXX rubric_instance rows took Y.YY seconds.
[INFO] No divergences detected.
```

Confirm:
- Row counts in the log line match (`submission boxes` == `rubric_instance rows`).
- No divergences detected.
- Time taken is seconds, not minutes.

- [ ] **Step 8: Commit**

```bash
git add updates/26.1.alpha1/rubric/02-replicate-settings.php
git commit -m "perf: replace per-row INSERT loop with bulk INSERT...SELECT in rubric migration

Reduces ~82,000 DB round-trips to 1 SQL statement.
Observed improvement: ~227s → expected <5s on large deployments."
```

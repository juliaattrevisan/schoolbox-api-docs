# Course Outline Filters Migration Performance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the N-round-trip PHP loop in `04-courseOutlineFilters.php` with 1–2 SQL `UPDATE` queries using `JSON_EXTRACT`/`JSON_SET`, reducing up to 25,000 DB round-trips to 1–2.

**Architecture:** The LTI config flag is checked once in PHP (as before), then one of two pre-built `UPDATE` queries is executed directly — no `SELECT`, no PHP row processing, no loop. The `IStatement::rowCount()` method (already available on the DB layer) replaces the manual `$updated` counter. The `html_entity_decode()` call is removed as values are confirmed to be stored as plain JSON.

**Tech Stack:** PHP 8.x, MySQL (JSON_EXTRACT / JSON_SET), `Alaress\Database\Connector\IConnector`, `Alaress\Database\Statement\IStatement`

---

### Task 1: Replace the migration body with two SQL UPDATE queries

**Files:**
- Modify: `updates/26.1.alpha1/reporting/04-courseOutlineFilters.php`

This is the only file that changes. The entire `run()` method body is replaced — everything from the `$db` assignment down to the closing `$this->getLogger()->info(...)` call.

The two queries differ only in whether the `$.lti` condition is included. When LTI is enabled, we require `$.lti = '1'` as part of "still at defaults". When disabled, that key would not have been `"1"` on default configs for that site, so we omit it.

- [ ] **Step 1: Open the file and read the current `run()` method**

File: `updates/26.1.alpha1/reporting/04-courseOutlineFilters.php`, lines 51–167.

Confirm the structure:
- `$db = $this->getDatabaseInternal();`
- The block comment about LTI/ordering
- `$updateStatement = $db->prepareQuery(...)`
- `$query = "SELECT cci.id, cci.value, ci.id AS component_id ..."`
- `$configInstances = $db->executeQuery($query)->fetchAll();`
- `$assessmentTypeOptions = AssessmentType::getOptions();`
- `$ltiEnabled = $this->getConfig()->get('lti_assessments_enable');`
- `foreach (...)` loop with per-row decode/check/update
- `$this->getLogger()->info(...)`

- [ ] **Step 2: Replace the `run()` method body**

Replace lines 51–167 with the following. The `--help` block and `runAsRoot()` method are untouched.

```php
    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Reporting Activity - Course Outline Filters Migration

This updates all existing course outline component filters that haven't been changed
from the default values to include Reporting Activity (hence remaining consistent with the new default).

This always runs in perform mode as part of the upgrade.

Usage: 04-courseOutlineFilters.php [-vvv]


HELP;
            return;
        }

        $db = $this->getDatabaseInternal();

        $ltiEnabled = $this->getConfig()->get('lti_assessments_enable');

        /**
         * Update all course outline "assessmentTypes" config instances that are still at their
         * default values (all non-reporting types enabled, nothing filtered out) to include
         * Reporting Activity.
         *
         * Two query variants handle whether LTI is enabled: when LTI is disabled, default configs
         * would not have "lti":"1", so we don't require it as part of the default check.
         */
        if ($ltiEnabled) {
            $sql = "
                UPDATE component_config_instance cci
                INNER JOIN component_config_definition ccd
                    ON cci.component_config_definition_id = ccd.id
                    AND ccd.`key` = 'assessmentTypes'
                INNER JOIN component_instance ci
                    ON cci.component_instance_id = ci.id
                INNER JOIN component_definition cd
                    ON ci.component_definition_id = cd.id
                    AND cd.class = 'Schoolbox\\\\Learning\\\\Assessment\\\\Component\\\\Homepage\\\\Outline\\\\Controller'
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
            ";
        } else {
            $sql = "
                UPDATE component_config_instance cci
                INNER JOIN component_config_definition ccd
                    ON cci.component_config_definition_id = ccd.id
                    AND ccd.`key` = 'assessmentTypes'
                INNER JOIN component_instance ci
                    ON cci.component_instance_id = ci.id
                INNER JOIN component_definition cd
                    ON ci.component_definition_id = cd.id
                    AND cd.class = 'Schoolbox\\\\Learning\\\\Assessment\\\\Component\\\\Homepage\\\\Outline\\\\Controller'
                SET cci.value = JSON_SET(cci.value, '$.reporting', '1')
                WHERE JSON_EXTRACT(cci.value, '$.project')     = '1'
                  AND JSON_EXTRACT(cci.value, '$.dueWork')     = '1'
                  AND JSON_EXTRACT(cci.value, '$.task')        = '1'
                  AND JSON_EXTRACT(cci.value, '$.quiz')        = '1'
                  AND JSON_EXTRACT(cci.value, '$.lessonPlan')  = '1'
                  AND JSON_EXTRACT(cci.value, '$.dialogAgent') = '1'
                  AND (JSON_EXTRACT(cci.value, '$.reporting') IS NULL
                    OR JSON_EXTRACT(cci.value, '$.reporting') != '1')
            ";
        }

        $result = $db->executeQuery($sql);

        $this->getLogger()->info(sprintf(
            '%s course outline component config instances matched defaults and have been updated to include Reporting Activity',
            $result->rowCount(),
        ));
    }
```

**Note on the `use` import:** The `AssessmentType` import at the top of the file is no longer needed. Remove it:

```php
// DELETE this line (line 12 in the original):
use Schoolbox\Learning\Assessment\AssessmentType;
```

- [ ] **Step 3: Verify the file compiles without errors**

Run:
```bash
php -l updates/26.1.alpha1/reporting/04-courseOutlineFilters.php
```

Expected output:
```
No syntax errors detected in updates/26.1.alpha1/reporting/04-courseOutlineFilters.php
```

- [ ] **Step 4: Verify the script runs against your dev database**

From the repo root (adjust path as needed for your environment):
```bash
php updates/26.1.alpha1/reporting/04-courseOutlineFilters.php
```

Expected output (exact count will vary by environment):
```
[info] N course outline component config instances matched defaults and have been updated to include Reporting Activity
```

If no course outline components exist in your dev DB, the count will be `0` — that's fine. Confirm no PHP errors or DB exceptions are thrown.

- [ ] **Step 5: Verify idempotency**

Run the script a second time immediately:
```bash
php updates/26.1.alpha1/reporting/04-courseOutlineFilters.php
```

Expected: count is `0` (the `WHERE` clause excludes rows already having `"reporting":"1"`).

- [ ] **Step 6: Spot-check the DB state (optional but recommended if rows were updated)**

In MySQL, run:
```sql
SELECT cci.value
FROM component_config_instance cci
INNER JOIN component_config_definition ccd
    ON cci.component_config_definition_id = ccd.id AND ccd.`key` = 'assessmentTypes'
INNER JOIN component_instance ci ON cci.component_instance_id = ci.id
INNER JOIN component_definition cd
    ON ci.component_definition_id = cd.id
    AND cd.class = 'Schoolbox\\Learning\\Assessment\\Component\\Homepage\\Outline\\Controller'
LIMIT 10;
```

Confirm that `"reporting":"1"` is present in the JSON values. Rows that had custom filters (not all types set to `"1"`) should be unchanged.

- [ ] **Step 7: Commit**

```bash
git add updates/26.1.alpha1/reporting/04-courseOutlineFilters.php
git commit -m "perf: replace per-row UPDATE loop with bulk JSON_SET query in courseOutlineFilters migration

Reduces up to 25,000 DB round-trips to 1-2 SQL UPDATE queries using
JSON_EXTRACT for default-state filtering and JSON_SET to add the reporting key."
```

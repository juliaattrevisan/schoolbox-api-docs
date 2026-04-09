# Add permittedReportingActivity to Work Types — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `permitted_reporting_activity` to the Work Types DB table, mapper, model, and Admin UI checkbox list so Reporting Activity is configurable alongside the other permitted activity types.

**Architecture:** Five co-located changes following the exact `permittedDialogAgent` pattern — DB column, row defaults, mapper registration, model property/getter/setter, and Admin UI checkbox. No new tests required (the pattern is covered by existing mapper/model infrastructure).

**Tech Stack:** PHP 8, MySQL, Schoolbox mapper framework, Admin Lists controller.

---

### Task 1: Add DB column to migration

**Files:**
- Modify: `updates/26.1.alpha1/luWorkTypes/01-add-columns.sql:10`

- [ ] **Step 1: Edit `01-add-columns.sql`**

Change line 10 from ending with `;` to a comma, and add the new column on line 11:

```sql
ALTER TABLE lu_work_type
ADD COLUMN is_visible_to_parents tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER staff_or_instructor_visible_only,
ADD COLUMN is_visible_to_learners tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER is_visible_to_parents,
ADD COLUMN permitted_project tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER is_visible_to_learners,
ADD COLUMN permitted_due_work tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_project,
ADD COLUMN permitted_task tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_due_work,
ADD COLUMN permitted_quiz tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_task,
ADD COLUMN permitted_lti tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_quiz,
ADD COLUMN permitted_lesson_plan tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_lti,
ADD COLUMN permitted_dialog_agent tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_lesson_plan,
ADD COLUMN permitted_reporting_activity tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_dialog_agent;
```

- [ ] **Step 2: Edit `02-update-rows.sql`**

Change line 10 from ending with `; --  /` to `, --  |` and add the new row:

```sql
UPDATE lu_work_type SET
is_visible_to_parents  = IF(staff_or_instructor_visible_only > 0, 0, 1), -- to preserve existing behaviour
is_visible_to_learners = IF(staff_or_instructor_visible_only > 0, 0, 1), -- to preserve existing behaviour
permitted_project      = 1, -- -+ All existing work types should have all activities permitted.
permitted_due_work     = 1, --  |
permitted_task         = 1, --  |
permitted_quiz         = 1, --  |
permitted_lti          = 1, --  |
permitted_lesson_plan  = 1, --  |
permitted_dialog_agent         = 1, --  |
permitted_reporting_activity   = 1; --  /
```

- [ ] **Step 3: Apply the migration to the local dev database**

```bash
mysql -u root schoolbox < updates/26.1.alpha1/luWorkTypes/01-add-columns.sql
mysql -u root schoolbox < updates/26.1.alpha1/luWorkTypes/02-update-rows.sql
```

Expected: no errors. Verify with:

```bash
mysql -u root schoolbox -e "DESCRIBE lu_work_type;" | grep permitted_reporting
```

Expected output: a row showing `permitted_reporting_activity`.

- [ ] **Step 4: Commit**

```bash
git add updates/26.1.alpha1/luWorkTypes/01-add-columns.sql updates/26.1.alpha1/luWorkTypes/02-update-rows.sql
git commit -m "Add permitted_reporting_activity column to lu_work_type [SA-21973]"
```

---

### Task 2: Register column in WorkType Mapper

**Files:**
- Modify: `library/Schoolbox/Learning/Assessment/WorkType/Mapper.php:78`

- [ ] **Step 1: Add the mapping after `permitted_dialog_agent` (line 78)**

The `mapping()` method currently ends its permitted-activity block at line 78. Add one line after it:

```php
        $this->uses('permitted_lesson_plan', self::TYPE_BOOLEAN);
        $this->uses('permitted_dialog_agent', self::TYPE_BOOLEAN);
        $this->uses('permitted_reporting_activity', self::TYPE_BOOLEAN);  // ← add this
```

- [ ] **Step 2: Commit**

```bash
git add library/Schoolbox/Learning/Assessment/WorkType/Mapper.php
git commit -m "Register permitted_reporting_activity in WorkType Mapper [SA-21973]"
```

---

### Task 3: Add property, getter, and setter to WorkType Model

**Files:**
- Modify: `library/Schoolbox/Learning/Assessment/WorkType/Model.php:80-81` (after `permittedDialogAgent` property), and at end of file before closing `}`

- [ ] **Step 1: Add the property after `$permittedDialogAgent` (around line 80)**

```php
    /** @var ?bool */
    private ?bool $permittedDialogAgent = null;

    /** @var ?bool */
    private ?bool $permittedReportingActivity = null;  // ← add these two lines
```

- [ ] **Step 2: Add getter and setter before the closing `}` of the class (after `setPermittedDialogAgent`, around line 397)**

```php
    /**
     * @return ?bool
     */
    public function getPermittedReportingActivity(): ?bool
    {
        return $this->permittedReportingActivity;
    }

    /**
     * @param bool $permittedReportingActivity
     * @return void
     */
    public function setPermittedReportingActivity(bool $permittedReportingActivity): void
    {
        $this->permittedReportingActivity = $permittedReportingActivity;
    }
```

- [ ] **Step 3: Commit**

```bash
git add library/Schoolbox/Learning/Assessment/WorkType/Model.php
git commit -m "Add permittedReportingActivity property/getter/setter to WorkType Model [SA-21973]"
```

---

### Task 4: Add checkbox to Admin UI

**Files:**
- Modify: `library/Schoolbox/Admin/Lists/Controller.php:32` (use statements), `:1198` (checkbox entry)

- [ ] **Step 1: Add `use` statement after the `DialogAgent` import (line 32)**

```php
use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgent;
use Schoolbox\Learning\Assessment\Reporting\Model as Reporting;  // ← add this line
```

- [ ] **Step 2: Add the checkbox entry after `permittedDialogAgent` (around line 1198)**

```php
                    'permittedDialogAgent' => [
                        'caption'    => DialogAgent::getDefaultTypeName(),
                        'checkbox'   => true,
                        'mandatory'  => true,
                        'default'    => false,
                        'popOutList' => 'permittedActivities',
                    ],
                    'permittedReportingActivity' => [  // ← add from here
                        'caption'    => Reporting::getDefaultTypeName(),
                        'checkbox'   => true,
                        'mandatory'  => true,
                        'default'    => false,
                        'popOutList' => 'permittedActivities',
                    ],
```

- [ ] **Step 3: Verify in browser**

Navigate to Admin > LMS > Work Types. Open any work type and expand the "Permitted Activities" pop-out. Confirm a "Reporting Activity" checkbox now appears alongside the others. Toggle it and save — verify the value persists on reload.

- [ ] **Step 4: Commit**

```bash
git add library/Schoolbox/Admin/Lists/Controller.php
git commit -m "Add permittedReportingActivity checkbox to Admin Work Types UI [SA-21973]"
```
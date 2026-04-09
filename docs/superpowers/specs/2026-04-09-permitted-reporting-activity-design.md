# Design Spec: Add permittedReportingActivity to Work Types

**Date:** 2026-04-09
**Ticket:** SA-21973
**PR:** alaress/schoolbox#34740

## Context

The Admin Work Types page has a "Permitted Activities" pop-out list with one checkbox per assessment type. The PR added checkboxes for Project, DueWork, Task, Quiz, Lti, LessonPlan, and DialogAgent — but omitted Reporting Activity, which is now a first-class assessment type (`AssessmentType::TYPE_REPORTING`). A reviewer flagged this as Required.

## Decision

Show the `permittedReportingActivity` checkbox unconditionally, consistent with how `permittedDialogAgent` is handled (always shown regardless of its feature flag).

## Changes Required

### 1. DB migration — `updates/26.1.alpha1/luWorkTypes/01-add-columns.sql`

Add column after `permitted_dialog_agent`:

```sql
ADD COLUMN permitted_reporting_activity tinyint(1) unsigned NOT NULL DEFAULT 0 AFTER permitted_dialog_agent;
```

### 2. DB migration — `updates/26.1.alpha1/luWorkTypes/02-update-rows.sql`

Set all existing rows to `1` (consistent with other permitted columns — all existing work types should permit all activity types):

```sql
permitted_reporting_activity = 1;
```

### 3. `library/Schoolbox/Learning/Assessment/WorkType/Mapper.php`

Add after `permitted_dialog_agent` line:

```php
$this->uses('permitted_reporting_activity', self::TYPE_BOOLEAN);
```

### 4. `library/Schoolbox/Learning/Assessment/WorkType/Model.php`

Add property, getter, and setter following the exact pattern of `permittedDialogAgent`:

```php
/** @var ?bool */
private ?bool $permittedReportingActivity = null;

public function getPermittedReportingActivity(): ?bool
{
    return $this->permittedReportingActivity;
}

public function setPermittedReportingActivity(bool $permittedReportingActivity): void
{
    $this->permittedReportingActivity = $permittedReportingActivity;
}
```

### 5. `library/Schoolbox/Admin/Lists/Controller.php`

Add `use` statement for the Reporting model (alongside the other assessment model imports):

```php
use Schoolbox\Learning\Assessment\Reporting\Model as Reporting;
```

Add checkbox entry after `permittedDialogAgent`:

```php
'permittedReportingActivity' => [
    'caption'    => Reporting::getDefaultTypeName(),
    'checkbox'   => true,
    'mandatory'  => true,
    'default'    => false,
    'popOutList' => 'permittedActivities',
],
```

## What is not changing

- No changes to visibility logic (`Visibility.php`, `Feed.php`, etc.)
- No changes to feature flag handling — the checkbox is always shown
- No new tests required: the pattern is fully covered by existing test infrastructure for `permittedDialogAgent`
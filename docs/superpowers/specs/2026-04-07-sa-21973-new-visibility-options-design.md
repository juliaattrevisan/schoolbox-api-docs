# SA-21973: Implement New Work Type Visibility Options

## Context

This spec covers the remaining TODO work in PR #34740. The PR replaces the deprecated
`lu_work_type.staff_or_instructor_visible_only` column with two new boolean columns:
`is_visible_to_learners` and `is_visible_to_parents`.

Two areas were already completed (Admin\HealthCheck\Controller and library\Feed's
`getOnlineAssessments()`). This spec covers the remaining areas plus two fixes discovered
during brainstorming.

### Key terminology

- **Learner** — a user with READ access to a specific folder (context-specific, not a global role)
- **Instructor** — a user with WRITE access to a specific folder (context-specific)
- **Staff** — a user with the system-wide 'staff' role type (`Role::isStaff()`)
- **Parent of a learner** — a user who has at least one child with READ access to a folder
  (`Access::isParentOfLearner()`)

### Visibility rules (from `Assessment\WorkType\Mapper` constants)

| `is_visible_to_learners` | `is_visible_to_parents` | Constant | Who can see it |
|---|---|---|---|
| 0 | 0 | `VISIBILITY_STAFF_INSTRUCTORS` | Instructors always; staff unless also a learner; others only if instructor |
| 1 | 0 | `VISIBILITY_STAFF_INSTRUCTORS_LEARNERS` | Instructors always; learners always; staff always; others only if instructor or learner |
| 0 | 1 | `VISIBILITY_STAFF_INSTRUCTORS_PARENTS` | Instructors always; parents of learners unless also learners; staff unless also a learner; others only if instructor |
| 1 | 1 | `VISIBILITY_STAFF_INSTRUCTORS_LEARNERS_PARENTS` | Instructors always; learners always; parents always; staff always; others only if instructor or learner |

**Critical rule:** When `is_visible_to_learners = 0`, a user's learner relationship to the
assessment's folder overrides their staff role type. Only instructor status (WRITE access)
overrides learner exclusion.

### Assessment context and folders

An assessment always has exactly one active context (`setAssessmentContext()`), which resolves
to exactly one folder via `IAssessmentContextDataModel::getFolder()`. The many-to-many
relationship in `mv_assessment_relation` represents all possible contexts, but at runtime each
assessment interaction (submission, calendar view, etc.) has a single folder context, accessible
via `$assessment->getBaseContext()->getFolder()`.

---

## Changes Already Made

### `Assessment\WorkType\Mapper.php` — comment fix
Updated `VISIBILITY_STAFF_INSTRUCTORS` comment from:
- `users of role type 'staff' - always included`

To:
- `users of role type 'staff' - included unless they are learners`

This corrects an inconsistency: both `VISIBILITY_STAFF_INSTRUCTORS` and
`VISIBILITY_STAFF_INSTRUCTORS_PARENTS` (the two cases where `is_visible_to_learners = 0`)
should exclude staff who are also learners.

### `library/Feed.php` — `getOnlineAssessments()` case `'0-0'`
Updated the `'0-0'` switch case to match the corrected rule: learner status now overrides
staff role type. Also fixed a pre-existing bug where `$access->isLearner($this->user, $row[''])`
passed an empty-string key instead of `$folder`.

Old logic: skip only if `!instructor && !staff && isLearner`
New logic: skip if `!instructor && (isLearner || !isStaff)` — i.e., learner check runs before
staff check, so staff who are also learners are excluded.

---

## Remaining Changes

### 1. `library/Schoolbox/Learning/Assessment/Visibility.php`

**Why:** `isVisibleToUser()` short-circuits to `true` for all staff without checking learner
status. `isVisibleToNonInstructor()` uses the deprecated `isStaffOrInstructorVisibleOnly()`.

**How:**

Restructure `isVisibleToUser()` to call `getFolderAccess()` once at the top and branch on the
result:

```
folderAccess = getFolderAccess(viewer, assessment->getBaseContext()->getFolder()->getId())

if folderAccess >= WRITE (instructor):
    return isVisibleToInstructor(...)   // always included, just check timing

if folderAccess == READ (learner):
    workType = assessment->getWorkType()
    'if respectSivo && workType !== null && !workType->isVisibleToLearners():
        return false                    // excluded even if staff
    if viewer->getRole()->isStaff():
        return isVisibleToStaff(...)
    return isVisibleToNonInstructor(..., isLearner: true)

// DENY access (non-learner)
if viewer->getRole()->isStaff():
    return isVisibleToStaff(...)
return isVisibleToNonInstructor(..., isLearner: false)
```

The learner exclusion for READ-access viewers is already handled in `isVisibleToUser()` before
`isVisibleToNonInstructor()` is called. So `isVisibleToNonInstructor()` only needs to handle
the DENY-access (non-learner) case. Replace the `isStaffOrInstructorVisibleOnly()` check with:

```
workType = assessable->getWorkType()
if workType !== null && respectSivo:
    if !workType->isVisibleToLearners() && !workType->isVisibleToParents():
        return false    // pure SIVO (0,0): non-instructor non-learner non-staff excluded

// For is_visible_to_parents = 1 (1,0): parent-of-learner visibility is handled by
// isVisibleToUserOrChildOfUser() iterating children — no change needed here.
// For (1,0) and (0,1): no early return; existing participant/publish/close logic handles it.

// ... existing participant/publish/close logic unchanged
```

Note: `getWorkType()` can return `null` (assessment has no work type assigned). In that case,
no filtering applies — consistent with the existing SQL pattern
`assessment.work_type_id IS NULL OR ...`.

### 2. `library/Schoolbox/Learning/Moment/Feed/Cursor.php`

**Why:** The work type SQL subquery in `getDueWorkFeedbackQueryAndParams()` and
`getQuizFeedbackQueryAndParams()` uses global role-type checks (`getRole()->isStudent()`,
`getRole()->isParent()`) which are incorrect proxies for folder-specific learner/parent
relationships. The Hydrator already calls `Visibility::isVisibleToUserOrChildOfUser()` on
fully-hydrated assessment objects, so the SQL pre-filter is redundant after `Visibility.php`
is updated.

**How:**

Remove the `$filterWorkType` flag and the work type SQL subquery entirely from both methods.
The pagination count (`retrieveResultCount()`) may over-count slightly, but this discrepancy
already exists due to the Hydrator's other visibility checks, and is acceptable.

Also fix the existing incorrect column name: `is_visible_to_students` → `is_visible_to_learners`
on line 512 (this bug exists even without the broader change).

Note: the `MARK_VISIBILITY` logic (`$visibility` / `submission_box.mark_visibility`) is a
separate concern from work type visibility and is not changed by this spec.

### 3. `library/Schoolbox/Filter/Filters/Assessment/WorkType/Factory.php`

**Why:** `buildComponent()` currently uses `any(notEquals is_visible_to_parents, notEquals
is_visible_to_learners)` regardless of the viewer's actual role — it doesn't differentiate
between a learner viewer and a parent viewer, and doesn't account for staff who are also
learners.

**How:**

For `PAGE_UPCOMING_ASSESSMENTS` and the non-staff branch of `PAGE_GRADES`, replace the current
predicate with viewer classification logic:

```
1. If $user === $viewee (viewer is the learner):
   → findByPredicates: is_visible_to_learners != 0

2. Else if $viewee is in $user->getChildren() (viewer is parent of the learner):
   → findByPredicates: is_visible_to_parents != 0

3. Else (need folder-level check):
   Query viewee's folders (folders where viewee has READ access).
   Check viewer's access level across those folders:
   - WRITE access to any → viewer is instructor → findAll()
   - READ access to any → viewer is also a learner → findByPredicates: is_visible_to_learners != 0
   - isStaff() AND no READ access → staff non-learner → findAll()
   - Otherwise → viewer has no relevant relationship → return empty work type collection
```

For `PAGE_GRADES` with `$user->getRole()->isStaff()` (the existing staff branch that calls
`findAll()`): this should also apply the learner check. If the staff user has READ access to
any of the viewee's folders, filter by `is_visible_to_learners != 0` instead.

---

### 4. Rename `$respectSivo` → `$respectWorkTypeVisibility`

Rename the parameter throughout `Visibility.php` and all callers. The semantics are unchanged
(`true` = apply work type visibility rules, `false` = ignore them), but the name `$respectSivo`
is tied to the deprecated `staff_or_instructor_visible_only` field and is misleading now that
the visibility model has four states.

Callers to update:
- `library/Schoolbox/Learning/Moment/Feed/Hydrator.php` (×2)
- `library/Schoolbox/Learning/Grades/Helper.php` (×1)
- Any other callers passing the parameter explicitly (all currently pass `true` or use the default)

### 5. Notifiers — replace `isStaffOrInstructorVisibleOnly()` with new visibility checks

Three notifier files use `isStaffOrInstructorVisibleOnly()` to decide whether to suppress
notifications. With the new model, `isStaffOrInstructorVisibleOnly()` only returns `true` for
the `(0,0)` case, so notifications are no longer suppressed for `(0,1)` even when learners
can't see the assessment — a latent bug introduced by splitting the old field into two.

**`ReturnedWorkNotifier.php` (line 136) and `SubmissionNotifier.php` (line 323):**

These send notifications to both learners and parents. Suppress only when nobody outside
staff/instructors can see the assessment. Replace:

```php
$workType->isStaffOrInstructorVisibleOnly()
```

With:

```php
!$workType->isVisibleToLearners() && !$workType->isVisibleToParents()
```

**`Participant/Plan/Notifier.php` (lines 302–303 and 326–327):**

These track `$isCurrentHidden` / `$isPreviousHidden` to suppress or trigger notifications
about assessment creation and work type changes. The audience is learners (participants).
Suppress when learners can't see the assessment, regardless of parent visibility. Replace:

```php
$this->workTypeMapper->findById(...)->isStaffOrInstructorVisibleOnly()
```

With:

```php
!$this->workTypeMapper->findById(...)->isVisibleToLearners()
```

**`Participant/Plan/Notifier.php` line 1412 (`isNotifiableAtAll()`):**

This suppresses all participation-status notifications when the assessment is staff-only.
The purpose is learner-specific (participation notifications are only meaningful when learners
can see the assessment). Replace:

```php
$assessment->isStaffOrInstructorVisibleOnly()
```

With:

```php
!($assessment->getWorkType()?->isVisibleToLearners() ?? true)
```

(The `?? true` default means: if there is no work type, treat as visible — consistent with the
existing pattern of not filtering when `work_type_id` is null.)

---

## Out of Scope

- Removing `isStaffOrInstructorVisibleOnly()` from `IAssessable`/`AbstractAssessment`/`WorkType\Model`
  — all callers are updated in sections 1 and 5, but removing the method itself is a separate
  cleanup that can follow in a later PR once the deprecation is confirmed complete.
- Changes to `MARK_VISIBILITY` logic in `Cursor.php`.
- Changes to `isVisibleToUserOrChildOfUser()` — the `getRole()->isParent()` optimisation is
  acceptable since non-parent-role users are unlikely to have children in the system.

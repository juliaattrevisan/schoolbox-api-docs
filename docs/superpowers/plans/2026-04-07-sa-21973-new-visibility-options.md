# SA-21973: New Visibility Options Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace deprecated `staff_or_instructor_visible_only` checks with folder-specific `is_visible_to_learners` / `is_visible_to_parents` logic across `Visibility.php`, `Cursor.php`, `Factory.php`, and three notifier files.

**Architecture:** Work type visibility is now a 2-bit field (`is_visible_to_learners`, `is_visible_to_parents`). Learner status is folder-specific (READ access), not a global role type. The critical new rule: when `is_visible_to_learners = 0`, a user's learner relationship overrides their staff role — only instructor status (WRITE access) overrides learner exclusion.

**Tech Stack:** PHP 8.x, PHPUnit 12, Schoolbox ORM (Mapper/Model pattern)

---

## Files Changed

| File | Action |
|---|---|
| `library/Schoolbox/Learning/Assessment/Test/VisibilityTest.php` | Modify — add learner (READ) user and new test rows |
| `library/Schoolbox/Learning/Assessment/Visibility.php` | Modify — rename `$respectSivo`, restructure `isVisibleToUser()`, fix `isVisibleToNonInstructor()` |
| `library/Schoolbox/Learning/Moment/Feed/Cursor.php` | Modify — remove `$filterWorkType` SQL subqueries, fix column name bug |
| `library/Schoolbox/Assessment/Component/SubmissionBox/ReturnedWorkNotifier.php` | Modify — replace `isStaffOrInstructorVisibleOnly()` |
| `library/Schoolbox/Assessment/Component/SubmissionBox/Submission/SubmissionNotifier.php` | Modify — replace `isStaffOrInstructorVisibleOnly()` |
| `library/Schoolbox/Assessment/Participant/Plan/Notifier.php` | Modify — replace three `isStaffOrInstructorVisibleOnly()` calls |
| `library/Schoolbox/Filter/Filters/Assessment/WorkType/Factory.php` | Modify — add `Access` + `FolderMapper` deps, implement viewer classification |

---

## Task 1: Add learner test cases to VisibilityTest (failing tests first)

**Files:**
- Modify: `library/Schoolbox/Learning/Assessment/Test/VisibilityTest.php`

The existing test has only two users: ID 1 (ACCESS_DENY) and ID 2 (ACCESS_WRITE). There is no learner (ACCESS_READ) user tested. These new rows will fail until Task 2 is complete — that is intentional.

Key new behaviors to cover:
- Learner + student + SIVO → `false` (learner excluded by worktype)
- Learner + student + is_visible_to_parents only (0,1) → `false` (learner excluded)
- Learner + staff + SIVO → `false` (**critical**: learner status overrides staff role!)
- Learner + staff + (0,1) → `false` (learner overrides staff)
- Learner + student + learners-visible (1,1) → `true` when participant + published

- [ ] **Step 1.1: Add `workTypeParentsOnly` fixture and user 3 to `VisibilityTest.php`**

In `providerIsVisible()`, after the existing `$workTypeSivo` line, add:
```php
$workTypeParentsOnly = (new WorkType\Model())->setIsVisibleToParents(true)->setIsVisibleToLearners(false);
```

In `buildUsers()`, add a third user:
```php
private static function buildUsers(): array
{
    return [
        (new User())->setId(1)->setFirstname('Andy - non-instructor'),
        (new User())->setId(2)->setFirstname('Bart - is-instructor'),
        (new User())->setId(3)->setFirstname('Cath - is-learner'),
    ];
}
```

Update `getMockAccess()` to return `ACCESS_READ` for user ID 3:
```php
private function getMockAccess(): Access|MockObject
{
    /** @var Access|MockObject $mockAccess */
    $mockAccess = $this->createMock(Access::class);
    $mockAccess
        ->expects($this->any())
        ->method('getFolderAccess')
        ->willReturnCallback(function (User $user) {
            return match ($user->getId()) {
                1 => UserAccess::ACCESS_DENY,
                2 => UserAccess::ACCESS_WRITE,
                3 => UserAccess::ACCESS_READ,
                default => throw new \InvalidArgumentException("No access level defined for user '{$user->getId()}'"),
            };
        });
    return $mockAccess;
}
```

Update `providerIsVisible()` to destructure a third user:
```php
[$nonInstructor, $isInstructor, $isLearner] = self::buildUsers();
```

- [ ] **Step 1.2: Add new data provider rows for learner scenarios**

At the end of the `return [...]` array in `providerIsVisible()`, add the following rows (before the closing `];`):

```php
// Learner (READ-access) student, SIVO (0,0): always excluded
[$isParticipant,  $studentRole, $isLearner, $workTypeSivo,        $upcoming, $showClosed, $now, false],
[$isParticipant,  $studentRole, $isLearner, $workTypeSivo,        $open,     $showClosed, $now, false],
[$notParticipant, $studentRole, $isLearner, $workTypeSivo,        $upcoming, $showClosed, $now, false],

// Learner (READ-access) student, parents-only (0,1): learner excluded
[$isParticipant,  $studentRole, $isLearner, $workTypeParentsOnly, $upcoming, $showClosed, $now, false],
[$isParticipant,  $studentRole, $isLearner, $workTypeParentsOnly, $open,     $showClosed, $now, false],
[$notParticipant, $studentRole, $isLearner, $workTypeParentsOnly, $upcoming, $showClosed, $now, false],

// Learner (READ-access) student, learners-visible (1,1): same as non-learner student
[$isParticipant,  $studentRole, $isLearner, $workTypeNormal,      $unpublished, $showClosed, $now, false],
[$isParticipant,  $studentRole, $isLearner, $workTypeNormal,      $upcoming,    $showClosed, $now, true],
[$isParticipant,  $studentRole, $isLearner, $workTypeNormal,      $upcoming,    $hideClosed, $now, true],
[$isParticipant,  $studentRole, $isLearner, $workTypeNormal,      $closed,      $showClosed, $now, true],
[$isParticipant,  $studentRole, $isLearner, $workTypeNormal,      $closed,      $hideClosed, $now, false],
[$notParticipant, $studentRole, $isLearner, $workTypeNormal,      $upcoming,    $showClosed, $now, false],
[$isParticipant,  $studentRole, $isLearner, $workTypeNone,        $upcoming,    $showClosed, $now, true],
[$notParticipant, $studentRole, $isLearner, $workTypeNone,        $upcoming,    $showClosed, $now, false],

// Learner (READ-access) STAFF, SIVO (0,0): EXCLUDED despite staff role — critical new rule
[$isParticipant,  $staffRole,   $isLearner, $workTypeSivo,        $upcoming, $showClosed, $now, false],
[$isParticipant,  $staffRole,   $isLearner, $workTypeSivo,        $open,     $showClosed, $now, false],
[$notParticipant, $staffRole,   $isLearner, $workTypeSivo,        $upcoming, $showClosed, $now, false],
[$notParticipant, $staffRole,   $isLearner, $workTypeSivo,        $closed,   $showClosed, $now, false],

// Learner (READ-access) STAFF, parents-only (0,1): EXCLUDED despite staff role
[$isParticipant,  $staffRole,   $isLearner, $workTypeParentsOnly, $upcoming, $showClosed, $now, false],
[$notParticipant, $staffRole,   $isLearner, $workTypeParentsOnly, $upcoming, $showClosed, $now, false],

// Learner (READ-access) STAFF, learners-visible (1,1): visible, subject to timing
[$isParticipant,  $staffRole,   $isLearner, $workTypeNormal,      $upcoming, $showClosed, $now, true],
[$isParticipant,  $staffRole,   $isLearner, $workTypeNormal,      $closed,   $hideClosed, $now, false],
[$notParticipant, $staffRole,   $isLearner, $workTypeNormal,      $upcoming, $showClosed, $now, true],
[$isParticipant,  $staffRole,   $isLearner, $workTypeNone,        $upcoming, $showClosed, $now, true],
[$notParticipant, $staffRole,   $isLearner, $workTypeNone,        $upcoming, $showClosed, $now, true],
```

- [ ] **Step 1.3: Run the new tests to confirm they fail**

```bash
./vendor/bin/phpunit library/Schoolbox/Learning/Assessment/Test/VisibilityTest.php
```

Expected: **FAIL** — the new learner rows fail because `isVisibleToUser()` still short-circuits on `isStaff()` and does not check learner status.

---

## Task 2: Restructure `Visibility.php`

**Files:**
- Modify: `library/Schoolbox/Learning/Assessment/Visibility.php`

This is the core logic change. It also renames `$respectSivo` throughout the file.

- [ ] **Step 2.1: Replace `isVisibleToUser()` entirely**

Replace the entire `isVisibleToUser()` method (lines 56–80) with:

```php
/**
 * Determines if the specified user can see an IAssessable (but not necessarily its details or participant interactions)
 *
 * Does not consider whether a parent can see the assessment because one of their children can. This is to ensure the caller
 * can distinguish the case where a parent can view the assessment themselves, vs seeing it due to their child's visibility.
 *
 * * Instructor               - can see anytime (so long as not past close and we are hiding closed)
 * * Learner (READ access)    - excluded if the work type hides learners; otherwise same as staff/participant rules
 * * Staff (non-learner)      - can see anytime (so long as not past close and we are hiding closed)
 * * NonStaff + NonInstructor - can only see if a participant, and after publish date, and not pure SIVO
 *
 * Staff      - is considered any user with role-type of 'staff'
 * Instructor - is considered any user with WRITE+ perm to folder
 * Learner    - is considered any user with READ perm to folder (overrides staff when work type hides learners)
 *
 * @param IAssessable              $assessment Must have plans and participants loaded if viewer is a participant
 * @param User                     $viewer
 * @param bool                     $showClosed Should we exclude assessments if their close date has passed?
 * @param Alaress_Date_Format|null $now
 * @param bool                     $respectWorkTypeVisibility
 * @return bool
 */
public function isVisibleToUser(IAssessable $assessment, User $viewer, bool $showClosed, ?Alaress_Date_Format $now = null, bool $respectWorkTypeVisibility = true): bool
{
    $now = $now ?? Alaress_Date_Format::now();

    try {
        $folderAccess = $this->access->getFolderAccess($viewer, $assessment->getBaseContext()->getFolder()->getId());
    } catch (MapperException $e) {
        return false;
    }

    // Instructor (WRITE+ access): always visible, subject only to timing
    if ($folderAccess >= UserAccess::ACCESS_WRITE) {
        return $this->isVisibleToInstructor($assessment, $viewer, $showClosed, $now);
    }

    // Learner (READ access): excluded if work type hides learners, even if viewer is staff
    if ($folderAccess === UserAccess::ACCESS_READ) {
        $workType = $assessment->getWorkType();
        if ($respectWorkTypeVisibility && $workType !== null && !$workType->isVisibleToLearners()) {
            return false;
        }
        if ($viewer->getRole()->isStaff()) {
            return $this->isVisibleToStaff($assessment, $viewer, $showClosed, $now);
        }
        return $this->isVisibleToNonInstructor($assessment, $viewer, $showClosed, $now, $respectWorkTypeVisibility);
    }

    // DENY access (non-learner, non-instructor): staff sees freely; others depend on participation
    if ($viewer->getRole()->isStaff()) {
        return $this->isVisibleToStaff($assessment, $viewer, $showClosed, $now);
    }
    return $this->isVisibleToNonInstructor($assessment, $viewer, $showClosed, $now, $respectWorkTypeVisibility);
}
```

- [ ] **Step 2.2: Rename `$respectSivo` in `isVisibleToUserOrChildOfUser()`**

Replace the docblock `@param bool $respectSivo` with `@param bool $respectWorkTypeVisibility` and rename the parameter in the signature and body:

```php
/**
 * Returns whether a user is permitted to see the existence of an assessment, or one of the user's children can.
 *
 * Use this when you don't need to know whether the user can see the assessment due to their own permissions, or
 * because of one of their children
 *
 * @param IAssessable              $assessment
 * @param User                     $viewer
 * @param bool                     $showClosed
 * @param Alaress_Date_Format|null $now
 * @param bool                     $respectWorkTypeVisibility
 * @return bool
 */
public function isVisibleToUserOrChildOfUser(IAssessable $assessment, User $viewer, bool $showClosed, ?Alaress_Date_Format $now = null, bool $respectWorkTypeVisibility = true): bool
{
    $now = $now ?? Alaress_Date_Format::now();

    if ($this->isVisibleToUser($assessment, $viewer, $showClosed, $now, $respectWorkTypeVisibility)) {
        return true;
    }
    if ($viewer->getRole()->isParent()) {
        foreach ($viewer->getChildren() as $child) {
            if ($this->isVisibleToUser($assessment, $child, $showClosed, $now, $respectWorkTypeVisibility)) {
                return true;
            }
        }
    }
    return false;
}
```

- [ ] **Step 2.3: Replace `isVisibleToNonInstructor()` entirely**

Replace the entire `isVisibleToNonInstructor()` method (lines 166–190) with:

```php
/**
 * Is the assessable visible to a non-instructor with DENY access (non-learner)?
 *
 * The learner exclusion check (READ access + work type hides learners) is handled upstream in isVisibleToUser().
 * This method handles only DENY-access users, plus learners whose work type allows learner visibility.
 *
 * @param IAssessable         $assessable
 * @param User                $nonInstructor
 * @param bool                $showClosed Should we show assessments after their closed date?
 * @param Alaress_Date_Format $now
 * @param bool                $respectWorkTypeVisibility
 * @return bool
 */
private function isVisibleToNonInstructor(IAssessable $assessable, User $nonInstructor, bool $showClosed, Alaress_Date_Format $now, bool $respectWorkTypeVisibility): bool
{
    $workType = $assessable->getWorkType();
    if ($respectWorkTypeVisibility && $workType !== null) {
        if (!$workType->isVisibleToLearners() && !$workType->isVisibleToParents()) {
            // Pure SIVO (0,0): only staff/instructors may see; non-staff non-learner excluded
            return false;
        }
    }
    $participant = $assessable->getParticipantIfExtant($nonInstructor);
    if (!$participant) {
        return false;
    }

    /**
     * Lesson Plans have some specific logic for visibility:
     * - Lesson Plans can be hidden from students via a Visibility switch
     * If you're a student, hide any lesson plans that were hidden from you
     * - Lesson Plans that are not hidden from students, should be visible to all students
     */
    if ($assessable instanceof LessonPlan\Model && \Schoolbox\Role::STUDENT !== $assessable->getVisibility()) {
        return false;
    }

    $closeDate   = $participant->getCloseDate();
    $isPublished = $now->gte($participant->getPublishDate());
    return $isPublished && ($showClosed || $now->lte($closeDate));
}
```

- [ ] **Step 2.4: Run the Visibility tests**

```bash
./vendor/bin/phpunit library/Schoolbox/Learning/Assessment/Test/VisibilityTest.php
```

Expected: **all tests pass**, including the new learner rows.

- [ ] **Step 2.5: Commit**

```bash
git add library/Schoolbox/Learning/Assessment/Test/VisibilityTest.php \
        library/Schoolbox/Learning/Assessment/Visibility.php
git commit -m "Restructure Visibility.php: learner access overrides staff when work type hides learners [SA-21973]"
```

---

## Task 3: Remove work type SQL filter from `Cursor.php`

**Files:**
- Modify: `library/Schoolbox/Learning/Moment/Feed/Cursor.php`

The `$filterWorkType` SQL subquery uses global role-type checks (`isStudent()`, `isParent()`) as proxies for folder-specific relationships. The Hydrator already calls `Visibility::isVisibleToUserOrChildOfUser()` on fully-hydrated assessments (after Task 2, this correctly handles the learner/parent check), so the SQL pre-filter is redundant and incorrect.

There is also a pre-existing bug on the current line 512: `is_visible_to_students` should be `is_visible_to_learners`. Removing the block fixes this bug incidentally.

- [ ] **Step 3.1: Remove `$filterWorkType` from `getDueWorkFeedbackQueryAndParams()`**

Replace the entire `getDueWorkFeedbackQueryAndParams()` method body from line 464 to the end of the method. The new method removes the `$filterWorkType` variable and its conditional SQL block, leaving only the `$visibility` / `mark_visibility` logic unchanged:

```php
private function getDueWorkFeedbackQueryAndParams(bool $countOnly): array
{
    $includeDueWorkFeedback = (
        count($this->filter['filterNames']) === 0 ||
        in_array('feedback', $this->filter['filterNames'], true)
    );

    if (
        !$includeDueWorkFeedback ||
        trim($this->filter['tag']) !== '' // Due work feedback cannot have tags, so if a tag filter exists, return no results.
    ) {
        return [null, []]; // This indicates that the query should not be run because we know that no results will be returned.
    }

    // Determine mark visibility.
    if ($this->viewer->getRole()->isStaff()) {
        $visibility = IAssessable::MARK_VISIBILITY_PRIVATE;
    } elseif ($this->viewer->getRole()->isStudent()) {
        $visibility = IAssessable::MARK_VISIBILITY_STUDENT;
    } elseif ($this->viewer->getRole()->isParent()) {
        $visibility = IAssessable::MARK_VISIBILITY_PARENT_STUDENT;
    } else {
        throw new UnexpectedValueException('Unexpected User: ' . $this->viewer->getId());
    }

    $query = 'SELECT ';
    $params = [];

    if ($countOnly) {
        $query .= 'COUNT(*) ';
    } else {
        $query .= (
            'submission_return.id           AS id,
             :discriminatorDueWorkFeedback  AS discriminator,
             submission_return.published_at AS date '
        );
        $params['discriminatorDueWorkFeedback'] = self::DISCRIMINATOR_DUE_WORK_FEEDBACK;
    }

    $query .= (
        'FROM `submission_return`
         JOIN `submission_box` ON `submission_return`.`box_id` = `submission_box`.`id`
         JOIN `assessment` ON `assessment`.`id` = `submission_box`.`assessment_id`
         WHERE `review_route` = :reviewRoute
         AND `submission_return`.`published_at` IS NOT NULL
         AND `submission_return`.`owner` = :userIdDueWorkFeedback
         AND `submission_box`.`mark_visibility` >= :markVisibility
         AND `submission_return`.`deleted_at` IS NULL
         AND `assessment`.`deleted_at` IS NULL '
    );
    $params['userIdDueWorkFeedback'] = $this->viewee->getId();
    $params['markVisibility']        = $visibility;
    $params['reviewRoute']           = ReturnedWork::REVIEW_ROUTE_INSTRUCTOR;

    return [$query, $params];
}
```

- [ ] **Step 3.2: Remove `$filterWorkType` from `getQuizFeedbackQueryAndParams()`**

Replace the `getQuizFeedbackQueryAndParams()` method body. The `$filterWorkType` block and its conditional SQL are removed; only `$visibility` / `mark_visibility` logic remains:

```php
private function getQuizFeedbackQueryAndParams(bool $countOnly): array
{
    $includeQuizFeedback = (
        count($this->filter['filterNames']) === 0 ||
        in_array('feedback', $this->filter['filterNames'], true)
    );

    if (
        !$includeQuizFeedback ||
        trim($this->filter['tag']) !== '' // Quiz feedback cannot have tags, so if a tag filter exists, return no results.
    ) {
        return [null, []]; // This indicates that the query should not be run because we know that no results will be returned.
    }

    // Determine mark visibility.
    if ($this->viewer->getRole()->isStaff()) {
        $visibility = IAssessable::MARK_VISIBILITY_PRIVATE;
    } elseif ($this->viewer->getRole()->isStudent()) {
        $visibility = IAssessable::MARK_VISIBILITY_STUDENT;
    } elseif ($this->viewer->getRole()->isParent()) {
        $visibility = IAssessable::MARK_VISIBILITY_PARENT_STUDENT;
    } else {
        throw new UnexpectedValueException('Unexpected User: ' . $this->viewer->getId());
    }

    $query = 'SELECT ';
    $params = [];

    if ($countOnly) {
        $query .= 'COUNT(*) ';
    } else {
        $query .= ("
            assess_attempt.attempt_id      AS id,
            :discriminatorQuizFeedback AS discriminator,
            assess_attempt.reviewed_at AS date
        ");

        $params['discriminatorQuizFeedback'] = self::DISCRIMINATOR_QUIZ_FEEDBACK;
    }

    $query .= ("
        FROM `assess_attempt`
        JOIN `assess` ON `assess_attempt`.`assess_id` = `assess`.`assess_id`
        JOIN `assessment` ON `assessment`.`id` = `assess`.`assessment_id`
        WHERE `assess_attempt`.`reviewed_at` IS NOT NULL
        AND `assess_attempt`.`user_id` = :userIdQuizFeedback
        AND `assess`.`mark_visibility` >= :markVisibility
        AND `assess_attempt`.`deleted_at` IS NULL
        AND `assessment`.`deleted_at` IS NULL
    ");
    $params['userIdQuizFeedback'] = $this->viewee->getId();
    $params['markVisibility']     = $visibility;

    return [$query, $params];
}
```

- [ ] **Step 3.3: Run the full test suite to check for regressions**

```bash
./vendor/bin/phpunit library/Schoolbox/
```

Expected: all tests pass.

- [ ] **Step 3.4: Commit**

```bash
git add library/Schoolbox/Learning/Moment/Feed/Cursor.php
git commit -m "Remove SQL work type filter from Cursor.php; Hydrator handles visibility [SA-21973]"
```

---

## Task 4: Update notifier files

**Files:**
- Modify: `library/Schoolbox/Assessment/Component/SubmissionBox/ReturnedWorkNotifier.php:136`
- Modify: `library/Schoolbox/Assessment/Component/SubmissionBox/Submission/SubmissionNotifier.php:323`
- Modify: `library/Schoolbox/Assessment/Participant/Plan/Notifier.php:302-303,326-327,1412`

`isStaffOrInstructorVisibleOnly()` returns `true` only for `(0,0)`. With the new 4-state model, the `(0,1)` case (`is_visible_to_learners=0, is_visible_to_parents=1`) no longer triggers it — a latent bug since the DB field was split.

### `ReturnedWorkNotifier.php`

This sends notifications to both learners and parents. Suppress only when nobody outside staff/instructors can see the assessment.

- [ ] **Step 4.1: Update `ReturnedWorkNotifier.php`**

At line 136, replace:
```php
if ($workType->isStaffOrInstructorVisibleOnly()) {
```
With:
```php
if (!$workType->isVisibleToLearners() && !$workType->isVisibleToParents()) {
```

### `SubmissionNotifier.php`

Same — sends notifications to learners and parents.

- [ ] **Step 4.2: Update `SubmissionNotifier.php`**

At line 323, replace:
```php
if ($worktype && $worktype->isStaffOrInstructorVisibleOnly()) {
```
With:
```php
if ($worktype && !$worktype->isVisibleToLearners() && !$worktype->isVisibleToParents()) {
```

### `Participant/Plan/Notifier.php`

There are three occurrences:

**Lines 302–303** — `$isCurrentHidden`: suppress notification for newly created assessments not visible to learners.

- [ ] **Step 4.3: Update `Participant/Plan/Notifier.php` line 302–303**

Replace:
```php
$isCurrentHidden = $assessment->getWorkType() === null ? false :
    $this->workTypeMapper->findById($assessment->getWorkType()->getId())->isStaffOrInstructorVisibleOnly();
```
With:
```php
$isCurrentHidden = $assessment->getWorkType() === null ? false :
    !$this->workTypeMapper->findById($assessment->getWorkType()->getId())->isVisibleToLearners();
```

**Lines 326–327** — `$isPreviousHidden`: same check for the previous work type state.

- [ ] **Step 4.4: Update `Participant/Plan/Notifier.php` line 326–327**

Replace:
```php
$isPreviousHidden = $diff['assessment']['workType'][1] === null ? false :
    $this->workTypeMapper->findById($diff['assessment']['workType'][1])->isStaffOrInstructorVisibleOnly();
```
With:
```php
$isPreviousHidden = $diff['assessment']['workType'][1] === null ? false :
    !$this->workTypeMapper->findById($diff['assessment']['workType'][1])->isVisibleToLearners();
```

**Line 1412** — `isNotifiableAtAll()`: suppress all participation-status notifications when learners can't see the assessment.

- [ ] **Step 4.5: Update `Participant/Plan/Notifier.php` line 1412**

Replace:
```php
if ($assessment->isStaffOrInstructorVisibleOnly()) {
```
With:
```php
if (!($assessment->getWorkType()?->isVisibleToLearners() ?? true)) {
```

(The `?? true` default: if there is no work type, treat as visible — consistent with the existing pattern of not filtering when `work_type_id` is null.)

- [ ] **Step 4.6: Run tests**

```bash
./vendor/bin/phpunit library/Schoolbox/
```

Expected: all tests pass.

- [ ] **Step 4.7: Commit**

```bash
git add library/Schoolbox/Assessment/Component/SubmissionBox/ReturnedWorkNotifier.php \
        library/Schoolbox/Assessment/Component/SubmissionBox/Submission/SubmissionNotifier.php \
        library/Schoolbox/Assessment/Participant/Plan/Notifier.php
git commit -m "Update notifiers: replace isStaffOrInstructorVisibleOnly() with new learner/parent visibility checks [SA-21973]"
```

---

## Task 5: Update `WorkType\Filter\Factory.php`

**Files:**
- Modify: `library/Schoolbox/Filter/Filters/Assessment/WorkType/Factory.php`

The current `buildComponent()` applies a role-type-based filter (`isStudent()`, `isParent()`) regardless of the viewer's actual folder relationship to the viewee. This needs folder-specific viewer classification.

- [ ] **Step 5.1: Add `use` statements and constructor dependencies**

At the top of the file, after the existing `use` statements, add:
```php
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
```

Update the constructor to inject the new dependencies:
```php
public function __construct(
    Filter\Page\Option\Mapper $optionMapper,
    Filter\Page\Option\Defaults\Mapper $defaultMapper,
    Filter\Selection\Mapper $selectionMapper,
    private WorkType\Mapper $workTypeMapper,
    private Access $access,
    private FolderMapper $folderMapper,
) {
    parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
}
```

- [ ] **Step 5.2: Add private helper `getMaxViewerAccessToVieweeFolders()`**

Add this private method at the end of the class (before the closing `}`):

```php
/**
 * Returns the highest folder access level the viewer has across all folders where the viewee is a learner (READ access).
 *
 * Used to classify the viewer's relationship to the viewee for work type visibility filtering:
 * - ACCESS_WRITE (or higher): viewer is an instructor in at least one of the viewee's folders
 * - ACCESS_READ: viewer is also a learner in at least one of the viewee's folders
 * - ACCESS_DENY: viewer has no folder relationship to the viewee
 *
 * @param User $viewer
 * @param User $viewee
 * @return int One of UserAccess::ACCESS_DENY, ACCESS_READ, or ACCESS_WRITE
 */
private function getMaxViewerAccessToVieweeFolders(User $viewer, User $viewee): int
{
    $vieweeFolderIds = $this->folderMapper->findFolderIdsForUser(
        $viewee,
        true,                      // include subjects
        UserAccess::ACCESS_READ,   // only folders where viewee is a learner
        true                       // exactAccess: exclude WRITE/FULL folders (those make viewee an instructor, not learner)
    );

    if (empty($vieweeFolderIds)) {
        return UserAccess::ACCESS_DENY;
    }

    $maxAccess = UserAccess::ACCESS_DENY;
    foreach ($vieweeFolderIds as $folderId) {
        $access = $this->access->getFolderAccess($viewer, $folderId);
        if ($access > $maxAccess) {
            $maxAccess = $access;
        }
        if ($maxAccess >= UserAccess::ACCESS_WRITE) {
            break; // Instructor access is the highest we care about
        }
    }
    return $maxAccess;
}
```

- [ ] **Step 5.3: Replace `buildComponent()` with viewer-classification logic**

Replace the entire `buildComponent()` method body:

```php
public function buildComponent(
    IDefinition $definition,
    User $user,
    \Schoolbox\Filter\Page\Model $pageFilter,
    ?GenericCollection $selections,
    ?User $viewee,
    array $routeArgs,
    GenericCollection $overrides
): IComponent {
    if (Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS === $pageFilter->getPage()) {
        $workTypes = $this->classifyViewerWorkTypes($user, $viewee);
    } elseif (Filter\Page\Model::PAGE_GRADES === $pageFilter->getPage()) {
        if ($user->getRole()->isStaff()) {
            // Staff on grades page: still apply learner check if staff user is enrolled as a learner
            $workTypes = $this->classifyViewerWorkTypes($user, $viewee);
        } else {
            $workTypes = $this->classifyViewerWorkTypes($user, $viewee);
        }
    } else {
        /* Everything else is a staff-only page */
        $workTypes = $this->workTypeMapper->findAll();
    }

    return new Component(
        $definition,
        $user,
        $pageFilter,
        $selections,
        $overrides[$pageFilter->getFilter()->getId()],
        $this->getSelectionMapper(),
        $workTypes
    );
}

/**
 * Classifies the viewer's relationship to the viewee and returns the appropriate set of visible work types.
 *
 * @param User      $user   The viewer
 * @param User|null $viewee The user whose assessments are being viewed
 * @return GenericCollection|WorkType\Model[]
 * @throws InvalidColumnException
 */
private function classifyViewerWorkTypes(User $user, ?User $viewee): GenericCollection
{
    // Case 1: viewer is looking at their own page — treat as learner
    if ($viewee === null || $user->getId() === $viewee->getId()) {
        return $this->workTypeMapper->findByPredicates(
            $this->workTypeMapper->getPredicateBuilder()->notEquals('is_visible_to_learners', 0)
        );
    }

    // Case 2: viewer is a parent of the viewee
    foreach ($user->getChildren() as $child) {
        if ($child->getId() === $viewee->getId()) {
            return $this->workTypeMapper->findByPredicates(
                $this->workTypeMapper->getPredicateBuilder()->notEquals('is_visible_to_parents', 0)
            );
        }
    }

    // Case 3: determine viewer's folder relationship to the viewee
    $maxAccess = $this->getMaxViewerAccessToVieweeFolders($user, $viewee);

    if ($maxAccess >= UserAccess::ACCESS_WRITE) {
        // Viewer is an instructor in one of the viewee's folders
        return $this->workTypeMapper->findAll();
    }

    if ($maxAccess === UserAccess::ACCESS_READ) {
        // Viewer is also a learner in one of the viewee's folders
        return $this->workTypeMapper->findByPredicates(
            $this->workTypeMapper->getPredicateBuilder()->notEquals('is_visible_to_learners', 0)
        );
    }

    if ($user->getRole()->isStaff()) {
        // Staff with no folder relationship to the viewee — still gets all work types
        return $this->workTypeMapper->findAll();
    }

    // No relevant relationship — empty collection
    return new GenericCollection(WorkType\Model::class);
}
```

Note: The two branches of `PAGE_GRADES` (`isStaff` vs non-staff) now both call `classifyViewerWorkTypes()`, so the `if/else` in `buildComponent()` is redundant. You can simplify:

```php
if (Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS === $pageFilter->getPage()
    || Filter\Page\Model::PAGE_GRADES === $pageFilter->getPage()
) {
    $workTypes = $this->classifyViewerWorkTypes($user, $viewee);
} else {
    /* Everything else is a staff-only page */
    $workTypes = $this->workTypeMapper->findAll();
}
```

Use the simplified form.

- [ ] **Step 5.4: Run the full test suite**

```bash
./vendor/bin/phpunit library/Schoolbox/
```

Expected: all tests pass.

- [ ] **Step 5.5: Commit**

```bash
git add library/Schoolbox/Filter/Filters/Assessment/WorkType/Factory.php
git commit -m "Update WorkType Filter Factory: folder-specific viewer classification for work type visibility [SA-21973]"
```

---

## Notes

- The parameter rename `$respectSivo` → `$respectWorkTypeVisibility` in `Visibility.php` is a public API change. All callers (`Hydrator.php` ×2, `Grades/Helper.php` ×1) pass this as a positional argument, so the rename is transparent to them.
- `isStaffOrInstructorVisibleOnly()` on `WorkType\Model`, `IAssessable`, and `AbstractAssessment` is deliberately left in place (marked deprecated). All callers are updated in this plan. Removing the method itself is out of scope.
- The `MARK_VISIBILITY` logic in `Cursor.php` (`submission_box.mark_visibility`) is a separate concern from work type visibility and is unchanged.
# SA-22271 Grades Feedback Visibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the grades page client-side instructor/student mark toggle with a server-side staff/student/parent view mode that recalculates activity visibility, feedback visibility, marks, averages, and graphs.

**Architecture:** Add a small grades view-mode model and resolver, then thread a `ViewContext` through the existing grades controller, helper, templates, and filter-panel AJAX route. Keep the change local to `Schoolbox\Learning\Grades` except for focused Playwright helper updates.

**Tech Stack:** PHP 8.4, Aura MVC controllers/routes, Twig templates, existing jQuery filter panel, PHPUnit, Playwright.

---

## File Structure

- Create `library/Schoolbox/Learning/Grades/ViewMode.php`: constants and validation for `staff`, `student`, and `parent` modes.
- Create `library/Schoolbox/Learning/Grades/ViewContext.php`: immutable data object for selected mode, effective viewer, and instructor-mark capability.
- Create `library/Schoolbox/Learning/Grades/ViewContextFactory.php`: resolves a `ViewContext` from current staff user, viewed student, query value, and parent relationships.
- Create `library/Schoolbox/Learning/Grades/Test/ViewModeTest.php`: unit tests for mode validation and labels.
- Create `library/Schoolbox/Learning/Grades/Test/ViewContextFactoryTest.php`: unit tests for staff/student/parent effective viewer behavior.
- Modify `library/Schoolbox/Learning/Grades/Controller.php`: resolve `ViewContext`, use its effective viewer in existing visibility/statistics call sites, pass it into templates.
- Modify `library/Schoolbox/Learning/Grades/Module.php`: inject `Schoolbox\External\User` into the grades controller.
- Replace `library/Schoolbox/Learning/Grades/templates/fragments/studentViewNavSwitcher.twig`: server-side view selector links instead of DOM mark toggling.
- Modify `library/Schoolbox/Learning/Grades/templates/summary.twig`: pass/preserve `viewMode`, render customisations link, use effective view context for assessment grade cards.
- Modify `library/Schoolbox/Learning/Grades/templates/gradesOuter.twig`: pass/preserve `viewMode`, include filter panel route with the query parameter.
- Modify `library/Schoolbox/Learning/Grades/templates/semesterSelector.twig`: append preserved query string to generated `data-href` values.
- Modify `templates/core/sub/subjectSelector.twig`: append preserved query string to generated `data-href` values.
- Modify `library/Schoolbox/Learning/Grades/templates/fragments/assessmentGrade.twig`: use `viewContext.viewer` for feedback checks and visible feedback.
- Modify `test/playwright/support/page/grades/GradesPage.ts`: update helpers from instructor/student DOM toggle to staff/student/parent navigation selector.
- Modify `test/playwright/acceptance/dueWork/markTypes.spec.ts`: update existing grades assertions and add mode persistence checks.

---

### Task 1: Add View Mode Model

**Files:**
- Create: `library/Schoolbox/Learning/Grades/ViewMode.php`
- Test: `library/Schoolbox/Learning/Grades/Test/ViewModeTest.php`

- [ ] **Step 1: Write the failing unit test**

Create `library/Schoolbox/Learning/Grades/Test/ViewModeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Grades\ViewMode;

class ViewModeTest extends TestCase
{
    #[DataProvider('validModeProvider')]
    public function testFromRequestValueAcceptsValidModes(string $input, string $expected): void
    {
        self::assertSame($expected, ViewMode::fromRequestValue($input));
    }

    public function testFromRequestValueDefaultsInvalidValuesToStaff(): void
    {
        self::assertSame(ViewMode::STAFF, ViewMode::fromRequestValue(null));
        self::assertSame(ViewMode::STAFF, ViewMode::fromRequestValue(''));
        self::assertSame(ViewMode::STAFF, ViewMode::fromRequestValue('guardian'));
    }

    #[DataProvider('labelProvider')]
    public function testLabelDescribesMode(string $mode, string $expected): void
    {
        self::assertSame($expected, ViewMode::label($mode));
    }

    public static function validModeProvider(): array
    {
        return [
            'staff' => ['staff', ViewMode::STAFF],
            'student' => ['student', ViewMode::STUDENT],
            'parent' => ['parent', ViewMode::PARENT],
        ];
    }

    public static function labelProvider(): array
    {
        return [
            'staff' => [ViewMode::STAFF, 'staff'],
            'student' => [ViewMode::STUDENT, 'student'],
            'parent' => [ViewMode::PARENT, 'parent'],
            'invalid fallback' => ['guardian', 'staff'],
        ];
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Learning/Grades/Test/ViewModeTest.php
```

Expected: FAIL because `Schoolbox\Learning\Grades\ViewMode` does not exist.

- [ ] **Step 3: Add the implementation**

Create `library/Schoolbox/Learning/Grades/ViewMode.php`:

```php
<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades;

final class ViewMode
{
    public const string STAFF = 'staff';
    public const string STUDENT = 'student';
    public const string PARENT = 'parent';

    public static function fromRequestValue(?string $value): string
    {
        return in_array($value, self::values(), true) ? $value : self::STAFF;
    }

    public static function label(string $mode): string
    {
        return match (self::fromRequestValue($mode)) {
            self::STUDENT => 'student',
            self::PARENT => 'parent',
            default => 'staff',
        };
    }

    public static function values(): array
    {
        return [self::STAFF, self::STUDENT, self::PARENT];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Learning/Grades/Test/ViewModeTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add library/Schoolbox/Learning/Grades/ViewMode.php library/Schoolbox/Learning/Grades/Test/ViewModeTest.php
git commit -m "Add grades view mode model [SA-22271]"
```

---

### Task 2: Add Effective View Context Resolver

**Files:**
- Create: `library/Schoolbox/Learning/Grades/ViewContext.php`
- Create: `library/Schoolbox/Learning/Grades/ViewContextFactory.php`
- Test: `library/Schoolbox/Learning/Grades/Test/ViewContextFactoryTest.php`

- [ ] **Step 1: Write the failing unit test**

Create `library/Schoolbox/Learning/Grades/Test/ViewContextFactoryTest.php`:

```php
<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\User as ExternalUser;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Grades\ViewContextFactory;
use Schoolbox\Learning\Grades\ViewMode;
use Schoolbox\Role;
use Schoolbox\User;

class ViewContextFactoryTest extends TestCase
{
    public function testStaffModeUsesCurrentUserAndAllowsInstructorMarks(): void
    {
        $currentUser = $this->user(1, Role::STAFF);
        $student = $this->user(2, Role::STUDENT);

        $context = (new ViewContextFactory($this->externalUser()))->create($currentUser, $student, ViewMode::STAFF);

        self::assertSame(ViewMode::STAFF, $context->getMode());
        self::assertSame($currentUser, $context->getViewer());
        self::assertTrue($context->canViewInstructorMarks());
    }

    public function testStudentModeUsesViewedStudentAndHidesInstructorMarks(): void
    {
        $currentUser = $this->user(1, Role::STAFF);
        $student = $this->user(2, Role::STUDENT);

        $context = (new ViewContextFactory($this->externalUser()))->create($currentUser, $student, ViewMode::STUDENT);

        self::assertSame(ViewMode::STUDENT, $context->getMode());
        self::assertSame($student, $context->getViewer());
        self::assertFalse($context->canViewInstructorMarks());
    }

    public function testParentModeUsesLoadedParentWhenAvailable(): void
    {
        $currentUser = $this->user(1, Role::STAFF);
        $student = $this->user(2, Role::STUDENT);
        $parent = $this->user(3, Role::PARENT);
        $parent->setChildren((new GenericCollection(User::class))->setOffset($student->getId(), $student));
        $student->setParents((new GenericCollection(User::class))->setOffset($parent->getId(), $parent));

        $context = (new ViewContextFactory($this->externalUser()))->create($currentUser, $student, ViewMode::PARENT);

        self::assertSame(ViewMode::PARENT, $context->getMode());
        self::assertSame($parent, $context->getViewer());
        self::assertFalse($context->canViewInstructorMarks());
        self::assertTrue($context->getViewer()->isParentOf($student));
    }

    public function testParentModeCreatesFallbackParentWhenNoRealParentExists(): void
    {
        $currentUser = $this->user(1, Role::STAFF);
        $student = $this->user(2, Role::STUDENT)->setParents(new GenericCollection(User::class));

        $context = (new ViewContextFactory($this->externalUser()))->create($currentUser, $student, ViewMode::PARENT);

        self::assertSame(ViewMode::PARENT, $context->getMode());
        self::assertTrue($context->getViewer()->getRole()->isParent());
        self::assertTrue($context->getViewer()->isParentOf($student));
    }

    private function user(int $id, string $roleType): User
    {
        return (new User())
            ->setId($id)
            ->setRole((new Role())->setType($roleType));
    }

    private function externalUser(): ExternalUser
    {
        return $this->createConfiguredMock(ExternalUser::class, [
            'loadParents' => [],
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Learning/Grades/Test/ViewContextFactoryTest.php
```

Expected: FAIL because `ViewContext` and `ViewContextFactory` do not exist.

- [ ] **Step 3: Add `ViewContext`**

Create `library/Schoolbox/Learning/Grades/ViewContext.php`:

```php
<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades;

use Schoolbox\User;

final class ViewContext
{
    public function __construct(
        private readonly string $mode,
        private readonly User $viewer,
        private readonly bool $canViewInstructorMarks,
    ) {
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getViewer(): User
    {
        return $this->viewer;
    }

    public function canViewInstructorMarks(): bool
    {
        return $this->canViewInstructorMarks;
    }

    public function getLabel(): string
    {
        return ViewMode::label($this->mode);
    }

    public function getQueryString(): string
    {
        return 'viewMode=' . rawurlencode($this->mode);
    }

    public function getQuerySuffix(): string
    {
        return '?' . $this->getQueryString();
    }
}
```

- [ ] **Step 4: Add `ViewContextFactory`**

Create `library/Schoolbox/Learning/Grades/ViewContextFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades;

use Schoolbox\External\User as ExternalUser;
use Schoolbox\GenericCollection;
use Schoolbox\Role;
use Schoolbox\User;

final class ViewContextFactory
{
    public function __construct(private readonly ExternalUser $externalUser)
    {
    }

    public function create(User $currentUser, User $student, ?string $requestedMode): ViewContext
    {
        $mode = ViewMode::fromRequestValue($requestedMode);

        if ($mode === ViewMode::STUDENT) {
            return new ViewContext($mode, $student, false);
        }

        if ($mode === ViewMode::PARENT) {
            return new ViewContext($mode, $this->resolveParentViewer($student), false);
        }

        return new ViewContext(ViewMode::STAFF, $currentUser, $currentUser->getRole()->isStaff());
    }

    private function resolveParentViewer(User $student): User
    {
        $this->externalUser->loadParents([$student]);
        $parents = $student->getParents();

        if ($parents !== null && $parents->count() > 0) {
            $parent = $parents->first();
            if ($parent instanceof User) {
                return $parent;
            }
        }

        $fallback = (new User())
            ->setId(0)
            ->setRole((new Role())->setType(Role::PARENT))
            ->setChildren(new GenericCollection(User::class));
        $fallback->getChildren()->setOffset($student->getId(), $student);

        return $fallback;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Learning/Grades/Test/ViewContextFactoryTest.php
```

Expected: PASS. If `GenericCollection::first()` is unavailable, replace that line with `current($parents->getAll()) ?: null` and rerun.

- [ ] **Step 6: Commit**

```bash
git add library/Schoolbox/Learning/Grades/ViewContext.php library/Schoolbox/Learning/Grades/ViewContextFactory.php library/Schoolbox/Learning/Grades/Test/ViewContextFactoryTest.php
git commit -m "Resolve grades effective view context [SA-22271]"
```

---

### Task 3: Thread View Context Through Controller

**Files:**
- Modify: `library/Schoolbox/Learning/Grades/Controller.php`
- Modify: `library/Schoolbox/Learning/Grades/Module.php`

- [ ] **Step 1: Inject the relationship hydrator**

In `Controller.php`, add imports:

```php
use Schoolbox\External\User as ExternalUser;
```

Add this constructor property after `$workTypeMapper`:

```php
private ExternalUser $externalUser,
```

In `Module.php`, add the final constructor argument in `buildController()`:

```php
new \Schoolbox\External\User(),
```

- [ ] **Step 2: Resolve view context in `getCustomGrades()`**

At the start of `getCustomGrades()`, after `$gradeHelper` is created, add:

```php
$viewContext = (new ViewContextFactory($this->externalUser))->create(
    $this->getUser(),
    $viewee,
    $this->requestQuery->get('viewMode')
);
$effectiveViewer = $viewContext->getViewer();
```

Change:

```php
[$folders, $markbooks, $assessments] = $gradeHelper->generateGrades($this->getUser(), $viewee, $term);
```

To:

```php
[$folders, $markbooks, $assessments] = $gradeHelper->generateGrades($effectiveViewer, $viewee, $term);
```

Change:

```php
$gradeHelper->filterAssessments($this->getUser(), $assessments, $filteredWorkTypes)
```

To:

```php
$gradeHelper->filterAssessments($effectiveViewer, $assessments, $filteredWorkTypes)
```

Pass these template variables into `summary.twig`:

```php
'viewContext' => $viewContext,
'viewModeQuerySuffix' => $viewContext->getQuerySuffix(),
```

- [ ] **Step 3: Resolve view context in `getGradesForSubject()`**

Before building `$filterPanel`, add:

```php
$viewContext = (new ViewContextFactory($this->externalUser))->create(
    $this->getUser(),
    $viewee,
    $this->requestQuery->get('viewMode')
);
```

Change the `$route` generation to append the query suffix:

```php
$route = $this->routeGenerator->generate('learningGrades.postReport', [
    'userId'    => $viewee->getId(),
    'termCode'  => $term->getCode(),
    'subjectId' => $subject->getId(),
]) . $viewContext->getQuerySuffix();
```

Change `new Markbook(..., $this->getUser())` to:

```php
new Markbook($assessments, $overallMarkScheme, $viewContext->getViewer())
```

Pass these variables into `gradesOuter.twig`:

```php
'viewContext' => $viewContext,
'viewModeQuerySuffix' => $viewContext->getQuerySuffix(),
```

- [ ] **Step 4: Resolve view context in `postReport()`**

Near the top of `postReport()`, after `$page`, add:

```php
$viewContext = (new ViewContextFactory($this->externalUser))->create(
    $this->getUser(),
    $viewee,
    $this->requestQuery->get('viewMode')
);
$effectiveViewer = $viewContext->getViewer();
```

Use `$effectiveViewer` in these existing call sites:

```php
$visibility->isVisibleToUser($a, $effectiveViewer, true)
```

```php
$hasPermissionsAndSettings =
    $viewContext->getMode() === ViewMode::STAFF
    || ($viewee->getDisplayGradeGraph() && $this->isTabEnabled('graph', $subject));
```

```php
$overallMarkScheme = $markSchemeFactory->spawnForFolder(
    $subject,
    $viewContext->getMode() === ViewMode::STAFF
);
$filteredMarkbook = (new Markbook($assessments, $overallMarkScheme, $effectiveViewer))
    ->sortAssessables(new AssessmentComparator($viewee));
```

```php
$graph = $gradeHelper->buildGraph(
    $filteredMarkbook,
    $viewee,
    $effectiveViewer,
    $this->getConfigLoader()->get('grade_gradient_enable'),
    $workTypeIds
);
```

```php
$includeComparison = $viewContext->getMode() === ViewMode::STAFF || $viewee->getDisplayGradeGraph();
```

Pass these variables into `gradesInner.twig`:

```php
'viewContext' => $viewContext,
'canViewInstructorMarks' => $viewContext->canViewInstructorMarks(),
```

- [ ] **Step 5: Run focused PHP checks**

Run:

```bash
./vendor/bin/phpcs library/Schoolbox/Learning/Grades/Controller.php library/Schoolbox/Learning/Grades/Module.php
./vendor/bin/phpstan analyze library/Schoolbox/Learning/Grades/Controller.php library/Schoolbox/Learning/Grades/Module.php
```

Expected: both pass. Fix type/import issues before continuing.

- [ ] **Step 6: Commit**

```bash
git add library/Schoolbox/Learning/Grades/Controller.php library/Schoolbox/Learning/Grades/Module.php
git commit -m "Apply grades view context server side [SA-22271]"
```

---

### Task 4: Replace Header Switcher With Server-Side Navigation

**Files:**
- Modify: `library/Schoolbox/Learning/Grades/templates/fragments/studentViewNavSwitcher.twig`
- Modify: `library/Schoolbox/Learning/Grades/templates/summary.twig`
- Modify: `library/Schoolbox/Learning/Grades/templates/gradesOuter.twig`
- Modify: `library/Schoolbox/Learning/Grades/templates/semesterSelector.twig`
- Modify: `templates/core/sub/subjectSelector.twig`

- [ ] **Step 1: Replace the switcher fragment**

Replace `studentViewNavSwitcher.twig` with:

```twig
{# Server-side grades view selector. Expects viewContext and currentRouteBase. #}
<li class="separator">Viewing as {{ viewContext.mode|default('staff') }}</li>
{% for mode in ['staff', 'student', 'parent'] %}
    {% if viewContext.mode != mode %}
        <li>
            <a class="icon-view" href="{{ currentRouteBase }}?viewMode={{ mode }}" data-grades-view-mode="{{ mode }}">
                View as {{ mode|capitalize }}
            </a>
        </li>
    {% endif %}
{% endfor %}
```

If Twig cannot access `viewContext.mode`, use `viewContext.getMode()`.

- [ ] **Step 2: Update summary header meta and menu include**

In `summary.twig`, replace:

```twig
<li data-has-student-view-meta class="icon-teacher">Teacher view</li>
```

With:

```twig
<li data-grades-view-mode-meta class="icon-view">Viewing as {{ viewContext.getLabel() }}</li>
```

Before including the switcher, define:

```twig
{% set currentRouteBase = routeLookup('learningGrades.getGrades', {viewee: student, userId: student.id, term: term, termCode: term.code}) %}
{% include '@learningGrades/fragments/studentViewNavSwitcher.twig' with {
    viewContext: viewContext,
    currentRouteBase: currentRouteBase
} only %}
```

- [ ] **Step 3: Update subject page header meta and menu include**

In `gradesOuter.twig`, make the same meta replacement and set:

```twig
{% set currentRouteBase = routeLookup('learningGrades.getGradesForSubject', {
    viewee: student,
    userId: student.id,
    term: term,
    termCode: term.code,
    subject: subject,
    subjectId: subject.id
}) %}
{% include '@learningGrades/fragments/studentViewNavSwitcher.twig' with {
    viewContext: viewContext,
    currentRouteBase: currentRouteBase
} only %}
```

- [ ] **Step 4: Preserve query suffix through term selector**

In `semesterSelector.twig`, add to the usage block:

```twig
viewModeQuerySuffix: string - optional query string suffix to append to generated URLs
```

For each `data-href`, append `{{ viewModeQuerySuffix ?? '' }}`. Example:

```twig
<option data-href="{{ routeLookup(route, routeArgs|merge({termCode: term.code})) }}{{ viewModeQuerySuffix ?? '' }}" ...>
```

Update summary and subject includes to pass `viewModeQuerySuffix`.

- [ ] **Step 5: Preserve query suffix through subject selector**

In `templates/core/sub/subjectSelector.twig`, append `{{ viewModeQuerySuffix ?? '' }}` to the generated `data-href`.

Update summary and subject includes to pass `viewModeQuerySuffix`.

- [ ] **Step 6: Run template smoke lint via PHP checks**

Run:

```bash
./vendor/bin/phpcs library/Schoolbox/Learning/Grades/templates/fragments/studentViewNavSwitcher.twig library/Schoolbox/Learning/Grades/templates/summary.twig library/Schoolbox/Learning/Grades/templates/gradesOuter.twig library/Schoolbox/Learning/Grades/templates/semesterSelector.twig templates/core/sub/subjectSelector.twig
```

Expected: PHPCS may not inspect Twig; if it reports unsupported files, note that and continue to browser tests later.

- [ ] **Step 7: Commit**

```bash
git add library/Schoolbox/Learning/Grades/templates/fragments/studentViewNavSwitcher.twig library/Schoolbox/Learning/Grades/templates/summary.twig library/Schoolbox/Learning/Grades/templates/gradesOuter.twig library/Schoolbox/Learning/Grades/templates/semesterSelector.twig templates/core/sub/subjectSelector.twig
git commit -m "Replace grades mark toggle with view selector [SA-22271]"
```

---

### Task 5: Render Marks And Feedback With Effective Viewer

**Files:**
- Modify: `library/Schoolbox/Learning/Grades/templates/fragments/assessmentGrade.twig`
- Modify: `library/Schoolbox/Learning/Grades/templates/summary.twig`
- Modify: `library/Schoolbox/Learning/Grades/templates/coreGradesInner.twig`

- [ ] **Step 1: Pass view context to assessment grade includes**

In `summary.twig` and `coreGradesInner.twig`, add `viewContext: viewContext` to every include of `@learningGrades/fragments/assessmentGrade.twig`.

- [ ] **Step 2: Use effective viewer inside assessment grade fragment**

In `assessmentGrade.twig`, set a local viewer near the top:

```twig
{% set gradesViewer = viewContext is defined ? viewContext.viewer : USER %}
```

Replace both checks:

```twig
assessment.isFeedbackVisibleToUser(USER)
```

With:

```twig
assessment.isFeedbackVisibleToUser(gradesViewer)
```

Replace:

```twig
{% set feedback = participant.findAuthoritativeVisibleFeedback(USER) %}
```

With:

```twig
{% set feedback = participant.findAuthoritativeVisibleFeedback(gradesViewer) %}
```

Keep instructor marks guarded by `canViewInstructorMarks`, which now comes from `ViewContext`.

- [ ] **Step 3: Run focused template/browser smoke manually**

Start the app if needed using the project Docker workflow, then visit:

```text
/learning/grades/25?viewMode=staff
/learning/grades/25?viewMode=student
/learning/grades/25?viewMode=parent
/learning/grades/25/2019-1/83?viewMode=staff
/learning/grades/25/2019-1/83?viewMode=student
/learning/grades/25/2019-1/83?viewMode=parent
```

Expected: pages render without Twig errors; header meta reflects selected mode; subject report loads via AJAX in the selected mode.

- [ ] **Step 4: Commit**

```bash
git add library/Schoolbox/Learning/Grades/templates/fragments/assessmentGrade.twig library/Schoolbox/Learning/Grades/templates/summary.twig library/Schoolbox/Learning/Grades/templates/coreGradesInner.twig
git commit -m "Render grades feedback for effective viewer [SA-22271]"
```

---

### Task 6: Add Summary Customisations Link

**Files:**
- Modify: `library/Schoolbox/Learning/Grades/Controller.php`
- Modify: `library/Schoolbox/Learning/Grades/templates/summary.twig`

- [ ] **Step 1: Add route data in `getCustomGrades()`**

Add a conservative template value that can be populated once the final customisation log route is confirmed:

```php
'customisationsRoute' => null,
```

Then inspect SA-20909 implementation in the codebase. If a direct route exists, replace `null` with `routeLookupIfAuthorized` data from the controller. If only assessment-level participation routes exist, keep the route null for the first implementation and record the gap in the PR notes rather than adding a misleading link.

- [ ] **Step 2: Render only when a route exists**

In `summary.twig`, near the student profile card actions, add:

```twig
{% if customisationsRoute %}
    <a class="button icon-view" href="{{ customisationsRoute }}" data-test="grades-view-customisations">
        View Customisations
    </a>
{% endif %}
```

- [ ] **Step 3: Verify manually**

If `customisationsRoute` remains null, verify there is no button. If the route exists, verify the link is visible only on `/learning/grades/{userId}`, not on the subject page.

- [ ] **Step 4: Commit**

```bash
git add library/Schoolbox/Learning/Grades/Controller.php library/Schoolbox/Learning/Grades/templates/summary.twig
git commit -m "Prepare grades customisations link [SA-22271]"
```

---

### Task 7: Update Playwright Coverage

**Files:**
- Modify: `test/playwright/support/page/grades/GradesPage.ts`
- Modify: `test/playwright/acceptance/dueWork/markTypes.spec.ts`

- [ ] **Step 1: Update page object selectors and helpers**

In `GradesPage.ts`, replace the old `SELECTOR_VIEW_INSTRUCTOR_GRADES` and `SELECTOR_VIEW_STUDENT_GRADES` helpers with:

```ts
static readonly SELECTOR_VIEW_MODE_META = "[data-grades-view-mode-meta]";

async viewAs(mode: "staff" | "student" | "parent"): Promise<void> {
    await this.clickJellyTot();
    await this.page.locator(`[data-grades-view-mode="${mode}"]`).click();
}

async expectGradesViewMode(mode: "staff" | "student" | "parent"): Promise<void> {
    await expect(this.page.locator(GradesPage.SELECTOR_VIEW_MODE_META)).toContainText(`Viewing as ${mode}`);
}
```

Keep compatibility wrappers for existing tests:

```ts
async viewInstructorGrades(): Promise<void> {
    await this.viewAs("staff");
}

async viewStudentGrades(): Promise<void> {
    await this.viewAs("student");
}

async expectGradesVisibility(expectedVisibility: "student" | "instructor"): Promise<void> {
    await this.expectGradesViewMode(expectedVisibility === "student" ? "student" : "staff");
}
```

- [ ] **Step 2: Update existing mark type test assertions**

In `markTypes.spec.ts`, keep the existing staff/student mark assertions but update the wording from instructor/student grades to staff/student view mode where relevant.

Add a parent mode smoke step after student mode:

```ts
await test.step("Parent View Mode", async () => {
    await gradesPage.viewAs("parent");
    await gradesPage.expectGradesViewMode("parent");
});
```

Do not assert exact parent marks until fixture data has a known parent-visible-only contrast; the unit tests cover mode resolution and the existing mark visibility tests cover role visibility rules.

- [ ] **Step 3: Run frontend checks**

Run:

```bash
npx eslint test/playwright/support/page/grades/GradesPage.ts test/playwright/acceptance/dueWork/markTypes.spec.ts --max-warnings 0
npx prettier --check test/playwright/support/page/grades/GradesPage.ts test/playwright/acceptance/dueWork/markTypes.spec.ts
```

Expected: both pass. Run `npx prettier --write ...` if formatting fails, then rerun checks.

- [ ] **Step 4: Run Playwright scenario**

Run the focused test:

```bash
npx playwright test test/playwright/acceptance/dueWork/markTypes.spec.ts
```

Expected: PASS. If the full scenario is too slow for local iteration, run the grades-related tests first using a grep and then run the full file before completion.

- [ ] **Step 5: Commit**

```bash
git add test/playwright/support/page/grades/GradesPage.ts test/playwright/acceptance/dueWork/markTypes.spec.ts
git commit -m "Cover grades view modes [SA-22271]"
```

---

### Task 8: Final Verification

**Files:**
- All changed files from previous tasks.

- [ ] **Step 1: Run backend style and static analysis**

Run:

```bash
./vendor/bin/phpcs library/Schoolbox/Learning/Grades
./vendor/bin/phpstan analyze library/Schoolbox/Learning/Grades
```

Expected: PASS.

- [ ] **Step 2: Run focused PHPUnit tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Learning/Grades/Test
```

Expected: PASS.

- [ ] **Step 3: Run frontend checks**

Run:

```bash
npx eslint test/playwright/support/page/grades/GradesPage.ts test/playwright/acceptance/dueWork/markTypes.spec.ts --max-warnings 0
npx prettier --check test/playwright/support/page/grades/GradesPage.ts test/playwright/acceptance/dueWork/markTypes.spec.ts
```

Expected: PASS.

- [ ] **Step 4: Run focused browser test**

Run:

```bash
npx playwright test test/playwright/acceptance/dueWork/markTypes.spec.ts
```

Expected: PASS.

- [ ] **Step 5: Docker final verification**

If Docker is available, run final backend verification inside `docker/ssh` per `AGENTS.md`:

```bash
docker/ssh
./vendor/bin/phpunit library/Schoolbox/Learning/Grades/Test
./vendor/bin/phpcs library/Schoolbox/Learning/Grades
./vendor/bin/phpstan analyze library/Schoolbox/Learning/Grades
```

Expected: PASS. Record any unavailable Docker environment as a verification limitation in the final handoff.

- [ ] **Step 6: Final commit if verification required fixes**

If verification required any fixes, commit them:

```bash
git add <fixed-files>
git commit -m "Fix grades view mode verification issues [SA-22271]"
```

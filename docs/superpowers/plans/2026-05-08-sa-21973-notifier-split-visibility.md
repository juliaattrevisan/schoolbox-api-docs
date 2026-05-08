# SA-21973 Notifier Split Visibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make assessment plan notifications respect learner and parent work type visibility as separate audiences.

**Architecture:** Keep the existing shared scheduler labels for future notifications, but make visibility transition decisions explicit for learner and parent audiences. Fix feed generation so it only builds messages for the feed viewer's audience instead of building learner and parent feed entries together. Keep the change local to the assessment plan notifier and focused tests.

**Tech Stack:** PHP 8.4, PHPUnit, Schoolbox realtime notification classes, Schoolbox assessment work type models.

---

## File Structure

- Modify: `library/Schoolbox/Assessment/Participant/Plan/Notifier.php`
  - Add explicit learner/parent visibility state helpers.
  - Use those helpers in `bundleTriggers()` instead of the current `$isCurrentHidden` / `$isPreviousHidden` aggregate-only flow.
  - Change feed-only open/closed notification generation so it knows the requesting feed audience.
- Create: `library/Schoolbox/Assessment/Participant/Plan/Test/NotifierVisibilityTest.php`
  - PHPUnit coverage for visibility transition scheduling and feed audience selection.
- Optional follow-up only if test setup proves too large: extract small private helper methods in `Notifier.php`; do not introduce new production classes unless the tests show the notifier becomes unreadable.

## Behaviour Matrix

Use these definitions throughout the implementation:

```text
SI   = learners=false, parents=false
SIL  = learners=true,  parents=false
SIP  = learners=false, parents=true
SILP = learners=true,  parents=true
```

Expected scheduling decisions:

```text
CREATE SI   => no open/upcoming/overdue/closed triggers
CREATE SIL  => shared open/upcoming/overdue/closed triggers
CREATE SIP  => shared open/upcoming/overdue/closed triggers
CREATE SILP => shared open/upcoming/overdue/closed triggers

UPDATE SI   -> SIL/SIP/SILP => attach shared open/upcoming/overdue/closed triggers
UPDATE SIL/SIP/SILP -> SI   => detach shared open/upcoming/updated/overdue/closed triggers and attach deleted
UPDATE SIL -> SIP           => no global deleted; learner audience became hidden, parent became visible
UPDATE SIP -> SIL           => no global deleted; parent audience became hidden, learner became visible
UPDATE SIL -> SILP          => parent became visible; learner remains visible
UPDATE SIP -> SILP          => learner became visible; parent remains visible
UPDATE SILP -> SIL          => parent became hidden; learner remains visible
UPDATE SILP -> SIP          => learner became hidden; parent remains visible
```

The first implementation should not add audience-specific retraction unless a failing test proves current stored-message retraction is required. Future scheduled sends already re-check current visibility when the trigger fires, so the immediate blocker is avoiding aggregate decisions that suppress or globally delete the wrong cases.

## Task 1: Add Test Harness For Visibility Transitions

**Files:**
- Create: `library/Schoolbox/Assessment/Participant/Plan/Test/NotifierVisibilityTest.php`
- Read: `library/Schoolbox/Realtime/Schedule/Trigger/TriggerBundleInterface.php`
- Read: `library/Schoolbox/Realtime/Schedule/Event/Event.php`

- [ ] **Step 1: Create a capturing trigger bundle**

Add this helper inside the new test file:

```php
final class CapturingTriggerBundle implements TriggerBundleInterface
{
    /** @var array<string,int|callable> */
    public array $attached = [];

    /** @var string[] */
    public array $detached = [];

    public function __construct(
        private Event $event,
        private string $method
    ) {
    }

    public function attach($label, $timestamp): void
    {
        $this->attached[$label] = $timestamp;
    }

    public function attachEach($array): void
    {
        foreach ($array as $label => $timestamp) {
            $this->attach($label, $timestamp);
        }
    }

    public function detach($label): void
    {
        $this->detached[] = $label;
    }

    public function detachEach($array): void
    {
        foreach ($array as $label) {
            $this->detach($label);
        }
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getTriggers(): array
    {
        return [];
    }
}
```

- [ ] **Step 2: Add work type fixture helper**

Add this helper in the test class:

```php
private function workType(int $id, bool $learners, bool $parents): WorkType\Model
{
    return (new WorkType\Model())
        ->setId($id)
        ->setIsVisibleToLearners($learners)
        ->setIsVisibleToParents($parents);
}
```

- [ ] **Step 3: Add notifier fixture helper**

Use a mocked `WorkTypeMapper` so `bundleTriggers()` can resolve previous and current work type IDs without touching the database:

```php
private function notifierWithWorkTypes(array $workTypes): Notifier
{
    $workTypeMapper = $this->createMock(WorkTypeMapper::class);
    $workTypeMapper
        ->method('findById')
        ->willReturnCallback(fn (int $id) => $workTypes[$id]);

    return new Notifier(
        $this->createStub(\Schoolbox\IConfigLoader::class),
        [
            'workTypeMapper' => $workTypeMapper,
            'nsMapper' => $this->createStub(NSMapper::class),
            'srcMapper' => $this->createStub(SourceMapper::class),
            'mapper' => $this->createStub(Mapper::class),
        ]
    );
}
```

- [ ] **Step 4: Add plan/event fixture helper**

Build a minimal due work assessment plan with open/due/close dates in the future:

```php
private function bundleForWorkTypeChange(
    Notifier $notifier,
    WorkType\Model $currentWorkType,
    ?int $previousWorkTypeId
): CapturingTriggerBundle {
    $now = Alaress_Date_Format::now();

    $dueWork = (new \Schoolbox\Assessment\Component\SubmissionBox())
        ->setId(101);

    $assessment = (new \Schoolbox\Learning\Assessment\DataModel\Model())
        ->setId(201)
        ->setDueWork($dueWork)
        ->setWorkType($currentWorkType)
        ->setOpenDate((clone $now)->addHours(1))
        ->setDueDate((clone $now)->addHours(3))
        ->setCloseDate((clone $now)->addHours(4));

    $plan = (new Model())
        ->setId(301)
        ->setAssessment($assessment)
        ->setIsDynamic(Mapper::PLAN_TYPE_DYNAMIC);

    $event = new Event(
        new User(),
        \Schoolbox\Mapper::METHOD_UPDATE,
        $plan,
        $this->createStub(\Schoolbox\Mapper::class),
        [
            'assessment' => [
                'workType' => [$currentWorkType->getId(), $previousWorkTypeId],
            ],
        ]
    );

    return new CapturingTriggerBundle($event, \Schoolbox\Mapper::METHOD_UPDATE);
}
```

If `SubmissionBox` or `DataModel\Model` setter chaining differs, keep the same shape but split setter calls into separate statements. Do not replace this with database fixtures.

- [ ] **Step 5: Write transition tests**

Add data-provider tests for the matrix above. These assertions are the important part:

```php
self::assertSame($expectedAttachedLabels, array_keys($bundle->attached));
self::assertSame($expectedDetachedLabels, $bundle->detached);
```

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Assessment/Participant/Plan/Test/NotifierVisibilityTest.php
```

Expected before implementation: at least `SIL -> SIP`, `SIP -> SIL`, `SILP -> SIL`, and `SILP -> SIP` fail because the current code collapses partial transitions into one `$isCurrentHidden` value.

## Task 2: Replace Aggregate Hidden State With Audience State

**Files:**
- Modify: `library/Schoolbox/Assessment/Participant/Plan/Notifier.php`
- Test: `library/Schoolbox/Assessment/Participant/Plan/Test/NotifierVisibilityTest.php`

- [ ] **Step 1: Add a work type model import**

Add near the existing `WorkTypeMapper` import:

```php
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;
```

- [ ] **Step 2: Add visibility helpers**

Add these private helpers near `isNotifiableAtAll()`:

```php
/**
 * @return array{learners: bool, parents: bool}
 */
private function getNotificationAudienceVisibility(?WorkType $workType): array
{
    return [
        'learners' => $workType?->isVisibleToLearners() ?? true,
        'parents' => $workType?->isVisibleToParents() ?? true,
    ];
}

/**
 * @param array{learners: bool, parents: bool} $visibility
 */
private function isVisibleToAnyNotificationAudience(array $visibility): bool
{
    return $visibility['learners'] || $visibility['parents'];
}
```

- [ ] **Step 3: Use helpers for current visibility**

Replace the current `$isCurrentHidden` block with:

```php
$currentAudienceVisibility = $this->getNotificationAudienceVisibility($assessment->getWorkType());
$isCurrentVisibleToAnyAudience = $this->isVisibleToAnyNotificationAudience($currentAudienceVisibility);

if ($method === Mapper::METHOD_CREATE && !$isCurrentVisibleToAnyAudience) {
    return;
}
```

Keep the existing close-date create suppression immediately after this block.

- [ ] **Step 4: Use helpers for previous visibility**

Replace `$isPreviousHidden` with:

```php
$previousAudienceVisibility = ['learners' => true, 'parents' => true];
$isPreviousVisibleToAnyAudience = true;
```

Inside the existing work type diff block, load the previous work type if present and calculate:

```php
if ($diff['assessment']['workType'][1] !== null) {
    $previousWorkType = $this->workTypeMapper->findById($diff['assessment']['workType'][1]);
    $previousAudienceVisibility = $this->getNotificationAudienceVisibility($previousWorkType);
}
$isPreviousVisibleToAnyAudience = $this->isVisibleToAnyNotificationAudience($previousAudienceVisibility);
```

- [ ] **Step 5: Update transition branches**

Replace aggregate hidden branches with:

```php
if (!$isCurrentClosed && $isPreviousVisibleToAnyAudience && !$isCurrentVisibleToAnyAudience) {
    $bundle->detachEach([
        self::TRIGGER_OPEN,
        self::TRIGGER_UPCOMING,
        self::TRIGGER_UPDATED,
        self::TRIGGER_OVERDUE,
        self::TRIGGER_CLOSED,
    ]);
    $bundle->attach(self::TRIGGER_DELETED, $now);
    return;
}
```

And later:

```php
if (
    !$isCurrentClosed
    && (
        $method === Mapper::METHOD_CREATE
        || (!$isPreviousVisibleToAnyAudience && $isCurrentVisibleToAnyAudience)
    )
) {
    $bundle->attach(self::TRIGGER_OPEN, $triggerOpenTime);
    $bundle->attach(self::TRIGGER_UPCOMING, $upcomingDate->getUnixTimeStamp());
    $bundle->attach(self::TRIGGER_OVERDUE, $dueDate->getUnixTimeStamp());
    $bundle->attach(self::TRIGGER_CLOSED, $closeDate->getUnixTimeStamp());
}
```

Do not add a global `deleted` trigger for partial audience transitions.

- [ ] **Step 6: Run focused tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Assessment/Participant/Plan/Test/NotifierVisibilityTest.php
```

Expected: transition tests pass.

## Task 3: Make Feed Generation Audience-Aware

**Files:**
- Modify: `library/Schoolbox/Assessment/Participant/Plan/Notifier.php`
- Test: `library/Schoolbox/Assessment/Participant/Plan/Test/NotifierVisibilityTest.php` or `library/Schoolbox/Test/FeedTest.php`

- [ ] **Step 1: Add feed audience constants**

Add near the trigger constants:

```php
private const FEED_AUDIENCE_LEARNER = 'learner';
private const FEED_AUDIENCE_PARENT = 'parent';
private const FEED_AUDIENCE_STAFF = 'staff';
```

- [ ] **Step 2: Change internal feed method signatures**

Change:

```php
private function internalNotifyOpen(IAssessable $assessment, Model $plan, bool $forFeed): Envelope
private function internalNotifyClosed(IAssessable $assessment, Model $plan, bool $forFeed): Envelope
```

to:

```php
private function internalNotifyOpen(IAssessable $assessment, Model $plan, ?string $feedAudience = null): Envelope
private function internalNotifyClosed(IAssessable $assessment, Model $plan, ?string $feedAudience = null): Envelope
```

Use `$feedAudience !== null` where the old code used `$forFeed`.

- [ ] **Step 3: Preserve scheduled notification behaviour**

Update public callers:

```php
public function notifyOpen(Model $plan): Envelope
{
    return $this->internalNotifyOpen($this->retrieveAssessmentDomainModel($plan), $plan);
}

public function notifyClosed(Model $plan): Envelope
{
    return $this->internalNotifyClosed($this->retrieveAssessmentDomainModel($plan), $plan);
}
```

This keeps scheduled notifications building learner/parent/staff messages as they do today, gated by `isLearnerNotifiable()` and `isParentNotifiable()`.

- [ ] **Step 4: Pass viewer audience from `getFeed()`**

Add:

```php
private function getFeedAudience(User $user, IAssessable $assessment): string
{
    if ($user->getRole()->isParent()) {
        return self::FEED_AUDIENCE_PARENT;
    }

    if (Access::getInstance()->getFolderAccess($user, $assessment->getBaseContext()->getFolder()) >= UserAccess::ACCESS_WRITE) {
        return self::FEED_AUDIENCE_STAFF;
    }

    return self::FEED_AUDIENCE_LEARNER;
}
```

In `getFeed()`, before calling `internalNotifyOpen()` and `internalNotifyClosed()`:

```php
$feedAudience = $this->getFeedAudience($user, $assessment);
```

Then call:

```php
$notifsForPlans["$key.open"] = $this
    ->internalNotifyOpen($assessment, $dataModelPlan, $feedAudience)
    ->getAll($openDate?->getUnixTimeStamp());
$notifsForPlans["$key.closed"] = $this
    ->internalNotifyClosed($assessment, $dataModelPlan, $feedAudience)
    ->getAll($dueDate?->getUnixTimeStamp());
```

- [ ] **Step 5: Gate feed entries by audience**

Inside `internalNotifyOpen()`:

```php
$forFeed = $feedAudience !== null;
```

Only call `internalNotifyLearnerOpen()` when:

```php
!$forFeed || $feedAudience === self::FEED_AUDIENCE_LEARNER
```

Only build parent feed messages when:

```php
$forFeed
&& $feedAudience === self::FEED_AUDIENCE_PARENT
&& $this->isParentNotifiable($assessment)
```

Only build staff feed messages when:

```php
$forFeed
&& $feedAudience === self::FEED_AUDIENCE_STAFF
&& $participantCount > 0
```

Apply the same audience gating in `internalNotifyClosed()`.

- [ ] **Step 6: Add tests for feed audience isolation**

Add tests that assert:

```text
learner feed for SIL includes learner title only
learner feed for SIP is empty
parent feed for SIP includes parent title only
parent feed for SIL is empty
staff feed for SIL/SIP/SILP includes staff status messages only
```

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Assessment/Participant/Plan/Test/NotifierVisibilityTest.php
```

Expected: feed audience tests fail before this task and pass after this task.

## Task 4: Re-check Notification Recipient Gating

**Files:**
- Modify only if tests fail: `library/Schoolbox/Assessment/Participant/Plan/Notifier.php`
- Test: `library/Schoolbox/Assessment/Participant/Plan/Test/NotifierVisibilityTest.php`

- [ ] **Step 1: Add direct recipient tests**

Test `notifyUpcoming()` or `notifyOverdue()` through injected `assessmentDataSource` so the notifier retrieves a domain model with one participant and each of the four work types.

Assertions should inspect envelope destination keys from `getAll()`:

```php
$destinations = array_keys($envelope->getAll());
self::assertContains('notify.user.' . $studentId, implode("\n", $destinations));
self::assertNotContains('notify.parent.' . $studentId, implode("\n", $destinations));
```

Use inverse assertions for parent-only visibility.

- [ ] **Step 2: Fix only if the direct tests fail**

If direct recipient tests fail, correct the relevant call sites so learner messages are always behind:

```php
if ($this->isLearnerNotifiable($assessment)) {
```

and parent messages are always behind:

```php
if ($this->isParentNotifiable($assessment)) {
```

Do not change `isLearnerNotifiable()` / `isParentNotifiable()` semantics unless the test proves the work type model itself is hydrated incorrectly.

- [ ] **Step 3: Run focused tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Assessment/Participant/Plan/Test/NotifierVisibilityTest.php
```

Expected: direct recipient tests pass.

## Task 5: Verification

**Files:**
- Verify: `library/Schoolbox/Assessment/Participant/Plan/Notifier.php`
- Verify: `library/Schoolbox/Assessment/Participant/Plan/Test/NotifierVisibilityTest.php`

- [ ] **Step 1: Run PHPCS on changed PHP files**

Run:

```bash
./vendor/bin/phpcs library/Schoolbox/Assessment/Participant/Plan/Notifier.php library/Schoolbox/Assessment/Participant/Plan/Test/NotifierVisibilityTest.php
```

Expected: no errors.

- [ ] **Step 2: Run PHPStan on changed PHP files**

Run:

```bash
./vendor/bin/phpstan analyze library/Schoolbox/Assessment/Participant/Plan/Notifier.php library/Schoolbox/Assessment/Participant/Plan/Test/NotifierVisibilityTest.php
```

Expected: no new PHPStan errors.

- [ ] **Step 3: Run focused PHPUnit**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Assessment/Participant/Plan/Test/NotifierVisibilityTest.php
```

Expected: pass.

- [ ] **Step 4: Run broader relevant PHPUnit**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/Learning/Assessment/Test/VisibilityTest.php library/Schoolbox/Learning/Assessment/Participant/Test/AssessmentListMutatorTest.php library/Schoolbox/Test/FeedTest.php
```

Expected: pass.

- [ ] **Step 5: Docker verification**

When Docker is available, repeat the focused PHPUnit command inside `docker/ssh` before marking the PR ready, because project instructions treat Docker PHP verification as authoritative.

## Self-Review

- Spec coverage: The plan covers the reviewer's core concern by replacing aggregate hidden-state decisions with explicit learner/parent audience state. It also covers the practical feed risk where a single feed build currently generates messages for multiple audiences.
- Placeholder scan: No `TBD`, `TODO`, or “write tests for the above” placeholders remain. Each task identifies the file, command, and expected result.
- Type consistency: The plan consistently uses `WorkType\Model`, `Notifier`, `Model` for participant plans, `TriggerBundleInterface`, and existing notifier helper names.


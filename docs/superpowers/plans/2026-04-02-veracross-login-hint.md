# Veracross login_hint Attendance Link Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Append a `login_hint` query parameter containing the logged-in user's email address to the Veracross attendance deep link, so users are not prompted to enter their email when clicking through.

**Architecture:** Modify `AttendanceLink::generate()` to append `login_hint=<email>` to the URL returned by `Reader::getAttendanceLink()`. If the user has no email the URL is returned unchanged. All logic stays within `AttendanceLink.php` — no other production files change.

**Tech Stack:** PHP 8.4, PHPUnit, PSR-12 (PHPCS), PHPStan level 4.

---

## File Map

| Action | Path |
|--------|------|
| Modify | `library/Schoolbox/External/Connector/Veracross/Base/Endpoint/Codec/AttendanceLink.php` |
| Create | `library/Schoolbox/External/Connector/Veracross/Base/Endpoint/Codec/Test/AttendanceLinkTest.php` |

---

## Task 1: Create the test class with failing tests

**Files:**
- Create: `library/Schoolbox/External/Connector/Veracross/Base/Endpoint/Codec/Test/AttendanceLinkTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Codec\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Codec\AttendanceLink;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Factory;
use Schoolbox\External\Event\Model as EventModel;
use Schoolbox\Role;
use Schoolbox\User;

class AttendanceLinkTest extends TestCase
{
    /**
     * Build a staff User with a local email address.
     */
    private function makeStaffUser(string $username, string $emailDomain): User
    {
        $role = new Role();
        $role->setType(Role::STAFF);

        $user = new User();
        $user->setRole($role);
        $user->setUsername($username);
        $user->setLocalEmail(true);
        $user->setEmailDomain($emailDomain);

        return $user;
    }


    /**
     * Build a User with no email address.
     */
    private function makeStaffUserWithNoEmail(): User
    {
        $role = new Role();
        $role->setType(Role::STAFF);

        $user = new User();
        $user->setRole($role);
        $user->setLocalEmail(false);
        $user->setAltEmail('');

        return $user;
    }


    /**
     * Build a valid Veracross event with an attendance URL embedded in its metadata.
     */
    private function makeEvent(string $attendanceUrl): EventModel
    {
        $event = new EventModel();
        $event->setSource(Factory::SOURCE_NAME);
        $event->setCode('MATHCLASS');
        $event->setStartAt(new \Alaress_Date_Format());
        $event->setMetadata(['links' => ['attendance' => $attendanceUrl]]);

        return $event;
    }


    /**
     * login_hint is appended with ? when the URL has no existing query string.
     */
    public function testLoginHintAppendedWithQuestionMark(): void
    {
        $codec = new AttendanceLink(true, 7);
        $user  = $this->makeStaffUser('teacher.name', 'school.edu');
        $event = $this->makeEvent('https://portals.veracross.com/school/attendance');

        $result = $codec->generate($event, $user);

        $this->assertSame(
            'https://portals.veracross.com/school/attendance?login_hint=teacher.name%40school.edu',
            $result
        );
    }


    /**
     * login_hint is appended with & when the URL already contains query parameters.
     */
    public function testLoginHintAppendedWithAmpersandWhenQueryStringExists(): void
    {
        $codec = new AttendanceLink(true, 7);
        $user  = $this->makeStaffUser('teacher.name', 'school.edu');
        $event = $this->makeEvent('https://portals.veracross.com/school/attendance?class=MATH');

        $result = $codec->generate($event, $user);

        $this->assertSame(
            'https://portals.veracross.com/school/attendance?class=MATH&login_hint=teacher.name%40school.edu',
            $result
        );
    }


    /**
     * URL is returned unchanged when the user has no email address.
     */
    public function testUrlReturnedUnchangedWhenUserHasNoEmail(): void
    {
        $codec = new AttendanceLink(true, 7);
        $user  = $this->makeStaffUserWithNoEmail();
        $event = $this->makeEvent('https://portals.veracross.com/school/attendance');

        $result = $codec->generate($event, $user);

        $this->assertSame('https://portals.veracross.com/school/attendance', $result);
    }


    /**
     * Empty string when attendance is disabled in config.
     */
    public function testEmptyStringWhenAttendanceDisabled(): void
    {
        $codec = new AttendanceLink(false, 7);
        $user  = $this->makeStaffUser('teacher.name', 'school.edu');
        $event = $this->makeEvent('https://portals.veracross.com/school/attendance');

        $result = $codec->generate($event, $user);

        $this->assertSame('', $result);
    }


    /**
     * Empty string when the event source is not Veracross.
     */
    public function testEmptyStringForNonVeracrossEvent(): void
    {
        $codec = new AttendanceLink(true, 7);
        $user  = $this->makeStaffUser('teacher.name', 'school.edu');

        $event = new EventModel();
        $event->setSource('some_other_connector');
        $event->setCode('MATHCLASS');
        $event->setStartAt(new \Alaress_Date_Format());
        $event->setMetadata(['links' => ['attendance' => 'https://portals.veracross.com/school/attendance']]);

        $result = $codec->generate($event, $user);

        $this->assertSame('', $result);
    }


    /**
     * Empty string when the event has no metadata.
     */
    public function testEmptyStringWhenNoMetadata(): void
    {
        $codec = new AttendanceLink(true, 7);
        $user  = $this->makeStaffUser('teacher.name', 'school.edu');

        $event = new EventModel();
        $event->setSource(Factory::SOURCE_NAME);
        $event->setCode('MATHCLASS');
        $event->setStartAt(new \Alaress_Date_Format());
        // metadata intentionally not set — defaults to null

        $result = $codec->generate($event, $user);

        $this->assertSame('', $result);
    }
}
```

- [ ] **Step 2: Run the tests to confirm they fail**

```bash
./vendor/bin/phpunit library/Schoolbox/External/Connector/Veracross/Base/Endpoint/Codec/Test/AttendanceLinkTest.php
```

Expected: 3 failures — the `login_hint` tests fail because the implementation hasn't been added yet. The 3 regression tests (`testEmptyStringWhenAttendanceDisabled`, `testEmptyStringForNonVeracrossEvent`, `testEmptyStringWhenNoMetadata`) should pass already.

---

## Task 2: Implement the login_hint logic

**Files:**
- Modify: `library/Schoolbox/External/Connector/Veracross/Base/Endpoint/Codec/AttendanceLink.php`

- [ ] **Step 1: Update `generate()` to append login_hint**

Replace the final `return` statement in `generate()` (currently line 60):

```php
return (string)(new Reader())->getAttendanceLink($event->getMetadata());
```

With:

```php
$url = (string)(new Reader())->getAttendanceLink($event->getMetadata());

if ($url !== '' && $user->hasEmail()) {
    $separator = str_contains($url, '?') ? '&' : '?';
    $url .= $separator . http_build_query(['login_hint' => $user->getEmail()]);
}

return $url;
```

The full updated `generate()` method:

```php
public function generate(
    \Schoolbox\External\Event\Model $event,
    \Schoolbox\User $user
): string {
    if ($event->getSource() !== Factory::SOURCE_NAME) {
        return '';
    }

    /* We needed this for an identifier obtained during timetable import
     */
    $metadata = $event->getMetadata();

    if (!isset($metadata)) {
        return '';
    }

    /* Only generate links where possible
     */
    if (!$this->isEnabled($event->getCode(), $user, $event->getStartAt())) {
        return '';
    }

    $url = (string)(new Reader())->getAttendanceLink($event->getMetadata());

    if ($url !== '' && $user->hasEmail()) {
        $separator = str_contains($url, '?') ? '&' : '?';
        $url .= $separator . http_build_query(['login_hint' => $user->getEmail()]);
    }

    return $url;
}
```

- [ ] **Step 2: Run the tests to confirm they all pass**

```bash
./vendor/bin/phpunit library/Schoolbox/External/Connector/Veracross/Base/Endpoint/Codec/Test/AttendanceLinkTest.php
```

Expected: 6 tests, 6 assertions, 0 failures.

- [ ] **Step 3: Run PHPCS on changed files**

```bash
./vendor/bin/phpcs library/Schoolbox/External/Connector/Veracross/Base/Endpoint/Codec/AttendanceLink.php library/Schoolbox/External/Connector/Veracross/Base/Endpoint/Codec/Test/AttendanceLinkTest.php
```

Expected: no violations. Run `./vendor/bin/phpcbf` on the same files if any style issues are reported.

- [ ] **Step 4: Run PHPStan on changed files**

```bash
./vendor/bin/phpstan analyze library/Schoolbox/External/Connector/Veracross/Base/Endpoint/Codec/AttendanceLink.php library/Schoolbox/External/Connector/Veracross/Base/Endpoint/Codec/Test/AttendanceLinkTest.php
```

Expected: no errors.

- [ ] **Step 5: Run the full PHPUnit suite to check for regressions**

```bash
./vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add \
  library/Schoolbox/External/Connector/Veracross/Base/Endpoint/Codec/AttendanceLink.php \
  library/Schoolbox/External/Connector/Veracross/Base/Endpoint/Codec/Test/AttendanceLinkTest.php
git commit -m "Append login_hint to Veracross attendance link [SA-21944]"
```

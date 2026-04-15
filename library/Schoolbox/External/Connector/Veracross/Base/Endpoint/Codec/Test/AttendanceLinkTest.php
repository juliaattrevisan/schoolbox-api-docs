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
     * Build a staff User with an alt email address.
     */
    private function makeStaffUserWithAltEmail(string $altEmail): User
    {
        $role = new Role();
        $role->setType(Role::STAFF);

        $user = new User();
        $user->setRole($role);
        $user->setLocalEmail(false);
        $user->setAltEmail($altEmail);

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
        $event = $this->makeEvent('https://portals.veracross.com/school/attendance');
        $event->setSource('some_other_connector');

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


    /**
     * login_hint is appended with user's alt email when localEmail is false.
     */
    public function testLoginHintAppendedForUserWithAltEmail(): void
    {
        $codec = new AttendanceLink(true, 7);
        $user  = $this->makeStaffUserWithAltEmail('alt.teacher@otherdomain.com');
        $event = $this->makeEvent('https://portals.veracross.com/school/attendance');

        $result = $codec->generate($event, $user);

        $this->assertSame(
            'https://portals.veracross.com/school/attendance?login_hint=alt.teacher%40otherdomain.com',
            $result
        );
    }
}

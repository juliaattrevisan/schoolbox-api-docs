<?php
/**
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Calendar\Test\Event;

use Schoolbox\Campus;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Collection;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Event\Access as EventAccess;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\Access as FolderAccess;
use Schoolbox\Calendar\Relation\Category as CategoryRelation;

class AccessTest extends \PHPUnit_Framework_TestCase
{
    /** @var Role[] */
    private $roles;

    /** @var User[] */
    private $users;

    /** @var Event[] */
    private $events;

    /** @var UserAccess[] */
    private $userAccess;

    /**
     * @param $key
     * @return mixed|Role
     */
    private function getRole($key)
    {
        if (empty($this->roles)) {
            $this->roles = [
                'staff' => (new Role())->setType(Role::STAFF),
                'parent' => (new Role())->setType(Role::PARENT),
                'student' => (new Role())->setType(Role::STUDENT),
            ];
        }

        return $this->roles[$key];
    }

    /**
     * @param $key
     * @return mixed|User
     */
    private function getUser($key)
    {
        if (empty($this->users)) {
            $this->users = [
                'super' => (new User)->setId(1)->setSuperuser(true),
                'staff' => (new User)->setId(2)->setRole($this->getRole('staff')),
                'staffAlt' => (new User)->setId(3)->setRole($this->getRole('staff')),
                'parent' => (new User)->setId(4)->setRole($this->getRole('parent')),
                'student' => (new User)->setId(5)->setRole($this->getRole('student')),

                'campusModerate' => (new User)->setId(6),
                'campusWrite' => (new User)->setId(7),
                'campusRead' => (new User)->setId(8),
                'campusNone' => (new User)->setId(9),

                'groupReadSuper' => (new User)->setId(10)->setSuperuser(true),
                'groupAdmin' => (new User)->setId(11),
                'groupWrite' => (new User)->setId(12),
                'groupRead' => (new User)->setId(13),
                'groupNone' => (new User)->setId(14),
            ];

            // Create a mock user object, who is a child of $this->users['parent']
            /** @var User|\PHPUnit_Framework_MockObject_MockObject $parentChild */
            $this->users['parentChild'] = $this->getMockBuilder(User::class)->getMock();
            $this->users['parentChild']->method('getParents')->willReturnCallback(function () {
                return new Collection([$this->users['parent']]);
            });
        }

        return $this->users[$key];
    }

    /**
     * @param $key
     * @return mixed|Event
     */
    private function getEvent($key)
    {
        if (empty($this->events)) {

            $categoryCollection = new Collection([
                (new CategoryRelation())->setCategoryId(1)->setEventId(2)
            ]);

            $campusCollection = new Collection([
                (new Campus())->setId(1)
            ]);

            $this->events = [
                'personal' => (new Event)->setId(1)->setAccess(Event::ACCESS_PERSONAL),
                'group' => (new Event)->setId(2)->setAccess(Event::ACCESS_GROUP)->setCategoryCollection($categoryCollection),
                'campus' => (new Event)->setId(3)->setAccess(Event::ACCESS_CAMPUS)->setCampusCollection($campusCollection),
            ];
        }

        return $this->events[$key];
    }

    /**
     * @param $key
     * @return mixed|UserAccess
     */
    private function getUserAccess($key)
    {
        if (empty($this->userAccess)) {
            $this->userAccess = [
                'campusModerate' => (new UserAccess())
                    ->setService(UserAccess::SERVICE_CALENDAR)
                    ->setAccessLevel(UserAccess::ACCESS_FULL)
                    ->setServiceIdentifier(1),

                'campusWrite' => (new UserAccess())
                    ->setService(UserAccess::SERVICE_CALENDAR)
                    ->setAccessLevel(UserAccess::ACCESS_WRITE)
                    ->setServiceIdentifier(1),

                'campusRead' => (new UserAccess())
                    ->setService(UserAccess::SERVICE_CALENDAR)
                    ->setAccessLevel(UserAccess::ACCESS_READ)
                    ->setServiceIdentifier(1),

                'groupReadSuper' => (new UserAccess())
                    ->setService(UserAccess::SERVICE_HOMEPAGE)
                    ->setAccessLevel(UserAccess::ACCESS_READ)
                    ->setServiceIdentifier(1),

                'groupAdmin' => (new UserAccess())
                    ->setService(UserAccess::SERVICE_HOMEPAGE)
                    ->setAccessLevel(UserAccess::ACCESS_FULL)
                    ->setServiceIdentifier(1),

                'groupWrite' => (new UserAccess())
                    ->setService(UserAccess::SERVICE_HOMEPAGE)
                    ->setAccessLevel(UserAccess::ACCESS_WRITE)
                    ->setServiceIdentifier(1),

                'groupRead' => (new UserAccess())
                    ->setService(UserAccess::SERVICE_HOMEPAGE)
                    ->setAccessLevel(UserAccess::ACCESS_READ)
                    ->setServiceIdentifier(1),
            ];
        }

        return $this->userAccess[$key];
    }

    /**
     * Takes a user/user access key and sets the relationship on both objects
     *
     * @param $key
     */
    private function configureUserAccess($key)
    {
        $user = $this->getUser($key);
        $access = $this->getUserAccess($key);

        $user->setAccessList(new Collection([$access]));
        $access->setUser($user);
    }

    /**
     * Generate an array for running a test on checkDestination
     *
     * @param $result
     * @param string $effectorKey
     * @param int $accessLevel
     * @param string $authorKey
     * @param string $ownerKey
     * @param int[] $groups
     * @param int[] $campuses
     * @return array
     */
    private function generateTest($result, $effectorKey, $accessLevel, $authorKey, $ownerKey, $groups = [], $campuses = [])
    {
        return [
            $result,
            $this->getUser($effectorKey),
            $accessLevel,
            $this->getUser($authorKey),
            $this->getUser($ownerKey),
            $groups,
            $campuses
        ];
    }

    /**
     * Generate an array for running a test on checkExisting
     *
     * @param $result
     * @param $effectorKey
     * @param $eventKey
     * @param $authorKey
     * @param $ownerKey
     * @return array
     */
    private function generateTestExisting($result, $effectorKey, $eventKey, $authorKey, $ownerKey)
    {
        $event = (clone $this->getEvent($eventKey))
            ->setOwner($this->getUser($ownerKey))
            ->setAuthor($this->getUser($authorKey));

        return [$result, $this->getUser($effectorKey), $event];
    }

    /**
     * Tests for checkDestination()
     * This makes up most of the permission check tests
     *
     * @return array
     */
    public function dataCheckDestination()
    {
        // Set access on users
        $this->configureUserAccess('campusModerate');
        $this->configureUserAccess('campusWrite');
        $this->configureUserAccess('campusRead');
        $this->configureUserAccess('groupReadSuper');
        $this->configureUserAccess('groupAdmin');
        $this->configureUserAccess('groupWrite');
        $this->configureUserAccess('groupRead');

        $tests = [];

        // --------------------------------------------------------------------------
        // Personal level events
        // --------------------------------------------------------------------------

        // Staff can create an event for themselves
        $tests['StaffPersonal'] = $this->generateTest(true, 'staff', Event::ACCESS_PERSONAL, 'staff', 'staff');

        // Parent can create an event for themselves
        $tests['ParentPersonal'] = $this->generateTest(true, 'parent', Event::ACCESS_PERSONAL, 'parent', 'parent');

        // Student can create an event for themselves
        $tests['StudentPersonal'] = $this->generateTest(true, 'student', Event::ACCESS_PERSONAL, 'student', 'student');

        // Super can create an event for staff member
        $tests['SuperForStaff'] = $this->generateTest(true, 'super', Event::ACCESS_PERSONAL, 'super', 'staff');

        // Super can create an event for a parent
        $tests['SuperForParent'] = $this->generateTest(true, 'super', Event::ACCESS_PERSONAL, 'super', 'parent');

        // Super can create an event for a student
        $tests['SuperForStudent'] = $this->generateTest(true, 'super', Event::ACCESS_PERSONAL, 'super', 'student');

        // Staff can create an event for another staff member
        $tests['StaffForStaff'] = $this->generateTest(true, 'staff', Event::ACCESS_PERSONAL, 'staff', 'staffAlt');

        // Staff can create event for any parent
        $tests['StaffForParent'] = $this->generateTest(true, 'staff', Event::ACCESS_PERSONAL, 'staff', 'parent');

        // Staff can create event for any student
        $tests['StaffForStudent'] = $this->generateTest(true, 'staff', Event::ACCESS_PERSONAL, 'staff', 'student');

        // Parent can create event for their child
        $tests['ParentForChild'] = $this->generateTest(true, 'parent', Event::ACCESS_PERSONAL, 'parent', 'parentChild');

        // Parent cannot create an event for a staff member
        // TODO: currently possible, add test with SA-4032 fix

        // Parent cannot create an event for another parent
        // TODO: currently possible, add test with SA-4032 fix

        // Parent cannot create an event for a student that isn't theirs
        // TODO: currently possible, add test with SA-4032 fix

        // Student cannot create event for a staff member
        // TODO: currently possible, add test with SA-4032 fix

        // Student cannot create event for a parent
        // TODO: currently possible, add test with SA-4032 fix

        // Student cannot create event for another student
        // TODO: currently possible, add test with SA-4032 fix


        // --------------------------------------------------------------------------
        // Campus level events
        // --------------------------------------------------------------------------

        // Super with no campus permission can create
        $tests['SuperCampus'] = $this->generateTest(true, 'super', Event::ACCESS_CAMPUS, 'super', 'super', [], [1]);

        // User with moderate access can create
        $tests['ModerateCampus'] = $this->generateTest(true, 'campusModerate', Event::ACCESS_CAMPUS, 'campusModerate', 'campusModerate', [], [1]);

        // User with write access can create
        $tests['WriteCampus'] = $this->generateTest(true, 'campusWrite', Event::ACCESS_CAMPUS, 'campusWrite', 'campusWrite', [], [1]);

        // User with read access cannot create
        $tests['ReadCampus'] = $this->generateTest(false, 'campusRead', Event::ACCESS_CAMPUS, 'campusRead', 'campusRead', [], [1]);

        // User with no access cannot create
        $tests['NoCampus'] = $this->generateTest(false, 'campusNone', Event::ACCESS_CAMPUS, 'campusNone', 'campusNone', [], [1]);


        // --------------------------------------------------------------------------
        // Group level events
        // --------------------------------------------------------------------------

        // Super with no permissions to group can create
        $tests['SuperGroup'] = $this->generateTest(true, 'super', Event::ACCESS_GROUP, 'super', 'super', [1]);

        // Super with read access to group can create
        $tests['SuperReadGroup'] = $this->generateTest(true, 'groupReadSuper', Event::ACCESS_GROUP, 'groupReadSuper', 'groupReadSuper', [1]);

        // User with admin access to group can create
        $tests['AdminGroup'] = $this->generateTest(true, 'groupAdmin', Event::ACCESS_GROUP, 'groupAdmin', 'groupAdmin', [1]);

        // User with write access to group can create
        $tests['WriteGroup'] = $this->generateTest(true, 'groupWrite', Event::ACCESS_GROUP, 'groupWrite', 'groupWrite', [1]);

        // User with read access to group cannot create
        $tests['ReadGroup'] = $this->generateTest(false, 'groupRead', Event::ACCESS_GROUP, 'groupRead', 'groupRead', [1]);

        // User with no access to group cannot create
        $tests['NoGroup'] = $this->generateTest(false, 'groupNone', Event::ACCESS_GROUP, 'groupNone', 'groupNone', [1]);

        return $tests;
    }


    /**
     * Tests for checkExisting()
     * Because checkExisting is just a shim on checkDestination - these tests will not repeat the checks above, they
     * will just make sure that permission checks run fine when working with an event object
     *
     * @return array
     */
    public function dataCheckExisting()
    {
        // Set access on users
        $this->configureUserAccess('campusModerate');
        $this->configureUserAccess('campusRead');

        return [

            // Personal level event, staff can modify event for themselves
            'ExistingPersonal' => $this->generateTestExisting(true, 'staff', 'personal', 'staff', 'staff'),

            // Personal level event, student cannot create event for a staff member
            // TODO: currently possible, add test with SA-4032 fix

            // Campus level event, moderators can modify campus level event not authored or owned by themselves
            'ExistingCampus' => $this->generateTestExisting(true, 'campusModerate', 'campus', 'staff', 'staff'),

            // Campus level event, read access cannot modify campus level event, even if they authored & own
            'ExistingCampusRead' => $this->generateTestExisting(false, 'campusRead', 'campus', 'campusRead', 'campusRead'),

        ];
    }

    /**
     * @dataProvider dataCheckExisting
     * @param $expected
     * @param User $effector
     * @param Event $event
     */
    public function testCheckExisting($expected, User $effector, Event $event)
    {
        $access = new EventAccess(new FolderAccess());
        $actual = $access->checkExisting($effector, $event);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider dataCheckDestination
     * @param $expected
     * @param User $effector
     * @param $accessLevel
     * @param User $author
     * @param User $owner
     * @param array $groups
     * @param array $campuses
     */
    public function testCheckDestination($expected, User $effector, $accessLevel, User $author, User $owner, array $groups = [], array $campuses = [])
    {
        // Create mock folder access object and mock getMinFolderAccess() to bypass DB
        $mockFolderAccess = $this->getMockBuilder(FolderAccess::class)->getMock();
        $mockFolderAccess->method('getMinFolderAccess')->willReturnCallback(function () use ($effector, $groups) {
            $relatedAccessList = $effector->getAccessList()->filter(function (UserAccess $access) use ($groups) {
                return in_array($access->getServiceIdentifier(), $groups);
            });

            $relatedAccessLevels = $relatedAccessList->getColumnValues('accessLevel');

            if (empty($relatedAccessLevels)) {
                return 0;
            }

            return min($relatedAccessLevels);
        });


        $access = new EventAccess($mockFolderAccess);
        $actual = $access->checkDestination($effector, $accessLevel, $author, $owner, $groups, $campuses);
        $this->assertEquals($expected, $actual);
    }
}

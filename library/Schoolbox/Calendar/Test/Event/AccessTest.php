<?php
/**
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Calendar\Test\Event;

use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Collection;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Event\Access as EventAccess;
use Schoolbox\Calendar\Relation\Campus as CampusRelation;
use Schoolbox\Calendar\Relation\Category as CategoryRelation;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\Folder\Model as FolderModel;

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
                'super' => (new User)->setSuperuser(true)->setId(1),
                'staff' => (new User)->setRole($this->getRole('staff'))->setId(2),
                'staffAlt' => (new User)->setRole($this->getRole('staff'))->setId(3),
                'parent' => (new User)->setRole($this->getRole('parent'))->setId(4),
                'student' => (new User)->setRole($this->getRole('student'))->setId(5),
                'campusModerate' => (new User)->setId(6),
                'campusWrite' => (new User)->setId(7),
                'campusRead' => (new User)->setId(8),

                'groupWrite' => (new User)->setId(9),
                'groupRead' => (new User)->setId(10),
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
            $this->events = [
                'personal' => (new Event)->setId(1)->setAccess(Event::ACCESS_PERSONAL),
                'group' => (new Event)->setId(2)->setAccess(Event::ACCESS_GROUP),
                'campus' => (new Event)->setId(3)->setAccess(Event::ACCESS_CAMPUS),
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
                    ->setAccessLevel(UserAccess::ACCESS_READ)
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

                'groupWrite' => (new UserAccess())
                    ->setService(UserAccess::SERVICE_HOMEPAGE)
                    ->setAccessLevel(UserAccess::ACCESS_WRITE)
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
     * Generate an array for running a test
     *
     * @param $result
     * @param $effectorKey
     * @param $eventKey
     * @param $authorKey
     * @param $ownerKey
     * @return array
     */
    private function generateTest($result, $effectorKey, $eventKey, $authorKey, $ownerKey)
    {
        $event = (clone $this->getEvent($eventKey))
            ->setOwner($this->getUser($ownerKey))
            ->setAuthor($this->getUser($authorKey));

        return [$result, $this->getUser($effectorKey), $event];
    }


    /**
     * @return array
     */
    public function dataCheckAccess()
    {
        // Set access on users
        $this->configureUserAccess('campusModerate');
        $this->configureUserAccess('campusWrite');
        $this->configureUserAccess('campusRead');

//        $groups = [
//            (new Group)->setCategory(1)
//        ];
//
//        $folders = [
//            (new FolderModel())->setId(1)->setGroup($groups[0])
//        ];
//
//        $categoryCollection = new Collection([
//            (new CategoryRelation())->setCategoryId(1)->setEventId(2)
//        ]);
//
//        $this->events['group']->setCategoryCollection($categoryCollection);
//
//
//        $campusCollection = new Collection([
//            (new CampusRelation())->setEventId(1)->setCampusId(1),
//        ]);
//
//        $this->getEvent('campus')->setCampusCollection($campusCollection);


        $tests = [];

        // --------------------------------------------------------------------------
        // Personal level events
        // --------------------------------------------------------------------------

        // Staff can create an event for themselves
        $tests['StaffPersonal'] = $this->generateTest(true, 'staff', 'personal', 'staff', 'staff');

        // Parent can create an event for themselves
        $tests['ParentPersonal'] = $this->generateTest(true, 'parent', 'personal', 'parent', 'parent');

        // Student can create an event for themselves
        $tests['StudentPersonal'] = $this->generateTest(true, 'student', 'personal', 'student', 'student');

        // Super can create an event for staff member
        $tests['SuperForStaff'] = $this->generateTest(true, 'super', 'personal', 'super', 'staff');

        // Super can create an event for a parent
        $tests['SuperForParent'] = $this->generateTest(true, 'super', 'personal', 'super', 'parent');

        // Super can create an event for a student
        $tests['SuperForStudent'] = $this->generateTest(true, 'super', 'personal', 'super', 'student');

        // Staff can create an event for another staff member
        $tests['StaffForStaff'] = $this->generateTest(true, 'staff', 'personal', 'staff', 'staffAlt');

        // Staff can create event for any parent
        $tests['StaffForParent'] = $this->generateTest(true, 'staff', 'personal', 'staff', 'parent');

        // Staff can create event for any student
        $tests['StaffForStudent'] = $this->generateTest(true, 'staff', 'personal', 'staff', 'student');

        // Parent can create event for their child
        $tests['ParentForChild'] = $this->generateTest(true, 'parent', 'personal', 'parent', 'parentChild');

        // Parent can not create an event for a staff member
        // TODO: currently possible, add test with SA-4032 fix

        // Parent can not create an event for another parent
        // TODO: currently possible, add test with SA-4032 fix

        // Parent can not create an event for a student that isn't theirs
        // TODO: currently possible, add test with SA-4032 fix

        // Student can not create event for a staff member
        // TODO: currently possible, add test with SA-4032 fix

        // Student can not create event for a parent
        // TODO: currently possible, add test with SA-4032 fix

        // Student can not create event for another student
        // TODO: currently possible, add test with SA-4032 fix


        // --------------------------------------------------------------------------
        // Campus level events
        // --------------------------------------------------------------------------

        // Super with no campus permission can create
        // $tests['SuperCampus'] = $this->generateTest(true, 'super', 'campus', 'super', 'super');

        // User with moderate access can create
        // $tests['ModerateCampus'] = $this->generateTest(true, 'campusModerate', 'campus', 'campusModerate', 'campusModerate');

        // User with write access can create

        // User with read access cannot create


        // --------------------------------------------------------------------------
        // Group level events
        // --------------------------------------------------------------------------

        // Super with no permissions to group can create
        // $tests['SuperGroup'] = $this->generateTest(true, 'super', 'group', 'super', 'super');

        // Super with read access to group can create
        // $tests['SuperGroupRead'] = $this->generateTest(true, 'superRead', 'group', 'superRead', 'superRead');

        // User with write access to group can create
        // $tests['GroupWrite'] = $this->generateTest(true, 'write', 'group', 'write', 'write');

        // User with read access to group cannot create

        // User with no access to group cannot create


        return $tests;
    }

    /**
     * @dataProvider dataCheckAccess
     * @param $expected
     * @param User $effector
     * @param Event $event
     * @param array $groups
     * @param array $campuses
     */
    public function testCheckAccess($expected, User $effector, Event $event, $groups = [], $campuses = [])
    {
        $access = new EventAccess();
        $actual = $access->checkAccess($effector, $event, $groups, $campuses);
        $this->assertEquals($expected, $actual);
    }
}

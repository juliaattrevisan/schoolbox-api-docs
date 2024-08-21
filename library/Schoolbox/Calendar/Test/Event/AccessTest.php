<?php

/**
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar\Test\Event;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Schoolbox\Campus;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Collection;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Event\Access as EventAccess;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\Access as FolderAccess;

/**
 * @codeCoverageIgnore
 */
class AccessTest extends TestCase
{
    /** @var Role[] */
    private $roles;

    /**
     * @var array<User|MockObject>
     */
    private $users;

    /** @var Event[] */
    private $events;

    /** @var UserAccess[] */
    private $userAccess;

    /**
     * @param string $key
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
     * @param string $key
     * @return User|MockObject
     */
    private function getUser($key)
    {
        if (empty($this->users)) {
            $newUser = function ($id, array $overload = null) {
                // Set an empty access list by default on every user, this is to avoid hitting the database on
                // User::getAccessList() when access list is not set

                /** @var User|MockObject $mockUser */
                $mockUser = $this->getMockBuilder(User::class)->onlyMethods($overload ?? [])->getMock();
                $mockUser->setId($id);
                $mockUser->setAccessList(new GenericCollection(UserAccess::class));
                $mockUser->setChildren(new GenericCollection(User::class));

                return $mockUser;
            };

            $this->users = [
                'super'                 => $newUser(1)->setSuperuser(true),
                'staff'                 => $newUser(2)->setRole($this->getRole('staff')),
                'staffAlt'              => $newUser(3)->setRole($this->getRole('staff')),
                'parent'                => $newUser(4)->setRole($this->getRole('parent')),
                'student'               => $newUser(5)->setRole($this->getRole('student')),

                'campusModerate'        => $newUser(6),
                'campusWrite'           => $newUser(7),
                'campusRead'            => $newUser(8),
                'campusNone'            => $newUser(9),

                'groupReadSuper'        => $newUser(10)->setSuperuser(true),
                'groupAdmin'            => $newUser(11),
                'groupWrite'            => $newUser(12),
                'groupRead'             => $newUser(13),
                'groupNone'             => $newUser(14),

                'parent2'               => $newUser(15)->setRole($this->getRole('parent')),
                'student2'              => $newUser(16)->setRole($this->getRole('student')),
                'parentWithStudent'     => $newUser(17)->setRole($this->getRole('parent')),
                'studentWithParent'     => $newUser(18, ['getParents'])
                                                ->setRole($this->getRole('student')),
            ];

            // Create a mock returns for users

            // Mock returns for studentWithParent, who is a child of $this->users['parentWithStudent']
            /** @var User|MockObject $studentWithParents */
            $studentWithParents = $this->users['studentWithParent'];
            $studentWithParents->method('getParents')->willReturnCallback(function () {
                $collection = new Collection();
                return $collection->add($this->users['parentWithStudent']);
            });
            $this->users['studentWithParent'] = $studentWithParents;
        }

        return $this->users[$key];
    }

    /**
     * @param string $key
     * @return mixed|Event
     */
    private function getEvent($key)
    {
        if (empty($this->events)) {
            $folderCollection = new Collection([
                (new Folder())->setId(1)
            ]);

            $campusCollection = new Collection([
                (new Campus())->setId(1)
            ]);

            $this->events = [
                'personal' => (new Event())
                    ->setId(1)
                    ->setAccess(Event::ACCESS_PERSONAL),

                'group' => (new Event())
                    ->setId(2)
                    ->setAccess(Event::ACCESS_GROUP)
                    ->setFolderCollection($folderCollection),

                'campus' => (new Event())
                    ->setId(3)
                    ->setAccess(Event::ACCESS_CAMPUS)
                    ->setCampusCollection($campusCollection),
            ];
        }

        return $this->events[$key];
    }

    /**
     * @param string $key
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
     * @param string[] $keys
     */
    private function configureUserAccess($keys)
    {
        foreach ($keys as $key) {
            $user = $this->getUser($key);
            $access = $this->getUserAccess($key);

            $user->setAccessList(new GenericCollection(UserAccess::class, [$access]));
            $access->setUser($user);
        }
    }

    /**
     * @param User $effector
     * @param int[] $groups
     * @return FolderAccess
     */
    private function getMockFolderAccess(User $effector, array $groups)
    {
        // Create mock folder access object and mock getMinFolderAccess() to bypass DB
        $mockFolderAccess = $this->createMock(FolderAccess::class);
        $mockFolderAccess->method('getMinFolderAccess')->willReturnCallback(function () use ($effector, $groups) {
            $relatedAccessList = $effector->getAccessList()->filter(function (UserAccess $access) use ($groups) {
                return in_array($access->getServiceIdentifier(), $groups);
            });

            $relatedAccessLevels = $relatedAccessList->getPropertyValues('accessLevel');

            if (empty($relatedAccessLevels)) {
                return 0;
            }

            return min($relatedAccessLevels);
        });

        return $mockFolderAccess;
    }

    /**
     * Generate an array for running a test on checkDestination
     *
     * @param bool $result
     * @param string $effectorKey
     * @param int $accessLevel
     * @param string $authorKey
     * @param string $ownerKey
     * @param int[] $groups
     * @param int[] $campuses
     * @return array
     */
    private function generateTest(
        $result,
        $effectorKey,
        $accessLevel,
        $authorKey,
        $ownerKey,
        $groups = [],
        $campuses = []
    ) {
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
     * @param bool $result
     * @param string $effectorKey
     * @param string $eventKey
     * @param string $authorKey
     * @param string $ownerKey
     * @return array
     */
    private function generateTestExisting($result, $effectorKey, $eventKey, $authorKey, $ownerKey)
    {
        $event = clone $this->getEvent($eventKey);
        $event->setOwner($this->getUser($ownerKey))
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
        $this->configureUserAccess([
            'campusModerate',
            'campusWrite',
            'campusRead',
            'groupReadSuper',
            'groupAdmin',
            'groupWrite',
            'groupRead',
        ]);

        return [

            // --------------------------------------------------------------------------
            // Personal level events
            // --------------------------------------------------------------------------

            // Staff can create an event for themselves
            'StaffPersonal' => $this->generateTest(true, 'staff', Event::ACCESS_PERSONAL, 'staff', 'staff'),

            // Parent can create an event for themselves
            'ParentPersonal' => $this->generateTest(true, 'parent', Event::ACCESS_PERSONAL, 'parent', 'parent'),

            // Student can create an event for themselves
            'StudentPersonal' => $this->generateTest(true, 'student', Event::ACCESS_PERSONAL, 'student', 'student'),

            // Super can create an event for staff member
            'SuperForStaff' => $this->generateTest(true, 'super', Event::ACCESS_PERSONAL, 'super', 'staff'),

            // Super can create an event for a parent
            'SuperForParent' => $this->generateTest(true, 'super', Event::ACCESS_PERSONAL, 'super', 'parent'),

            // Super can create an event for a student
            'SuperForStudent' => $this->generateTest(true, 'super', Event::ACCESS_PERSONAL, 'super', 'student'),

            // Staff can create an event for another staff member
            'StaffForStaff' => $this->generateTest(true, 'staff', Event::ACCESS_PERSONAL, 'staff', 'staffAlt'),

            // Staff can create event for any parent
            'StaffForParent' => $this->generateTest(true, 'staff', Event::ACCESS_PERSONAL, 'staff', 'parent'),

            // Staff can create event for any student
            'StaffForStudent' => $this->generateTest(true, 'staff', Event::ACCESS_PERSONAL, 'staff', 'student'),

            // Staff can edit own event made by super user
            'StaffEditSuper' => $this->generateTest(true, 'staff', Event::ACCESS_PERSONAL, 'super', 'staff'),

            // Parent can create event for their child
            'ParentForChild' => $this->generateTest(
                true,
                'parentWithStudent',
                Event::ACCESS_PERSONAL,
                'parentWithStudent',
                'studentWithParent'
            ),

            // Staff cannot edit other staff members event
            'StaffEditStaff2' => $this->generateTest(false, 'staff', Event::ACCESS_PERSONAL, 'staffAlt', 'staffAlt'),

            // Parent cannot create an event for a staff member
            'ParentForStaff' => $this->generateTest(false, 'parent', Event::ACCESS_PERSONAL, 'parent', 'staff'),

            // Parent cannot create an event for another parent
            'ParentForParent' => $this->generateTest(false, 'parent', Event::ACCESS_PERSONAL, 'parent', 'parent2'),

            // Parent cannot create an event for a student that isn't theirs
            'ParentForOtherChild' => $this->generateTest(
                false,
                'parent',
                Event::ACCESS_PERSONAL,
                'parent',
                'studentWithParent'
            ),

            // Student cannot create event for a staff member
            'StudentForStaff' => $this->generateTest(false, 'student', Event::ACCESS_PERSONAL, 'student', 'staff'),

            // Student cannot create event for a parent
            'StudentForParent' => $this->generateTest(false, 'student', Event::ACCESS_PERSONAL, 'student', 'parent'),

            // Student cannot create event for another student
            'StudentForStudent' => $this->generateTest(false, 'student', Event::ACCESS_PERSONAL, 'student', 'student2'),

            // Student cannot edit own event made by staff
            'StudentEditStaff' => $this->generateTest(false, 'student', Event::ACCESS_PERSONAL, 'staff', 'student'),

            // Student cannot edit own event made by parent
            'StudentEditParent' => $this->generateTest(false, 'student', Event::ACCESS_PERSONAL, 'parent', 'student'),

            // --------------------------------------------------------------------------
            // Campus level events
            // --------------------------------------------------------------------------

            // Super with no campus permission can create
            'SuperCampus' => $this->generateTest(true, 'super', Event::ACCESS_CAMPUS, 'super', 'super', [], [1]),

            // User with moderate access can create
            'ModerateCampus' => $this->generateTest(
                true,
                'campusModerate',
                Event::ACCESS_CAMPUS,
                'campusModerate',
                'campusModerate',
                [],
                [1]
            ),

            // User with write access can create
            'WriteCampus' => $this->generateTest(
                true,
                'campusWrite',
                Event::ACCESS_CAMPUS,
                'campusWrite',
                'campusWrite',
                [],
                [1]
            ),

            // User with read access cannot create
            'ReadCampus' => $this->generateTest(
                false,
                'campusRead',
                Event::ACCESS_CAMPUS,
                'campusRead',
                'campusRead',
                [],
                [1]
            ),

            // User with no access cannot create
            'NoCampus' => $this->generateTest(
                false,
                'campusNone',
                Event::ACCESS_CAMPUS,
                'campusNone',
                'campusNone',
                [],
                [1]
            ),


            // --------------------------------------------------------------------------
            // Group level events
            // --------------------------------------------------------------------------

            // Super with no permissions to group can create
            'SuperGroup' => $this->generateTest(true, 'super', Event::ACCESS_GROUP, 'super', 'super', [1]),

            // Super with read access to group can create
            'SuperReadGroup' => $this->generateTest(
                true,
                'groupReadSuper',
                Event::ACCESS_GROUP,
                'groupReadSuper',
                'groupReadSuper',
                [1]
            ),

            // User with admin access to group can create
            'AdminGroup' => $this->generateTest(
                true,
                'groupAdmin',
                Event::ACCESS_GROUP,
                'groupAdmin',
                'groupAdmin',
                [1]
            ),

            // User with write access to group can create
            'WriteGroup' => $this->generateTest(
                true,
                'groupWrite',
                Event::ACCESS_GROUP,
                'groupWrite',
                'groupWrite',
                [1]
            ),

            // User with read access to group cannot create
            'ReadGroup' => $this->generateTest(false, 'groupRead', Event::ACCESS_GROUP, 'groupRead', 'groupRead', [1]),

            // User with no access to group cannot create
            'NoGroup' => $this->generateTest(false, 'groupNone', Event::ACCESS_GROUP, 'groupNone', 'groupNone', [1]),

        ];
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
        $this->configureUserAccess([
            'campusModerate',
            'campusRead',
            'groupWrite',
            'groupRead',
        ]);

        return [

            // --------------------------------------------------------------------------
            // Personal level events
            // --------------------------------------------------------------------------

            // Staff can modify event for themselves
            'ExistingPersonal' => $this->generateTestExisting(true, 'staff', 'personal', 'staff', 'staff'),

            // Student cannot modify event for a staff member
            'ExistingPersonalStudentToStaff' => $this->generateTestExisting(
                false,
                'student',
                'personal',
                'staff',
                'staff'
            ),

            // --------------------------------------------------------------------------
            // Campus level events
            // --------------------------------------------------------------------------

            // Moderator can modify campus level event not authored or owned by themselves
            'ExistingCampus' => $this->generateTestExisting(true, 'campusModerate', 'campus', 'staff', 'staff'),

            // Read access cannot modify campus level event, even if they authored & own
            'ExistingCampusRead' => $this->generateTestExisting(
                false,
                'campusRead',
                'campus',
                'campusRead',
                'campusRead'
            ),

            // --------------------------------------------------------------------------
            // Group level events
            // --------------------------------------------------------------------------

            // Write access can modify event not authored or owned by themselves
            'ExistingGroup' => $this->generateTestExisting(true, 'groupWrite', 'group', 'staff', 'staff'),

            // Read access cannot modify group level event, even if they authored & own
            'ExistingGroupRead' => $this->generateTestExisting(false, 'groupRead', 'group', 'groupRead', 'groupRead'),

        ];
    }

    /**
     * @param bool $expected
     * @param User $effector
     * @param Event $event
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataCheckExisting')]
    public function testCheckExisting($expected, User $effector, Event $event)
    {
        $access = new EventAccess(
            $this->getMockFolderAccess($effector, $event->getFolderCollection()->getPropertyValues('id'))
        );
        $actual = $access->checkExisting($effector, $event);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @param bool $expected
     * @param User $effector
     * @param int $accessLevel
     * @param User $author
     * @param User $owner
     * @param array $groups
     * @param array $campuses
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataCheckDestination')]
    public function testCheckDestination(
        $expected,
        User $effector,
        $accessLevel,
        User $author,
        User $owner,
        array $groups = [],
        array $campuses = []
    ) {
        $access = new EventAccess($this->getMockFolderAccess($effector, $groups));
        $actual = $access->checkDestination($effector, $accessLevel, $author, $owner, $groups, $campuses);
        $this->assertEquals($expected, $actual);
    }
}

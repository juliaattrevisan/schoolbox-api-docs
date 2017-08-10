<?php
/**
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Calendar\Test\Event;


use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Event\Access;
use Schoolbox\Collection;
use Schoolbox\Role;
use Schoolbox\User;

class AccessTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return array
     */
    public function dataCheckAccess()
    {
        $tests = [];

        // Set up some objects that we'll reuse a lot
        $roles = [
            'staff' => (new Role())->setType(Role::STAFF),
            'parent' => (new Role())->setType(Role::PARENT),
            'student' => (new Role())->setType(Role::STUDENT),
        ];

        $users = [
            'super' => (new User)->setSuperuser(true)->setId(1),
            'staff' => (new User)->setRole($roles['staff'])->setId(2),
            'staffAlt' => (new User)->setRole($roles['staff'])->setId(3),
            'parent' => (new User)->setRole($roles['parent'])->setId(4),
            'student' => (new User)->setRole($roles['student'])->setId(5),
        ];

        $events = [
            'personal' => (new Event)->setAccess(Event::ACCESS_PERSONAL),
        ];

        // Create a mock user object, who is a child of $users['parent']
        /** @var User|\PHPUnit_Framework_MockObject_MockObject $parentChild */
        $users['parentChild'] = $this->getMockBuilder(User::class)->getMock();
        $users['parentChild']->method('getParents')->willReturnCallback(function() use ($users) {
            return new Collection([ $users['parent'] ]);
        });


        // --------------------------------------------------------------------------------
        // Personal level
        // --------------------------------------------------------------------------------

        /**
         * Staff can create an event for themselves
         */
        $event = (clone $events['personal'])
                    ->setOwner($users['staff'])
                    ->setAuthor($users['staff']);
        $tests['StaffPersonal'] = [true, $users['staff'], $event];

        /**
         * Parent can create an event for themselves
         */
        $event = (clone $events['personal'])
                    ->setOwner($users['parent'])
                    ->setAuthor($users['parent']);
        $tests['ParentPersonal'] = [true, $users['parent'], $event];

        /**
         * Student can create an event for themselves
         */
        $event = (clone $events['personal'])
                    ->setOwner($users['student'])
                    ->setAuthor($users['student']);
        $tests['StudentPersonal'] = [true, $users['student'], $event];


        /**
         * Super can create an event for staff member
         */
        $event = (clone $events['personal'])
                    ->setOwner($users['staff'])
                    ->setAuthor($users['super']);
        $tests['SuperForStaff'] = [true, $users['super'], $event];

        /**
         * Super can create an event for a parent
         */
        $event = (clone $events['personal'])
                    ->setOwner($users['parent'])
                    ->setAuthor($users['super']);
        $tests['SuperForParent'] = [true, $users['super'], $event];

        /**
         * Super can create an event for a student
         */
        $event = (clone $events['personal'])
                    ->setOwner($users['student'])
                    ->setAuthor($users['super']);
        $tests['SuperForStudent'] = [true, $users['super'], $event];


        /**
         * Staff can create an event for another staff member
         */
        $event = (clone $events['personal'])
                    ->setOwner($users['staffAlt'])
                    ->setAuthor($users['staff']);
        $tests['StaffForStaff'] = [true, $users['staff'], $event];


        /**
         * Staff can create event for any parent
         */
        $event = (clone $events['personal'])
                    ->setOwner($users['parent'])
                    ->setAuthor($users['staff']);
        $tests['StaffForParent'] = [true, $users['staff'], $event];

        /**
         * Staff can create event for any student
         */
        $event = (clone $events['personal'])
                    ->setOwner($users['student'])
                    ->setAuthor($users['staff']);
        $tests['StaffForStudent'] = [true, $users['staff'], $event];


        /**
         * Parent can create event for their child
         */
        $event = (clone $events['personal'])
                    ->setOwner($users['parentChild'])
                    ->setAuthor($users['parent']);
        $tests['ParentForChild'] = [true, $users['parent'], $event];


        /**
         * Parent can not create an event for a staff member
         * TODO: currently possible, add test with SA-4032 fix
         */

        /**
         * Parent can not create an event for another parent
         * TODO: currently possible, add test with SA-4032 fix
         */

        /**
         * Parent can not create an event for a student that isn't theirs
         * TODO: currently possible, add test with SA-4032 fix
         */

        /**
         * Student can not create event for a staff member
         * TODO: currently possible, add test with SA-4032 fix
         */

        /**
         * Student can not create event for a parent
         * TODO: currently possible, add test with SA-4032 fix
         */

        /**
         * Student can not create event for another student
         * TODO: currently possible, add test with SA-4032 fix
         */

        return $tests;
    }

    /**
     * @dataProvider dataCheckAccess
     *
     * @param $expected
     * @param User $user
     * @param Event $event
     * @param array $groups
     * @param array $campuses
     */
    public function testCheckAccess($expected, User $user, Event $event, $groups = [], $campuses = [])
    {
        $access = new Access();

        $actual = $access->checkAccess($user, $event, $groups, $campuses);
        $this->assertEquals($expected, $actual);

    }
}

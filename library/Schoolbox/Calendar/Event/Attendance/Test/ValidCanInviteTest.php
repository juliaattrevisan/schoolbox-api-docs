<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Calendar\Event\Attendance\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Calendar\Event\Attendance\ValidCanInvite;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Class ValidCanInviteTest
 *
 * Ensures that the config var "Allow students to invite others" doesn't regress
 *
 * See SD-10 for usages
 *
 * @package Schoolbox\Calendar\Event\Attendance\Test
 */
class ValidCanInviteTest extends TestCase
{
    /**
     * @param User $user
     * @param bool $configVar
     * @param bool $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validCanInvite')]
    public function testValidCanInvite(User $user, bool $configVar, bool $expected): void
    {
        $outcome = (new ValidCanInvite())->isSatisfiedBy($user, $configVar);

        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidCanInvite
     *
     * @return array
     */
    public static function provider_validCanInvite(): array
    {

        /* Superuser prototypes
         */
        $superuser = (new User())->setSuperuser(true)->setRole((new Role())->setType(Role::STAFF));
        $studentSuperuser = (new User())->setSuperuser(true)->setRole((new Role())->setType(Role::STUDENT));

        /* Non-superuser prototypes
         */
        $staff = (new User())->setSuperuser(false)->setRole((new Role())->setType(Role::STAFF));
        $student = (new User())->setSuperuser(false)->setRole((new Role())->setType(Role::STUDENT));
        $parent = (new User())->setSuperuser(false)->setRole((new Role())->setType(Role::PARENT));
        $guest = (new User())->setSuperuser(false)->setRole((new Role())->setType(Role::GUEST));
        $unknown = (new User())->setSuperuser(false)->setRole(new Role());

        return [

            /* Superuser can always add attendees, regardless of config var
             */
            'Super 1' => [
                $superuser,
                false,
                true
            ],

            'Super 2' => [
                $superuser,
                true,
                true
            ],

            /* Student Superuser can always add attendees, regardless of config var
             * I don't think this is a common possibility, but nothing stops you from doing it
             * In that case, superuser flag takes overrides everything
             */
            'Student Super 1' => [
                $studentSuperuser,
                true,
                true
            ],

            'Student Super 2' => [
                $studentSuperuser,
                false,
                true
            ],

            /* Staff can always add attendees, regardless of config var
             */
            'Staff 1' => [
                $staff,
                false,
                true
            ],

            'Staff 2' => [
                $staff,
                true,
                true
            ],

            /* Students can add attendees only if the config var is true
             */
            'Student 1' => [
                $student,
                true,
                true
            ],

            /* Students cannot add attendees if the config var is false
             */
            'Student 2' => [
                $student,
                false,
                false
            ],

            /* Everyone else cannot add attendees
             */
            'Parent 1' => [
                $parent,
                false,
                false
            ],

            'Parent 2' => [
                $parent,
                true,
                false
            ],

            'Guest 1' => [
                $guest,
                false,
                false
            ],

            'Guest 2' => [
                $guest,
                true,
                false
            ],

            /* When roletype is not specified, cannot add attendees
             * May result from stub-hydrated checks or malformed data structs
             */
            'Unknown RoleType 1' => [
                $unknown,
                false,
                false
            ],

            'Unknown RoleType 2' => [
                $unknown,
                true,
                false
            ],
        ];
    }
}

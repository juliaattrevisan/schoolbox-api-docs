<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Translator\Test;

use Alaress_Date_Format;
use House;
use HouseResidential;
use PHPUnit\Framework\TestCase;
use Schoolbox\Campus;
use Schoolbox\Config\TestLoader;
use Schoolbox\GenericCollection;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\YearLevel\Model as YearLevel;

/**
 * Test for the user model to array translator.
 *
 * @covers \Schoolbox\User\Translator\ModelToArray
 */
class ModelToArrayTest extends TestCase
{
    /**
     * Data provider for `testInvoke`.
     *
     * @return iterable
     */
    public function getTestInvokeData(): iterable
    {
        $configLoader = new TestLoader([
            'mail_address' => 'test.schoolbox.com.au',
            'name_format'  => '{title} {firstname} {lastname}',
        ]);

        $emptyUser = (new User())
        ->setFirstname(null)
        ->setLastname(null)
        ->setConfigLoader($configLoader);

        $emptyResponse = [
            'id'               => null,
            'campus'           => null,
            'role'             => null,
            'groups'           => null,
            'schoolHouse'      => null,
            'guardians'        => null,
            'students'         => null,
            'impersonableUser' => null,
            'email'            => null,
            '_links'           => [],
            'externalId'       => null,
            'username'         => null,
            'superuser'        => null,
            'enabled'          => false,
            'title'            => null,
            'firstName'        => null,
            'preferredName'    => null,
            'lastName'         => null,
            'fullName'         => 'Unknown User',
            'isEmailDerived'   => null,
            'altEmail'         => null,
            'positionTitle'    => null,
            'yearLevel'        => null,
            'isDeleted'        => false,
            'isBoarder'        => false,
            'flags'            => [
                'showWebmailTab'         => null,
                'showContacts'           => true,
                'enableTimetable'        => true,
                'showSideNav'            => true,
                'showNotificationsPanel' => true,
                'showGradeGraph'         => null,
                'enableEportfolio'       => null,
                'useInternalMail'        => null,
                'calendarView'           => 'month',
            ],
        ];
        yield 'empty fields' => [
            clone $emptyUser,
            $emptyResponse,
        ];

        $populatedUser = (new User())
            ->setConfigLoader($configLoader)
            ->setId(1)
            ->setCampusCollection(
                (new GenericCollection(Campus::class))
                    ->add((new Campus())
                        ->setId(1)
                        ->setName('B')
                        ->setExtTimetableCode('C')
                        ->setTimetableDay(5))
            )
            ->setRole((new Role())
                ->setId(3)
                ->setName('Admin')
                ->setType(Role::STAFF))
            ->setSchoolHouse((new House())
                ->setId(1)
                ->setHouse('School House'))
            ->setResidentialHouse((new HouseResidential())
                ->setId(1)
                ->setHouse('Res House'))
            ->setExternalId('7')
            ->setUsername('test')
            ->setSuperuser(false)
            ->setEnabled(true)
            ->setTitle('Mr')
            ->setFirstname('John')
            ->setPreferredName('Jack')
            ->setLastname('Doe')
            ->setLocalEmail(false)
            ->setAltEmail('mrjohndoe@mail.com')
            ->setPositionTitle('Tester')
            ->setYear((new YearLevel())
                ->setId(1)
                ->setName('Year 12'))
            ->setBoardingStatus('Y')
            ->setWebmail(false)
            ->setHideContacts(true)
            ->setHideTimetable(true)
            ->setHideSidenav(true)
            ->setHideNotificationsTray(true)
            ->setDisplayGradeGraph(false)
            ->setEportfolioEnable(false)
            ->setUseInternalMail(false)
            ->setCalendarView('week');
        yield 'populated fields' => [
            $populatedUser,
            [
                'id'               => 1,
                'campus'           => [
                    [
                        'id'   => 1,
                        'name' => 'B',
                        'code' => 'C',
                        'days' => 5,
                    ],
                ],
                'role'             => [
                    'id'   => 3,
                    'name' => 'Admin',
                    'type' => Role::STAFF,
                ],
                'groups'           => null,
                'schoolHouse'      => [
                    'id'   => 1,
                    'name' => 'School House',
                ],
                'guardians'        => null,
                'students'         => null,
                'impersonableUser' => null,
                'email'            => 'mrjohndoe@mail.com',
                '_links'           => [
                    'profile' => '/search/user/1',
                    'avatar'  => '/portrait.php?id=1&size=square64',
                ],
                'externalId'       => '7',
                'username'         => 'test',
                'superuser'        => false,
                'enabled'          => true,
                'title'            => 'Mr',
                'firstName'        => 'John',
                'preferredName'    => 'Jack',
                'lastName'         => 'Doe',
                'fullName'         => 'Mr John Doe',
                'isEmailDerived'   => false,
                'altEmail'         => 'mrjohndoe@mail.com',
                'positionTitle'    => 'Tester',
                'yearLevel'        => [
                    'id'   => 1,
                    'name' => 'Year 12',
                ],
                'isDeleted'        => false,
                'isBoarder'        => true,
                'flags'            => [
                    'showWebmailTab'         => false,
                    'showContacts'           => false,
                    'enableTimetable'        => false,
                    'showSideNav'            => false,
                    'showNotificationsPanel' => false,
                    'showGradeGraph'         => false,
                    'enableEportfolio'       => false,
                    'useInternalMail'        => false,
                    'calendarView'           => 'week',
                ],
            ],
        ];

        // Test some individual fields
        $inverseFlags          = $emptyResponse;
        $inverseFlags['flags'] = [
            'showWebmailTab'         => true,
            'showContacts'           => true,
            'enableTimetable'        => true,
            'showSideNav'            => true,
            'showNotificationsPanel' => true,
            'showGradeGraph'         => true,
            'enableEportfolio'       => true,
            'useInternalMail'        => true,
            'calendarView'           => 'month',
        ];
        yield 'inverse flags' => [
            (clone $emptyUser)
                ->setWebmail(true)
                ->setHideContacts(false)
                ->setHideTimetable(false)
                ->setHideSidenav(false)
                ->setHideNotificationsTray(false)
                ->setDisplayGradeGraph(true)
                ->setEportfolioEnable(true)
                ->setUseInternalMail(true),
            $inverseFlags,
        ];


        $superuser              = $emptyResponse;
        $superuser['superuser'] = true;
        yield 'superuser' => [
            (clone $emptyUser)
                ->setSuperuser(true),
            $superuser,
        ];


        $derivedEmail                   = $emptyResponse;
        $derivedEmail['username']       = 'test';
        $derivedEmail['isEmailDerived'] = true;
        $derivedEmail['email']          = 'test@test.schoolbox.com.au';

        // This relies upon the user's config loader.
        // Unlike with the username I can't really fake this
        yield 'derived email' => [
            (clone $emptyUser)
                ->setUsername('test')
                ->setLocalEmail(true),
            $derivedEmail,
        ];

        // Deleted user
        $deletedUser              = $emptyResponse;
        $deletedUser['isDeleted'] = true;
        yield 'deleted user' => [
            (clone $emptyUser)
                ->setDeletedAt(Alaress_Date_Format::now()),
            $deletedUser,
        ];

        // Campus indexed by ID
        $campusIndexedById           = $emptyResponse;
        $campusIndexedById['campus'] = [
            [
                'id'   => 333,
                'name' => 'B',
                'code' => 'C',
                'days' => 5,
            ],
            [
                'id'   => 555,
                'name' => 'Z',
                'code' => '&',
                'days' => 10,
            ],
        ];
        yield 'campus indexed by ID' => [
            (clone $emptyUser)
                ->setCampusCollection(
                    (new GenericCollection(Campus::class))
                        ->setOffset(
                            333,
                            (new Campus())
                                ->setId(333)
                                ->setName('B')
                                ->setExtTimetableCode('C')
                            ->setTimetableDay(5)
                        )
                        ->setOffset(
                            555,
                            (new Campus())
                                ->setId(555)
                                ->setName('Z')
                                ->setExtTimetableCode('&')
                            ->setTimetableDay(10)
                        )
                ),
            $campusIndexedById,
        ];
    }


    /**
     * @dataProvider getTestInvokeData
     * @covers       \Schoolbox\User\Translator\ModelToArray::__invoke
     * @param User  $input
     * @param array $expected
     */
    public function testInvoke(User $input, array $expected): void
    {
        self::assertSame($expected, (new User\Translator\ModelToArray())($input));
    }
}

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
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\External\Normalise\User\Staff\Year;
use Schoolbox\GenericCollection;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\YearLevel\Model as YearLevel;

/**
 * Test for the user model to array translator.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\User\Translator\ModelToArray::class)]
class ModelToArrayTest extends TestCase
{
    /**
     * Data provider for `testInvoke`.
     *
     * @return iterable
     */
    public static function getTestInvokeData(): iterable
    {
        $configLoader = new ArrayLoader([
            ConfigMapper::NAME_MAIL_ADDRESS => 'test.schoolbox.com.au',
            ConfigMapper::NAME_NAME_FORMAT  => '{title} {firstname} {lastname}',
        ]);

        $emptyUser = (new User())
            ->setFirstname(null)
            ->setLastname(null)
            ->setEmailDomain($configLoader->get(ConfigMapper::NAME_MAIL_ADDRESS))
            ->setNameFormat($configLoader->get(ConfigMapper::NAME_NAME_FORMAT));

        $emptyResponse = [
            'id'               => null,
            'campus'           => null,
            'role'             => null,
            'groups'           => null,
            'schoolHouse'      => null,
            'guardians'        => null,
            'students'         => null,
            'impersonableUsers' => null,
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
            'flags'            => [
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
            ->setEmailDomain($configLoader->get(ConfigMapper::NAME_MAIL_ADDRESS))
            ->setNameFormat($configLoader->get(ConfigMapper::NAME_NAME_FORMAT))
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
            ->setHideContacts(true)
            ->setHideTimetable(true)
            ->setHideSidenav(true)
            ->setHideNotificationsTray(true)
            ->setDisplayGradeGraph(false)
            ->setEportfolioEnable(false)
            ->setUseInternalMail(false)
            ->setCalendarView('week')
            ->setLinkedUsers(
                new Collection(
                    [
                        (new User())
                        ->setId(2)
                        ->setEmailDomain($configLoader->get(ConfigMapper::NAME_MAIL_ADDRESS))
                        ->setNameFormat($configLoader->get(ConfigMapper::NAME_NAME_FORMAT))
                        ->setTitle('Mrs')
                        ->setFirstname('Kim')
                        ->setPreferredName('Kimmy')
                        ->setLastname('Impersonable')
                        ->setRole(
                            (new Role())
                            ->setId(3)
                            ->setName('Admin')
                            ->setType(Role::STAFF)
                        )
                        ->setYear(
                            (new YearLevel())
                            ->setId(4)
                            ->setName('12')
                        ),
                        (new User())
                        ->setId(3)
                        ->setEmailDomain($configLoader->get(ConfigMapper::NAME_MAIL_ADDRESS))
                        ->setNameFormat($configLoader->get(ConfigMapper::NAME_NAME_FORMAT))
                        ->setFirstname('Tim')
                        ->setLastname('Impersonable')
                        ->setRole(
                            (new Role())
                            ->setId(3)
                            ->setName('Admin')
                            ->setType(Role::STAFF)
                        ),
                    ]
                )
            );
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
                'impersonableUsers' => [
                    [
                        'id' => 2,
                        'externalId' => 2,
                        'title' => 'Mrs',
                        'firstName' => 'Kim',
                        'preferredName' => 'Kimmy',
                        'givenName' => 'Kimmy',
                        'lastName' => 'Impersonable',
                        'fullName' => 'Mrs Kim Impersonable',
                        'role' => [
                            'id' => 3,
                            'name' => 'Admin',
                            'type' => Role::STAFF
                        ],
                        'yearLevel' => [
                            'id' => 4,
                            'name' => '12'
                        ]
                    ],
                    [
                        'id' => 3,
                        'externalId' => 3,
                        'title' => null,
                        'firstName' => 'Tim',
                        'preferredName' => null,
                        'givenName' => 'Tim',
                        'lastName' => 'Impersonable',
                        'fullName' => 'Tim Impersonable',
                        'role' => [
                            'id' => 3,
                            'name' => 'Admin',
                            'type' => Role::STAFF
                        ],
                        'yearLevel' => null
                    ]
                ],
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
                'flags'            => [
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
     * @param User  $input
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeData')]
    public function testInvoke(User $input, array $expected): void
    {
        self::assertSame($expected, (new User\Translator\ModelToArray())($input));
    }
}

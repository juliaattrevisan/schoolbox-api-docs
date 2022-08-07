<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Translator\Author\Test;

use Alaress_Date_Format;
use House;
use PHPUnit\Framework\TestCase;
use Schoolbox\Campus;
use Schoolbox\Config\TestLoader;
use Schoolbox\GenericCollection;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\User\Translator\Author\ModelToArray;
use Schoolbox\YearLevel\Model as YearLevel;

/**
 * Test for the author variant of the user model to array translator.
 *
 * @covers \Schoolbox\User\Translator\Author\ModelToArray
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
            'id'            => null,
            '_links'        => [],
            'title'         => null,
            'firstName'     => null,
            'preferredName' => null,
            'lastName'      => null,
            'fullName'      => 'Unknown User',
            'isDeleted'     => false,
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
                'id'            => 1,
                '_links'        => [
                    'profile' => '/search/user/1',
                    'avatar'  => '/portrait.php?id=1&size=square64',
                ],
                'title'         => 'Mr',
                'firstName'     => 'John',
                'preferredName' => 'Jack',
                'lastName'      => 'Doe',
                'fullName'      => 'Mr John Doe',
                'isDeleted'     => false,
            ],
        ];

        // Deleted user
        $deletedUser              = $emptyResponse;
        $deletedUser['isDeleted'] = true;
        yield 'deleted user' => [
            clone $emptyUser
                ->setDeletedAt(Alaress_Date_Format::now()),
            $deletedUser,
        ];
    }


    /**
     * @dataProvider getTestInvokeData
     * @covers       \Schoolbox\User\Translator\Author\ModelToArray::__invoke
     * @param User  $input
     * @param array $expected
     */
    public function testInvoke(User $input, array $expected): void
    {
        self::assertSame($expected, (new ModelToArray())($input));
    }
}

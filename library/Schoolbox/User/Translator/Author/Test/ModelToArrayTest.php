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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\GenericCollection;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\User\Translator\Author\ModelToArray;
use Schoolbox\YearLevel\Model as YearLevel;
use Schoolbox\User\Pronoun\Model as Pronoun;

/**
 * Test for the author variant of the user model to array translator.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\User\Translator\Author\ModelToArray::class)]
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
            'id'            => null,
            '_links'        => [],
            'title'         => null,
            'firstName'     => null,
            'preferredName' => null,
            'pronoun'       => null,
            'lastName'      => null,
            'fullName'      => 'Unknown User',
            'isDeleted'     => false,
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
            ->setExternalId('7')
            ->setUsername('test')
            ->setSuperuser(false)
            ->setEnabled(true)
            ->setTitle('Mr')
            ->setFirstname('John')
            ->setPreferredName('Jack')
            ->setLastname('Doe')
            ->setPronoun((new Pronoun())
                             ->setId(1)
                             ->setSubjective('he')
                             ->setObjective('him')
                             ->setPossessive('his'))
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
                'pronoun'       => '(he/him)',
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
     * @param User  $input
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeData')]
    public function testInvoke(User $input, array $expected): void
    {
        self::assertSame($expected, (new ModelToArray())($input));
    }
}

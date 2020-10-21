<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Translator\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\User;

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
        yield 'empty fields' => [
            new User(),
            [
                'id'               => null,
                'campus'           => null,
                'role'             => null,
                'groups'           => null,
                'schoolHouse'      => null,
                'residentialHouse' => null,
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
                'lastName'         => null,
                'fullName'         => 'Unknown User',
                'isEmailDerived'   => null,
                'altEmail'         => null,
                'positionTitle'    => null,
                'yearLevel'        => null,
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
            ],
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

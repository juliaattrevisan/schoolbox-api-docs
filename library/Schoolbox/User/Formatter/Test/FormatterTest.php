<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Formatter\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\User;

/**
 * @covers \Schoolbox\User\Formatter\Formatter
 */
class FormatterTest extends TestCase
{
    /**
     * @dataProvider getTestFormatObjectData
     * @covers \Schoolbox\User\Formatter\Formatter::formatObject
     *
     * @param string $expected
     * @param User   $user
     * @param string $format
     * @return void
     */
    public function testFormatObject(string $expected, User $user, string $format): void
    {
        $this->assertSame($expected, (new User\Formatter\Formatter($format))->formatObject($user));
    }

    /**
     * Data provider for `testFormatObject`
     *
     * @return iterable
     */
    public function getTestFormatObjectData(): iterable
    {
        yield 'empty everything' => [
            'Unknown User',
            new User(),
            '',
        ];

        yield 'empty format' => [
            'Unknown User',
            (new User())
                ->setFirstname('A')
                ->setLastname('B'),
            '',
        ];

        yield 'empty user' => [
            'Unknown User',
            new User(),
            '{firstname} {lastname}',
        ];

        yield 'standard' => [
            'Mr A B',
            (new User())
                ->setTitle('Mr')
                ->setFirstname('A')
                ->setLastname('B'),
            '{title} {firstname} {lastname}',
        ];

        yield 'title + last name' => [
            'Mr B',
            (new User())
                ->setTitle('Mr')
                ->setFirstname('A')
                ->setLastname('B'),
            '{title} {lastname}',
        ];

        yield 'last name, firstname' => [
            'B, A',
            (new User())
                ->setTitle('Mr')
                ->setFirstname('A')
                ->setLastname('B'),
            '{lastname}, {firstname}',
        ];

        yield 'no title' => [
            'A B',
            (new User())
                ->setFirstname('A')
                ->setLastname('B'),
            '{title} {firstname} {lastname}',
        ];

        yield 'no first name' => [
            'Mr  B',
            (new User())
                ->setTitle('Mr')
                ->setLastname('B'),
            '{title} {firstname} {lastname}',
        ];

        yield 'no last name' => [
            'Mr A',
            (new User())
                ->setTitle('Mr')
                ->setFirstname('A'),
            '{title} {firstname} {lastname}',
        ];

        yield 'preferred name set' => [
            'B',
            (new User())
                ->setFirstname('A')
                ->setPreferredName('B'),
            '{preferredname}',
        ];

        yield 'preferred name not set' => [
            'A',
            (new User())
                ->setFirstname('A'),
            '{preferredname}',
        ];

        yield 'unrecognized format' => [
            'Unknown User',
            (new User())
                ->setTitle('Mr')
                ->setFirstname('A')
                ->setPreferredName('B'),
            '{unrecognized}',
        ];

        yield 'partially unrecognized format' => [
            'Mr',
            (new User())
                ->setTitle('Mr')
                ->setFirstname('A')
                ->setPreferredName('B'),
            '{title} {unrecognized}',
        ];
    }

    /**
     * @dataProvider getTestFormatArrayData
     * @covers \Schoolbox\User\Formatter\Formatter::formatArray
     *
     * @param string $expected
     * @param array  $userInfo
     * @param string $format
     * @return void
     */
    public function testFormatArray(string $expected, array $userInfo, string $format): void
    {
        $this->assertSame($expected, (new User\Formatter\Formatter($format))->formatArray($userInfo));
    }

    /**
     * Data provider for `testFormatArray`
     *
     * @return iterable
     */
    public function getTestFormatArrayData(): iterable
    {
        yield 'empty everything' => [
            'Unknown User',
            [],
            '',
        ];

        yield 'empty format' => [
            'Unknown User',
            [
                'firstname' => 'A',
                'lastname'  => 'B',
            ],
            '',
        ];

        yield 'empty user' => [
            'Unknown User',
            [],
            '{firstname} {lastname}',
        ];

        yield 'standard' => [
            'Mr A B',
            [
                'title'     => 'Mr',
                'firstname' => 'A',
                'lastname'  => 'B',
            ],
            '{title} {firstname} {lastname}',
        ];

        yield 'title + last name' => [
            'Mr B',
            [
                'title'     => 'Mr',
                'firstname' => 'A',
                'lastname'  => 'B',
            ],
            '{title} {lastname}',
        ];

        yield 'last name, firstname' => [
            'B, A',
            [
                'title'     => 'Mr',
                'firstname' => 'A',
                'lastname'  => 'B',
            ],
            '{lastname}, {firstname}',
        ];

        yield 'no title' => [
            'A B',
            [
                'title'     => '',
                'firstname' => 'A',
                'lastname'  => 'B',
            ],
            '{title} {firstname} {lastname}',
        ];

        yield 'no first name' => [
            'Mr  B',
            [
                'title'     => 'Mr',
                'firstname' => '',
                'lastname'  => 'B',
            ],
            '{title} {firstname} {lastname}',
        ];

        yield 'no last name' => [
            'Mr A',
            [
                'title'     => 'Mr',
                'firstname' => 'A',
                'lastname'  => '',
            ],
            '{title} {firstname} {lastname}',
        ];

        yield 'preferred name set' => [
            'B',
            [
                'firstname'     => 'A',
                'preferredname' => 'B',
            ],
            '{preferredname}',
        ];

        yield 'preferred name not set' => [
            'A',
            [
                'firstname'     => 'A',
                'preferredname' => '',
            ],
            '{preferredname}',
        ];

        yield 'unrecognized format' => [
            'Unknown User',
            [
                'title'     => 'Mr',
                'firstname' => 'A',
                'lastname'  => 'B',
            ],
            '{unrecognized}',
        ];

        yield 'partially unrecognized format' => [
            'Mr',
            [
                'title'     => 'Mr',
                'firstname' => 'A',
                'lastname'  => 'B',
            ],
            '{title} {unrecognized}',
        ];
    }
}

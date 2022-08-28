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
    }
}

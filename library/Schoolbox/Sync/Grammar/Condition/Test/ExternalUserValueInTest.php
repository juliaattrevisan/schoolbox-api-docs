<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Sync\Grammar\Condition\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Normalise\User\Base;
use Schoolbox\External\Normalise\User\Common\Email;
use Schoolbox\External\Normalise\User\Staff;
use Schoolbox\Sync\Grammar\Condition\ExternalUserValueIn;

class ExternalUserValueInTest extends TestCase
{
    /**
     * @dataProvider getTestAssertInData
     * @covers \Schoolbox\Sync\Grammar\Condition\ExternalUserValueIn::assertIn
     *
     * @param string           $field
     * @param array|string|int $validValues
     * @param Base             $externalUser
     * @param bool             $expected
     * @return void
     */
    public function testAssertIn(
        string $field,
        array|string|int $validValues,
        Base $externalUser,
        bool $expected,
    ): void {
        // negate is meaningless in this class:
        // currently, negation is tested in `\Alaress\Rules\Rule::apply`
        $condition = new ExternalUserValueIn($field, $validValues);
        $this->assertSame($expected, $condition->assertIn($externalUser));
    }

    /**
     * Data provider for `testAssertIn`
     *
     * @return iterable
     */
    public function getTestAssertInData(): iterable
    {
        $staffUser = (new Staff())
            ->setGivenName('Jack')
            ->addEmail((new Email())->setAddress('jack@example.com'))
            ->addEmail((new Email())->setAddress('john@example.com'))
        ;
        yield 'match on single user name' => [
            'givenName',
            'Jack',
            $staffUser,
            true,
        ];
        yield 'match on multiple user names' => [
            'givenName',
            ['Jack', 'John'],
            $staffUser,
            true,
        ];
        yield 'no match on single user name' => [
            'givenName',
            'Jill',
            $staffUser,
            false,
        ];
        yield 'no match on multiple user names' => [
            'givenName',
            ['Jill', 'John'],
            $staffUser,
            false,
        ];

        yield 'match on single email address' => [
            'email.address',
            'jack@example.com',
            $staffUser,
            true,
        ];
        yield 'match on multiple email addresses' => [
            'email.address',
            ['john@example.com', 'jill@example.com',],
            $staffUser,
            true,
        ];
        yield 'no match on single email address' => [
            'email.address',
            'jill@example.com',
            $staffUser,
            false,
        ];
        yield 'no match on multiple email addresses' => [
            'email.address',
            ['james@example.com', 'jill@example.com',],
            $staffUser,
            false,
        ];
    }
}

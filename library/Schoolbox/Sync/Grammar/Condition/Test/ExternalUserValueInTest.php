<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Sync\Grammar\Condition\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Normalise\User\Base;
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
     * @param bool             $negate
     * @param Base             $externalUser
     * @param bool             $expected
     * @return void
     */
    public function testAssertIn(
        string $field,
        array|string|int $validValues,
        bool $negate,
        Base $externalUser,
        bool $expected,
    ): void {
        // negate is meaningless in this class:
        // currently, negation is tested in `\Alaress\Rules\Rule::apply`
        $condition = new ExternalUserValueIn($field, $validValues, $negate);
        $this->assertSame($expected, $condition->assertIn($externalUser));
    }

    /**
     * Data provider for `testAssertIn`
     *
     * @return iterable
     */
    public function getTestAssertInData(): iterable
    {
        yield 'match on single user name' => [
            'givenName',
            'Jack',
            false,
            (new Staff())->setGivenName('Jack'),
            true,
        ];
        yield 'match on single user name: negated' => [
            'givenName',
            'Jack',
            true,
            (new Staff())->setGivenName('Jack'),
            false,
        ];
        yield 'match on multiple user names' => [
            'givenName',
            ['Jack', 'John'],
            false,
            (new Staff())->setGivenName('Jack'),
            true,
        ];
        yield 'match on multiple user names: negated' => [
            'givenName',
            ['Jack', 'John'],
            true,
            (new Staff())->setGivenName('Jack'),
            false,
        ];
        yield 'no match on single user name' => [
            'givenName',
            'Jack',
            false,
            (new Staff())->setGivenName('Jill'),
            false,
        ];
        yield 'no match on single user name: negated' => [
            'givenName',
            'Jack',
            true,
            (new Staff())->setGivenName('Jill'),
            true,
        ];
        yield 'no match on multiple user names' => [
            'givenName',
            ['Jack', 'John'],
            false,
            (new Staff())->setGivenName('Jill'),
            false,
        ];
        yield 'no match on multiple user names: negated' => [
            'givenName',
            ['Jack', 'John'],
            true,
            (new Staff())->setGivenName('Jill'),
            true,
        ];
    }
}

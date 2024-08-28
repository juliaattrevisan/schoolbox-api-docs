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

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Sync\Grammar\Condition\ExternalUserValueIn::class)]
class ExternalUserValueInTest extends TestCase
{
    /**
     *
     * @param string           $field
     * @param array|string|int $validValues
     * @param Base             $externalUser
     * @param bool             $expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestAssertInData')]
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
    public static function getTestAssertInData(): iterable
    {
        $staffUser = (new Staff())
            ->setGivenName('Jack')
            ->addEmail((new Email())->setAddress('jack@example.com'))
            ->addEmail((new Email())->setAddress('john@example.com'))
        ;

        // Simple match (or not) on name
        yield 'match on single valid name' => [
            'givenName',
            'Jack',
            $staffUser,
            true,
        ];
        yield 'no match on single valid name' => [
            'givenName',
            'Jill',
            $staffUser,
            false,
        ];
        yield 'match on multiple valid names' => [
            'givenName',
            ['Jack', 'John'],
            $staffUser,
            true,
        ];
        yield 'no match on multiple valid names' => [
            'givenName',
            ['Jill', 'John'],
            $staffUser,
            false,
        ];
        yield 'match on single valid name (case-insensitive)' => [
            'givenName',
            'jACk',
            $staffUser,
            true,
        ];
        yield 'match on multiple valid names (case-insensitive)' => [
            'givenName',
            ['jACk', 'JohN'],
            $staffUser,
            true,
        ];

        // Simple match (or not) on email addresses
        // (a user may have more than one)
        yield 'match on single valid email address' => [
            'email.address',
            'jack@example.com',
            $staffUser,
            true,
        ];
        yield 'no match on single valid email address' => [
            'email.address',
            'jill@example.com',
            $staffUser,
            false,
        ];
        yield 'no match on multiple valid email addresses' => [
            'email.address',
            ['james@example.com', 'jill@example.com',],
            $staffUser,
            false,
        ];
        yield 'some matches on multiple valid email addresses' => [
            'email.address',
            ['john@example.com', 'jill@example.com',],
            $staffUser,
            true,
        ];
        yield 'all matches on multiple valid email addresses' => [
            'email.address',
            ['john@example.com', 'jack@example.com',],
            $staffUser,
            true,
        ];

        // Pattern matching: test quantity of matches
        yield 'match on single valid name pattern' => [
            'givenName',
            'j*',
            $staffUser,
            true,
        ];
        yield 'no match on single valid name pattern' => [
            'givenName',
            'b*',
            $staffUser,
            false,
        ];
        yield 'no match on multiple valid name patterns' => [
            'givenName',
            ['b*', 'q*'],
            $staffUser,
            false,
        ];
        yield 'some matches on multiple valid name patterns' => [
            'givenName',
            ['j*', 'q*'],
            $staffUser,
            true,
        ];
        yield 'all matches on multiple valid name patterns' => [
            'givenName',
            ['j*', '*n'],
            $staffUser,
            true,
        ];
        yield 'no match on escaped name pattern' => [
            'givenName',
            'j\\*',
            $staffUser,
            false,
        ];

        // Pattern matching: various types of pattern
        yield 'match wildcard' => [
            'givenName',
            '*',
            $staffUser,
            true,
        ];
        yield 'match leading wildcard' => [
            'givenName',
            'j*',
            $staffUser,
            true,
        ];
        yield 'no match leading wildcard' => [
            'givenName',
            'k*',
            $staffUser,
            false,
        ];
        yield 'match trailing wildcard' => [
            'givenName',
            '*k',
            $staffUser,
            true,
        ];
        yield 'no match trailing wildcard' => [
            'givenName',
            '*q',
            $staffUser,
            false,
        ];
        yield 'match leading and trailing wildcard' => [
            'givenName',
            '*a*',
            $staffUser,
            true,
        ];
        yield 'no match leading and trailing wildcard' => [
            'givenName',
            '*b*',
            $staffUser,
            false,
        ];
        yield 'match enclosed wildcard' => [
            'givenName',
            'j*k',
            $staffUser,
            true,
        ];
        yield 'no match enclosed wildcard' => [
            'givenName',
            'q*k',
            $staffUser,
            false,
        ];

        yield 'match single-character wildcard' => [
            'givenName',
            '?ack',
            $staffUser,
            true,
        ];
        yield 'no match single-character wildcard' => [
            'givenName',
            '?bck',
            $staffUser,
            false,
        ];
        yield 'match set' => [
            'givenName',
            '[bj]ack',
            $staffUser,
            true,
        ];
        yield 'no match set' => [
            'givenName',
            '[bl]ack',
            $staffUser,
            false,
        ];
        yield 'match range' => [
            'givenName',
            '[a-k]ack',
            $staffUser,
            true,
        ];
        yield 'no match range' => [
            'givenName',
            '[l-z]ack',
            $staffUser,
            false,
        ];
        yield 'match set negation' => [
            'givenName',
            '[^bl]ack',
            $staffUser,
            true,
        ];
        yield 'no match set negation' => [
            'givenName',
            '[^bj]ack',
            $staffUser,
            false,
        ];
        yield 'match range negation' => [
            'givenName',
            '[^l-z]ack',
            $staffUser,
            true,
        ];
        yield 'no match range negation' => [
            'givenName',
            '[^a-k]ack',
            $staffUser,
            false,
        ];
        yield 'match set negation (! format)' => [
            'givenName',
            '[!bl]ack',
            $staffUser,
            true,
        ];
        yield 'no match set negation (! format)' => [
            'givenName',
            '[!bj]ack',
            $staffUser,
            false,
        ];
        yield 'match range negation (! format)' => [
            'givenName',
            '[!l-z]ack',
            $staffUser,
            true,
        ];
        yield 'no match range negation (! format)' => [
            'givenName',
            '[!a-k]ack',
            $staffUser,
            false,
        ];

        // Multiple patterns in valid value
        yield 'match on single valid name pattern with multiple sub-patterns' => [
            'givenName',
            '{jack,john}',
            $staffUser,
            true,
        ];
        yield 'no match on single valid name pattern with multiple sub-patterns' => [
            'givenName',
            '{john,jill}',
            $staffUser,
            false,
        ];

        // The (=foo) and (!foo) patterns are lookaheads:
        // I haven't found a use for them, but I can't ignore them
        yield 'match on positive lookahead' => [
            'givenName',
            'j(=ack)*',
            $staffUser,
            true,
        ];
        yield 'no match on positive lookahead' => [
            'givenName',
            'j(=ohn)*',
            $staffUser,
            false,
        ];
        yield 'match on negative lookahead' => [
            'givenName',
            'j(!ohn)*',
            $staffUser,
            true,
        ];
        yield 'no match on negative lookahead' => [
            'givenName',
            'j(!ack)*',
            $staffUser,
            false,
        ];

        // Invalid patterns:
        // I'd like to raise a warning, but I cannot without having different
        // behavior when running UPS on the command line and dry run: the CLI
        // uses the daemon kernel, which throws errors on warning trigger.
        // (Of course this class shouldn't have any dependency on which kernel
        //  creates it: undoing said dependency is another problem though.)
        yield 'invalid pattern' => [
            'givenName',
            '{jack',
            $staffUser,
            false,
        ];
        yield 'valid and invalid patterns: valid patterns still match' => [
            'givenName',
            ['{jack', 'jack'],
            $staffUser,
            true,
        ];
    }
}

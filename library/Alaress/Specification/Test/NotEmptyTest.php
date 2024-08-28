<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Specification\Test;

use Alaress\Specification\NotEmpty;
use PHPUnit\Framework\TestCase;
use Schoolbox\Collection;
use stdClass;
use TypeError;

#[\PHPUnit\Framework\Attributes\CoversClass(\Alaress\Specification\NotEmpty::class)]
class NotEmptyTest extends TestCase
{
    /**
     * Data for `testIsSatisfiedBy`.
     *
     * @return iterable
     */
    public static function getTestIsSatisfiedByData(): iterable
    {
        yield 'empty array'            => [[], false];
        yield 'non-empty array(1)'     => [[1], true];
        yield 'non-empty array(3)'     => [[1, 2, 5], true];
        yield 'empty countable'        => [new Collection([]), false];
        yield 'non-empty countable(1)' => [new Collection([1]), true];
        yield 'non-empty countable(3)' => [new Collection([1, 3, 5]), true];
    }


    /**
     * Test for valid cases for `NotEmpty`.
     *
     *
     * @param mixed $candidate
     * @param bool  $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsSatisfiedByData')]
    public function testIsSatisfiedBy($candidate, bool $expected): void
    {
        $this->assertSame($expected, (new NotEmpty())->isSatisfiedBy($candidate));
    }


    /**
     * Data for `testIsSatisfiedByInvalidCandidate`.
     *
     * @return iterable
     */
    public static function getTestIsSatisfiedByInvalidCandidateData(): iterable
    {
        yield 'null'                 => [null];
        yield 'string'               => ['b'];
        yield 'int'                  => [1];
        yield 'bool'                 => [true];
        yield 'non-countable object' => [new stdClass()];
    }


    /**
     * Test for invalid cases for `NotEmpty`.
     *
     *
     *
     * @param mixed $candidate
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsSatisfiedByInvalidCandidateData')]
    public function testIsSatisfiedByInvalidCandidate($candidate): void
    {
        $this->expectException(TypeError::class);
        (new NotEmpty())->isSatisfiedBy($candidate);
    }
}

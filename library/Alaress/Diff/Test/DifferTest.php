<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Diff\Test;

use Alaress\Diff\Differ;
use PHPUnit\Framework\TestCase;

class DifferTest extends TestCase
{
    /**
     * Test various diff cases
     *
     * @param Differ $differ
     * @param array  $a
     * @param array  $b
     * @param array  $expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestDiffData')]
    public function testDiff(Differ $differ, array $a, array $b, array $expected): void
    {
        $this->assertEquals($differ->diff($a, $b), $expected);
    }

    /**
     * Data provider for `testDiff`
     *
     * @return array
     */
    public static function getTestDiffData(): array
    {
        $standardDiffer  = new Differ();
        $stringKeyDiffer = new Differ('expected', 'actual');
        return [
            'matching flat' => [
                $standardDiffer,
                ['a' => 1, 'b' => 2],
                ['a' => 1, 'b' => 2],
                [],
            ],
            'matching recursive' => [
                $standardDiffer,
                ['a' => 1, 'b' => ['c' => 2, 'd' => ['e' => 3, 'f' => 4,], 'g' => 5]],
                ['a' => 1, 'b' => ['c' => 2, 'd' => ['e' => 3, 'f' => 4,], 'g' => 5]],
                [],
            ],
            'non-matching flat, same structure' => [
                $standardDiffer,
                ['a' => 1, 'b' => 2],
                ['a' => 1, 'b' => 3],
                ['b' => [2, 3]],
            ],
            'non-matching flat, different structure' => [
                $standardDiffer,
                ['a' => 1, 'b' => 2, 'd' => 3],
                ['a' => 1, 'c' => 2],
                ['b' => [2, null], 'c' => [null, 2], 'd' => [3, null]],
            ],
            'non-matching recursive, same structure' => [
                $standardDiffer,
                ['a' => 1, 'b' => ['c' => 3, 'd' => ['e' => 3, 'f' => 4,], 'g' => 5]],
                ['a' => 1, 'b' => ['c' => 2, 'd' => ['e' => 3, 'f' => 4,], 'g' => 5]],
                ['b' => ['c' => [3, 2]]],
            ],
            'non-matching recursive, different structure' => [
                $standardDiffer,
                ['a' => 1, 'b' => ['c' => 2, 'd' => ['e' => 3, 'f' => 4,], 'g' => 5]],
                ['a' => 1, 'b' => ['c' => 2, 'e' => ['e' => 3, 'f' => 4,], 'g' => 5]],
                ['b' => [
                    'd' => ['e' => [3, null], 'f' => [4, null],],
                    'e' => ['e' => [null, 3], 'f' => [null, 4],],
                ]],
            ],
            'explicit null value' => [
                $standardDiffer,
                ['a' => null, 'b' => 2],
                ['a' => null, 'b' => null],
                ['b' => [2, null]],
            ],
            'flat with array and scalar value' => [
                $standardDiffer,
                ['a' => 1, '1234567890' => 2],
                ['a' => 1, '1234567890' => ['c' => 3]],
                ['1234567890' => [2,['c' => 3]]],
            ],
            'recursive with array and scalar value' => [
                $standardDiffer,
                ['a' => 1, '1234567890' => ['0987654321' => 2,]],
                ['a' => 1, '1234567890' => ['0987654321' => ['d' => 3]]],
                ['1234567890' => ['0987654321' => [2,['d' => 3]]]],
            ],
            'matching flat, string keys' => [
                $stringKeyDiffer,
                ['a' => 1, 'b' => 2],
                ['a' => 1, 'b' => 2],
                [],
            ],
            'non-matching flat, same structure, string keys' => [
                $stringKeyDiffer,
                ['a' => 1, 'b' => 2],
                ['a' => 1, 'b' => 3],
                ['b' => ['expected' => 2, 'actual' => 3]],
            ],
        ];
    }
}

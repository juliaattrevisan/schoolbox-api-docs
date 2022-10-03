<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Rules\Lang\Test;

use Alaress\Rules\Lang\Map;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Alaress\Rules\Lang\Map
 */
class MapTest extends TestCase
{
    /**
     * @dataProvider getTestApplyData
     * @covers \Alaress\Rules\Lang\Map::apply
     *
     * @param array $map
     * @param array $inputValue
     * @param array $expected
     * @return void
     */
    public function testApply(array $map, array $inputValue, array $expected): void
    {
        $this->assertSame($expected, (new Map('', $map))->apply($inputValue));
    }

    /**
     * Data provider for `testApply`.
     *
     * @return iterable
     */
    public function getTestApplyData(): iterable
    {
        yield 'Empty map, empty input' => [
            [],
            [],
            [],
        ];

        yield 'Empty map, non-empty input' => [
            [],
            ['input'],
            [],
        ];

        yield 'Non-empty map, empty input' => [
            ['match' => 'output'],
            [],
            [],
        ];

        yield 'No matching mappings' => [
            ['match' => 'output'],
            ['input', 'input2'],
            [],
        ];

        yield 'One matching exact mapping' => [
            ['match' => '1', 'nomatch' => '2'],
            ['match', 'match2'],
            ['1'],
        ];

        yield 'Map leading wildcard: single' => [
            ['*atch' => '1', '*omatch' => '2'],
            ['match'],
            ['1'],
        ];

        yield 'Map leading wildcard: multiple' => [
            ['*atch' => '1', '*omatch' => '2'],
            ['match', 'nomatch'],
            ['1', '2'],
        ];

        yield 'Map trailing wildcard: single' => [
            ['mat*' => '1', 'nomat*' => '2'],
            ['match'],
            ['1'],
        ];

        yield 'Map trailing wildcard: multiple' => [
            ['mat*' => '1', 'nomat*' => '2'],
            ['match', 'nomatch'],
            ['1', '2'],
        ];

        yield 'Wildcard always matches' => [
            ['match' => '1', 'nomatch' => '2', '*' => '3'],
            ['blah'],
            ['3'],
        ];

        yield 'Case-insensitive' => [
            ['match' => '1', 'nomatch' => '2'],
            ['MatCh'],
            ['1'],
        ];

        yield 'Single-character wildcard' => [
            ['matc?' => '1', 'mat?' => '2'],
            ['match'],
            ['1'],
        ];

        yield 'Wildcard matches space-separated values' => [
            ['*zero*' => '1'],
            ['match zero one'],
            ['1'],
        ];

        yield 'Wildcard matches comma-separated values' => [
            ['*zero*' => '1'],
            ['match,zero,one'],
            ['1'],
        ];

        yield 'Character class' => [
            ['match[12]' => '1', 'match[34]' => '2'],
            ['match1'],
            ['1'],
        ];

        yield 'Character class: exact match' => [
            ['match[12]' => '1', 'match[34]' => '2'],
            ['match[12]'],
            ['1'],
        ];

        // Vagaries of fnmatch
        yield 'leading period in input does not match' => [
            ['test' => '1'],
            ['.test'],
            [],
        ];

        yield 'leading period in map does not match' => [
            ['.test' => '1'],
            ['test'],
            [],
        ];

        yield 'backslashes escape' => [
            ['test\\*' => '1'],
            ['test*'],
            ['1'],
        ];

        // The patterns which Jez wants to match on:
        // he wants to match a single value in a comma-separated list
        yield 'comma-separated list: multiple patterns, match at start of input' => [
            [
                'zero'     => '1',
                '*,zero'   => '1',
                'zero,*'   => '1',
                '*,zero,*' => '1',
            ],
            ['zero,match,one'],
            ['1'],
        ];
        yield 'comma-separated list: multiple patterns, match in middle of input' => [
            [
                'zero'     => '1',
                '*,zero'   => '1',
                'zero,*'   => '1',
                '*,zero,*' => '1',
            ],
            ['match,zero,one'],
            ['1'],
        ];
        yield 'comma-separated list: multiple patterns, match at end of input' => [
            [
                'zero'     => '1',
                '*,zero'   => '1',
                'zero,*'   => '1',
                '*,zero,*' => '1',
            ],
            ['match,one,zero'],
            ['1'],
        ];
        yield 'comma-separated list: multiple patterns, match equals input' => [
            [
                'zero'     => '1',
                '*,zero'   => '1',
                'zero,*'   => '1',
                '*,zero,*' => '1',
            ],
            ['zero'],
            ['1'],
        ];
        yield 'comma-separated list: multiple patterns, partial match at start of input' => [
            [
                'zero'     => '1',
                '*,zero'   => '1',
                'zero,*'   => '1',
                '*,zero,*' => '1',
            ],
            ['zeroo,match,one'],
            [],
        ];
        yield 'comma-separated list: multiple patterns, partial match in middle of input' => [
            [
                'zero'     => '1',
                '*,zero'   => '1',
                'zero,*'   => '1',
                '*,zero,*' => '1',
            ],
            ['match,zeroo,one'],
            [],
        ];
        yield 'comma-separated list: multiple patterns, partial match at end of input' => [
            [
                'zero'     => '1',
                '*,zero'   => '1',
                'zero,*'   => '1',
                '*,zero,*' => '1',
            ],
            ['match,one,zeroo'],
            [],
        ];
        yield 'comma-separated list: multiple patterns, partial match equals input' => [
            [
                'zero'     => '1',
                '*,zero'   => '1',
                'zero,*'   => '1',
                '*,zero,*' => '1',
            ],
            ['zeroo'],
            [],
        ];

        // Extended matches not supported by fnmatch
        yield 'comma-separated list: single pattern, match at start of input' => [
            ['{zero,*\,zero,zero\,*,*\,zero\,*}' => '1'],
            ['zero,match,one'],
            ['1'],
        ];
        yield 'comma-separated list: single pattern, match in middle of input' => [
            ['{zero,*\,zero,zero\,*,*\,zero\,*}' => '1'],
            ['match,zero,one'],
            ['1'],
        ];
        yield 'comma-separated list: single pattern, match at end of input' => [
            ['{zero,*\,zero,zero\,*,*\,zero\,*}' => '1'],
            ['match,one,zero'],
            ['1'],
        ];
        yield 'comma-separated list: single pattern, match equals input' => [
            ['{zero,*\,zero,zero\,*,*\,zero\,*}' => '1'],
            ['zero'],
            ['1'],
        ];
        yield 'comma-separated list: single pattern, partial match at start of input' => [
            ['{zero,*\,zero,zero\,*,*\,zero\,*}' => '1'],
            ['zeroo,match,one'],
            [],
        ];
        yield 'comma-separated list: single pattern, partial match in middle of input' => [
            ['{zero,*\,zero,zero\,*,*\,zero\,*}' => '1'],
            ['match,zeroo,one'],
            [],
        ];
        yield 'comma-separated list: single pattern, partial match at end of input' => [
            ['{zero,*\,zero,zero\,*,*\,zero\,*}' => '1'],
            ['match,one,zeroo'],
            [],
        ];
        yield 'comma-separated list: single pattern, partial match equals input' => [
            ['{zero,*\,zero,zero\,*,*\,zero\,*}' => '1'],
            ['zeroo'],
            [],
        ];
    }
}

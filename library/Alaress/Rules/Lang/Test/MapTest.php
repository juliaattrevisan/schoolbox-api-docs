<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Rules\Lang\Test;

use Alaress\Rules\Lang\Map;
use Alaress\Rules\Lang\NoMatchingMapException;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Alaress\Rules\Lang\Map::class)]
class MapTest extends TestCase
{
    /**
     *
     * @param array $map
     * @param array $inputValue
     * @param array $expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestApplyData')]
    public function testApply(array $map, array $inputValue, array $expected): void
    {
        $this->assertSame($expected, (new Map('', $map))->apply($inputValue));
    }

    /**
     * @param array $map
     * @param array $inputValue
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestApplyNoMatchData')]
    public function testApplyNoMatch(array $map, array $inputValue): void
    {
        $this->expectException(NoMatchingMapException::class);
        (new Map('', $map))->apply($inputValue);
    }

    /**
     * Data provider for `testApply`.
     *
     * @return iterable
     */
    public static function getTestApplyData(): iterable
    {
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

        yield 'Wildcard matches zero characters (leading)' => [
            ['*match' => '1'],
            ['match'],
            ['1'],
        ];

        yield 'Wildcard matches zero characters (trailing)' => [
            ['match*' => '1'],
            ['match'],
            ['1'],
        ];

        yield 'Wildcard matches zero characters (leading and trailing)' => [
            ['*match*' => '1'],
            ['match'],
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

        yield 'Character class negation' => [
            ['match[^12]' => '1', 'match[^34]' => '2'],
            ['match1'],
            ['2'],
        ];

        yield 'Character class negation: exact match' => [
            ['match[^12]' => '1', 'match[^34]' => '2'],
            ['match[^12]'],
            ['1'],
        ];

        yield 'Character class negation, ! format' => [
            ['match[!12]' => '1', 'match[!34]' => '2'],
            ['match1'],
            ['2'],
        ];

        yield 'Character class negation: exact match, ! format' => [
            ['match[!12]' => '1', 'match[!34]' => '2'],
            ['match[!12]'],
            ['1'],
        ];

        yield '[! escaped' => [
            ['match\\[!1]' => '1'],
            ['match\\[!1]'],
            ['1'],
        ];

        yield '[! double-escaped' => [
            ['match\\\\[!1]' => '1'],
            ['match\\2'],
            ['1'],
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

        // The (=foo) and (!foo) patterns are lookaheads:
        // I haven't found a use for them, but I can't ignore them
        yield 'positive lookahead: match' => [
            ['zero(=[0-9])*' => '1'],
            ['zero0'],
            ['1'],
        ];
        yield 'negative lookahead: match' => [
            ['zero(![a-z])*' => '1'],
            ['zero0'],
            ['1'],
        ];

        // Invalid patterns:
        // I'd like to raise a warning, but I cannot without having different
        // behavior when running UPS on the command line and dry run: the CLI
        // uses the daemon kernel, which throws errors on warning trigger.
        // (Of course this class shouldn't have any dependency on which kernel
        //  creates it: undoing said dependency is another problem though.)
        yield 'valid and invalid patterns: valid patterns still match' => [
            ['{zero' => '1', 'zero' => '2', 'zero0' => '3'],
            ['zero'],
            ['2'],
        ];
    }

    /**
     * Data provider for `testApplyNoMatch`.
     *
     * @return iterable
     */
    public static function getTestApplyNoMatchData(): iterable
    {
        yield 'Empty map, empty input' => [
            [],
            [],
        ];

        yield 'Empty map, non-empty input' => [
            [],
            ['input'],
        ];

        yield 'Non-empty map, empty input' => [
            ['match' => 'output'],
            [],
        ];

        yield 'No matching mappings' => [
            ['match' => 'output'],
            ['input', 'input2'],
        ];

        yield '[! escaped, no match' => [
            ['match\\[!1]' => '1'],
            ['match\\2'],
        ];

        yield '[! double-escaped, no match' => [
            ['match\\\\[!1]' => '1'],
            ['match\\1'],
        ];

        // Vagaries of fnmatch
        yield 'leading period in input does not match' => [
            ['test' => '1'],
            ['.test'],
        ];

        yield 'leading period in map does not match' => [
            ['.test' => '1'],
            ['test'],
        ];

        yield 'backslashes escape: not a match' => [
            ['test\\*' => '1'],
            ['test?'],
        ];

        yield 'comma-separated list: multiple patterns, partial match at start of input' => [
            [
                'zero'     => '1',
                '*,zero'   => '1',
                'zero,*'   => '1',
                '*,zero,*' => '1',
            ],
            ['zeroo,match,one'],
        ];

        yield 'comma-separated list: multiple patterns, partial match in middle of input' => [
            [
                'zero'     => '1',
                '*,zero'   => '1',
                'zero,*'   => '1',
                '*,zero,*' => '1',
            ],
            ['match,zeroo,one'],
        ];

        yield 'comma-separated list: multiple patterns, partial match at end of input' => [
            [
                'zero'     => '1',
                '*,zero'   => '1',
                'zero,*'   => '1',
                '*,zero,*' => '1',
            ],
            ['match,one,zeroo'],
        ];

        yield 'comma-separated list: multiple patterns, partial match equals input' => [
            [
                'zero'     => '1',
                '*,zero'   => '1',
                'zero,*'   => '1',
                '*,zero,*' => '1',
            ],
            ['zeroo'],
        ];

        yield 'comma-separated list: single pattern, partial match at start of input' => [
            ['{zero,*\,zero,zero\,*,*\,zero\,*}' => '1'],
            ['zeroo,match,one'],
        ];

        yield 'comma-separated list: single pattern, partial match in middle of input' => [
            ['{zero,*\,zero,zero\,*,*\,zero\,*}' => '1'],
            ['match,zeroo,one'],
        ];

        yield 'comma-separated list: single pattern, partial match at end of input' => [
            ['{zero,*\,zero,zero\,*,*\,zero\,*}' => '1'],
            ['match,one,zeroo'],
        ];

        yield 'comma-separated list: single pattern, partial match equals input' => [
            ['{zero,*\,zero,zero\,*,*\,zero\,*}' => '1'],
            ['zeroo'],
        ];

        yield 'positive lookahead: no match' => [
            ['zero(=[a-z])*' => '1'],
            ['zero0'],
        ];

        yield 'negative lookahead: no match' => [
            ['zero(![0-9])*' => '1'],
            ['zero0'],
        ];

        // Only invalid patterns: raise a no match
        yield 'invalid pattern' => [
            ['{zero' => '1'],
            ['zero'],
        ];
    }
}

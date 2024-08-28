<?php

/**
 * SanitiseScoresTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Feedback\Test;

use PHPUnit\Framework\TestCase;

class SanitiseScoresTest extends TestCase
{
    /**
     * @param mixed $mark
     * @param mixed $maxMark
     * @param int   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidMark')]
    public function testValidMark($mark, $maxMark, $expected)
    {
        /* Substitute this with a spec pattern or other conditional
         */
        $outcome = (new \Schoolbox\Learning\Learnosity\Feedback\SanitiseScores())->sanitise($mark, $maxMark);

        /* There are other assertions that you can try (ctrl-click to follow)
         */
        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidMark
     *
     * @return array
     */
    public static function providerValidMark(): array
    {
        /* Our db schema is DECIMAL(15,2) so everything always
         * has two decimal places
         */
        return [
            'Valid Mark 1' => [
                0,
                5,
                [
                    'score' => 0.00,
                    'max_score' => 5.00
                ]
            ],

            'Valid Mark 2' => [
                5,
                5.00,
                [
                    'score' => 5.00,
                    'max_score' => 5.00
                ]
            ],

            'Valid Mark 3' => [
                100.0000,
                100,
                [
                    'score' => 100.00,
                    'max_score' => 100.00
                ]
            ],

            'Valid Mark 4' => [
                12.3,
                15.5,
                [
                    'score' => 12.30,
                    'max_score' => 15.50
                ]
            ],

            'Valid Mark 5' => [
                '',
                10.00,
                [
                    'score' => null,
                    'max_score' => 10.00
                ]
            ],

            /* We should be trimming our stuff
             */
            'Trim 1' => [
                '      ',
                10.00,
                [
                    'score' => null,
                    'max_score' => 10.00
                ]
            ],

            'Trim 2' => [
                "  \r\n\t    ",
                10.00,
                [
                    'score' => null,
                    'max_score' => 10.00
                ]
            ],

            /* Special case
             */
            'Null Mark 8' => [
                null,
                100.00,
                [
                    'score' => null,
                    'max_score' => 100.00
                ]
            ],

            /* Extreme floatyness that exceeds our schema
             */
            'Mega Floats 1' => [
                50.12345678901234567890123456789,
                67.12345678901234567890123456789,
                [
                    'score' => 50.12,
                    'max_score' => 67.12
                ]
            ],

            'Mega Floats 2' => [
                50.155,
                67.255,
                [
                    'score' => 50.16,
                    'max_score' => 67.26
                ]
            ],

            'Mega Floats 3' => [
                50.1123,
                67.2123,
                [
                    'score' => 50.11,
                    'max_score' => 67.21
                ]
            ],

            /* Special cases, I suppose, for zero values
             */
            'Zero marks 1' => [
                0,
                0,
                [
                    'score' => 0.00,
                    'max_score' => 0.00
                ]
            ],

            'Zero marks 2' => [
                0.000,
                0,
                [
                    'score' => 0.00,
                    'max_score' => 0.00
                ]
            ],

            'Zero marks 3' => [
                0,
                0.0000,
                [
                    'score' => 0.00,
                    'max_score' => 0.00
                ]
            ],

            /* Negative numbers not allowed and will be rounded to 0
             * (you can't punish students)
             */
            'Negative mark 1' => [
                -1,
                10,
                [
                    'score' => 0.00,
                    'max_score' => 10.00
                ]
            ],

            'Negative mark 2' => [
                -2.12345,
                10,
                [
                    'score' => 0.00,
                    'max_score' => 10.00
                ]
            ],

            /* You can't have a negative max_mark; that gets rounded to 0 as well
             * not that there's any reason for this use case to exist except due
             * to user error somehow
             */
            'Negative max_mark 1' => [
                12,
                -100,
                [
                    'score' => 0.00,
                    'max_score' => 0.00
                ]
            ],

            'Negative max_mark 2' => [
                10,
                -90.1234,
                [
                    'score' => 0.00,
                    'max_score' => 0.00
                ]
            ],

            /* Oddball usages result in null/null
             */
            'Strings 1' => [
                'cat',
                'horse',
                [
                    'score' => null,
                    'max_score' => null
                ]
            ],

            'Strings 2' => [
                'cat',
                123,
                [
                    'score' => null,
                    'max_score' => null
                ]
            ],

            'Strings 3' => [
                123,
                'cat',
                [
                    'score' => null,
                    'max_score' => null
                ]
            ],

            'e' => [
                'e',
                'horse',
                [
                    'score' => null,
                    'max_score' => null
                ]
            ],

            /* Null max score
             */
            'Null max score 1' => [
                123,
                null,
                [
                    'score' => null,
                    'max_score' => null
                ]
            ],

            'Null max score 2' => [
                null,
                null,
                [
                    'score' => null,
                    'max_score' => null
                ]
            ],

            'Null max score 3' => [
                '12',
                null,
                [
                    'score' => null,
                    'max_score' => null
                ]
            ],

            'Null max score 4' => [
                '',
                null,
                [
                    'score' => null,
                    'max_score' => null
                ]
            ],
        ];
    }
}

<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Normalise\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Normalise\TupleStack;

/**
 * Class TupleStackTest
 *
 * @package Schoolbox\External\Normalise\Test
 */
class TupleStackTest extends TestCase
{

    /**
     *
     * @param array $stack
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validNormaliseValuesSplitMultiSeparator')]
    public function testNormaliseValuesSplitMultiSeparator(array $stack, array $expected): void
    {
        $this->assertEquals($expected, (new TupleStack())->normaliseValues($stack, ";, \r\n"));
    }


    /**
     * A valid stack for us to process
     *
     * @return array
     */
    public static function provider_validNormaliseValuesSplitMultiSeparator(): array
    {
        return [
            'Empty 6' => [
                [],
                []
            ],
            'Mixed Bag 6' => [
                [
                    "  \n   cat@schoolbox.com.au     \n\n\n rhino@alaress.com.au\r\nfox@alaress.com.au\rhorse@alaress.com.au",
                    "this\nis\na\nbig\rparagraph;\r\nof\nlines",
                    "\n",
                    false,
                    '1',
                    'cat@schoolbox.com.au;cat@schoolbox.com.au;',
                    null,
                    0,
                    'cat@schoolbox.com.au;horse@schoolbox.com.au',
                    'cat',
                    '      ',
                    'horse@schoolbox.com.au',
                    'cat@schoolbox.com.au',
                    [],
                    '     elephant@alaress.com.au     ; rhino@alaress.com.au;fox@alaress.com.au;;;;;',
                    "this\nis\na\nbig\rparagraph;\r\nof\nlines",
                    "\n",
                    false,
                    new \stdClass(),
                    '',
                    'ocelotte@schoolbox.com.au,lothric@schoolbox.com.au,',
                    'so there was this really great idea',
                    null,
                    0,
                    'yoel@schoolbox.com.au,',
                    'cat',
                    '      ',
                    'horse@schoolbox.com.au',
                    'cat@schoolbox.com.au',
                    ['maguffin', 'bleah', 23987],
                    '     elephant@alaress.com.au     , rhino@alaress.com.au,fox@alaress.com.au,,,,,',
                    "this\nis\na\nbig\rparagraph,\r\nof\nlines",
                    "\n"
                ],
                [
                    'cat@schoolbox.com.au',
                    'rhino@alaress.com.au',
                    'fox@alaress.com.au',
                    'horse@alaress.com.au',
                    'this',
                    'is',
                    'a',
                    'big',
                    'paragraph',
                    'of',
                    'lines',
                    '1',
                    '0',
                    'horse@schoolbox.com.au',
                    'cat',
                    'elephant@alaress.com.au',
                    'ocelotte@schoolbox.com.au',
                    'lothric@schoolbox.com.au',
                    'so',
                    'there',
                    'was',
                    'really',
                    'great',
                    'idea',
                    'yoel@schoolbox.com.au',
                ]
            ]
        ];
    }


    /**
     *
     * @param array $stack
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validNormaliseValuesSplitNewlines')]
    public function testNormaliseValuesSplitNewlines(array $stack, array $expected): void
    {
        $this->assertEquals($expected, (new TupleStack())->normaliseValues($stack, "\r\n"));
    }


    /**
     * A valid stack for us to process
     *
     * @return array
     */
    public static function provider_validNormaliseValuesSplitNewlines(): array
    {
        return [
            'Empty 5' => [
                [],
                []
            ],
            'Mixed Bag 5' => [
                [
                    "  \n   cat@schoolbox.com.au     \n\n\n rhino@alaress.com.au\r\nfox@alaress.com.au\rhorse@alaress.com.au",
                    "this\nis\na\nbig\rparagraph;\r\nof\nlines",
                    "\n"
                ],
                [
                    'cat@schoolbox.com.au',
                    'rhino@alaress.com.au',
                    'fox@alaress.com.au',
                    'horse@alaress.com.au',
                    'this',
                    'is',
                    'a',
                    'big',
                    'paragraph;',
                    'of',
                    'lines'
                ]
            ]
        ];
    }


    /**
     *
     * @param array $stack
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validNormaliseValuesSplitSemicolon')]
    public function testNormaliseValuesSplitSemicolon(array $stack, array $expected): void
    {
        $this->assertEquals($expected, (new TupleStack())->normaliseValues($stack, ';'));
    }


    /**
     * A valid stack for us to process
     *
     * @return array
     */
    public static function provider_validNormaliseValuesSplitSemicolon(): array
    {
        return [
            'Empty 4' => [
                [],
                []
            ],
            'Mixed Bag 4' => [
                [
                    false,
                    '',
                    'cat@schoolbox.com.au;cat@schoolbox.com.au;',
                    null,
                    0,
                    'cat@schoolbox.com.au;horse@schoolbox.com.au',
                    'cat',
                    '      ',
                    'horse@schoolbox.com.au',
                    'cat@schoolbox.com.au',
                    [],
                    '     elephant@alaress.com.au     ; rhino@alaress.com.au;fox@alaress.com.au;;;;;',
                    "this\nis\na\nbig\rparagraph;\r\nof\nlines",
                    "\n"
                ],
                [
                    'cat@schoolbox.com.au',
                    0,
                    'horse@schoolbox.com.au',
                    'cat',
                    'elephant@alaress.com.au',
                    'rhino@alaress.com.au',
                    'fox@alaress.com.au',
                    "this\nis\na\nbig\rparagraph",
                    "of\nlines",
                ]
            ]
        ];
    }


    /**
     *
     * @param array $stack
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validNormaliseValuesSplitComma')]
    public function testNormaliseValuesSplitComma(array $stack, array $expected): void
    {
        $this->assertEquals($expected, (new TupleStack())->normaliseValues($stack, ','));
    }


    /**
     * A valid stack for us to process
     *
     * @return array
     */
    public static function provider_validNormaliseValuesSplitComma(): array
    {
        return [
            'Empty 3' => [
                [],
                []
            ],
            'Mixed Bag 3' => [
                [
                    false,
                    '',
                    'cat@schoolbox.com.au,cat@schoolbox.com.au,',
                    null,
                    0,
                    'cat@schoolbox.com.au,horse@schoolbox.com.au',
                    'cat',
                    '      ',
                    'horse@schoolbox.com.au',
                    'cat@schoolbox.com.au',
                    [],
                    '     elephant@alaress.com.au     , rhino@alaress.com.au,fox@alaress.com.au,,,,,',
                    "this\nis\na\nbig\rparagraph,\r\nof\nlines",
                    "\n"
                ],
                [
                    'cat@schoolbox.com.au',
                    0,
                    'horse@schoolbox.com.au',
                    'cat',
                    'elephant@alaress.com.au',
                    'rhino@alaress.com.au',
                    'fox@alaress.com.au',
                    "this\nis\na\nbig\rparagraph",
                    "of\nlines",
                ]
            ]
        ];
    }


    /**
     *
     * @param array $stack
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validNormaliseValues')]
    public function testNormaliseValues(array $stack, array $expected): void
    {
        $this->assertEquals($expected, (new TupleStack())->normaliseValues($stack));
    }


    /**
     * A valid stack for us to process
     *
     * @return array
     */
    public static function provider_validNormaliseValues(): array
    {
        return [
            'Empty 2' => [
                [],
                []
            ],
            'Mixed Bag 2' => [
                [
                    false,
                    '',
                    'cat@schoolbox.com.au',
                    null,
                    0,
                    'cat@schoolbox.com.au',
                    'cat',
                    '      ',
                    'cat@schoolbox.com.au',
                    [],
                    "\n"
                ],
                [
                    'cat@schoolbox.com.au',
                    0,
                    'cat',
                ]
            ]
        ];
    }



    /**
     *
     * @param array $stack
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validNormaliseTuples')]
    public function testNormaliseTuples(array $stack, array $expected): void
    {
        $this->assertEquals($expected, (new TupleStack())->normaliseTuples($stack));
    }


    /**
     * A valid stack for us to process
     *
     * @return array
     */
    public static function provider_validNormaliseTuples(): array
    {
        return [
            'Empty 1' => [
                [],
                []
            ],

            'Key Only 1' => [
                [
                    'cat' => 'cat'
                ],
                [
                    'cat' => [
                        'cat'
                    ]
                ]
            ],

            'Scalars Only 1' => [
                [
                    'a',
                    'b',
                    'c',
                    'd'
                ],
                [
                    0 => [
                        'a'
                    ],
                    1 => [
                        'b'
                    ],
                    2 => [
                        'c'
                    ],
                    3 => [
                        'd'
                    ]
                ],
            ],

            'Scalars Only Mixed Bag 1' => [
                [
                    'a',
                    '',
                    false,
                    'd',
                    null
                ],
                [
                    0 => [
                        'a'
                    ],
                    1 => [],
                    2 => [],
                    3 => [
                        'd'
                    ],
                    4 => []
                ],
            ],

            'Mixed Bag 1' => [
                [
                    'id1' => [
                        'email1',
                        null,
                        'email1',
                        '',
                        '      ',
                        'email2',
                        'false',
                        false,
                        'email3'
                    ],
                    'id2' => [
                        'email3',
                        '      ',
                        'email2',
                        'false',
                        false,
                    ],
                    'id3' => [],
                    'id4' => null,
                    'id5' => 'cat',
                    'id6' => ''
                ],
                [
                    'id1' => [
                        'email1',
                        'email2',
                        'false',
                        'email3'
                    ],
                    'id2' => [
                        'email3',
                        'email2',
                        'false',
                    ],
                    'id3' => [],
                    'id4' => [],
                    'id5' => [
                        'cat'
                    ],
                    'id6' => []
                ]
            ],

            /* This causes a warning, but will silently proceed anyway, but you might
             * not get what you wanted.
             */
//            'Invalid Usage' => [
//                [
//                    'a',
//                    [
//                        'bb'
//                    ],
//                    [
//                        'what' => 'does this do',
//                        'when' => 'i keep providing more stuff'
//                    ],
//                    [
//                        'and then' => [
//                            'i keep providing more' => 'and more and more and more',
//                            'until' => 'it cannot eat a single bite'
//                        ]
//                    ],
//                ]
//            ]
        ];
    }
}

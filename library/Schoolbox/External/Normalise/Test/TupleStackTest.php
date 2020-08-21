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
     * @dataProvider provider_validNormaliseValuesSplitNewlines
     *
     * @param array $stack
     * @param array $expected
     */
    public function testNormaliseValuesSplitNewlines(array $stack, array $expected): void
    {
        $this->assertEquals($expected, (new TupleStack())->normaliseValues($stack, "\r\n"));
    }


    /**
     * A valid stack for us to process
     *
     * @return array
     */
    public function provider_validNormaliseValuesSplitNewlines(): array
    {
        return [
            'Empty' => [
                [],
                []
            ],
            'Mixed Bag' => [
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
     * @dataProvider provider_validNormaliseValuesSplitSemicolon
     *
     * @param array $stack
     * @param array $expected
     */
    public function testNormaliseValuesSplitSemicolon(array $stack, array $expected): void
    {
        $this->assertEquals($expected, (new TupleStack())->normaliseValues($stack, ';'));
    }


    /**
     * A valid stack for us to process
     *
     * @return array
     */
    public function provider_validNormaliseValuesSplitSemicolon(): array
    {
        return [
            'Empty' => [
                [],
                []
            ],
            'Mixed Bag' => [
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
     * @dataProvider provider_validNormaliseValuesSplitComma
     *
     * @param array $stack
     * @param array $expected
     */
    public function testNormaliseValuesSplitComma(array $stack, array $expected): void
    {
        $this->assertEquals($expected, (new TupleStack())->normaliseValues($stack, ','));
    }


    /**
     * A valid stack for us to process
     *
     * @return array
     */
    public function provider_validNormaliseValuesSplitComma(): array
    {
        return [
            'Empty' => [
                [],
                []
            ],
            'Mixed Bag' => [
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
     * @dataProvider provider_validNormaliseValues
     *
     * @param array $stack
     * @param array $expected
     */
    public function testNormaliseValues(array $stack, array $expected): void
    {
        $this->assertEquals($expected, (new TupleStack())->normaliseValues($stack));
    }


    /**
     * A valid stack for us to process
     *
     * @return array
     */
    public function provider_validNormaliseValues(): array
    {
        return [
            'Empty' => [
                [],
                []
            ],
            'Mixed Bag' => [
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
     * @dataProvider provider_validNormaliseTuples
     *
     * @param array $stack
     * @param array $expected
     */
    public function testNormaliseTuples(array $stack, array $expected): void
    {
        $this->assertEquals($expected, (new TupleStack())->normaliseTuples($stack));
    }


    /**
     * A valid stack for us to process
     *
     * @return array
     */
    public function provider_validNormaliseTuples(): array
    {
        return [
            'Empty' => [
                [],
                []
            ],

            'Key Only' => [
                [
                    'cat' => 'cat'
                ],
                [
                    'cat' => [
                        'cat'
                    ]
                ]
            ],

            'Scalars Only' => [
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

            'Scalars Only Mixed Bag' => [
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

            'Mixed Bag' => [
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

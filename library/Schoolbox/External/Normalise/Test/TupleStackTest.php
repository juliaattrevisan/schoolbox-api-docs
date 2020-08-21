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

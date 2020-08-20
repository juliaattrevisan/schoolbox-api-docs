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
     * @dataProvider provider_validNormaliser
     *
     * @param array $stack
     * @param bool  $expected
     */
    public function testNormaliser(array $stack, array $expected): void
    {
        $this->assertEquals($expected, (new TupleStack())->normalise($stack));
    }


    /**
     * A valid stack for us to process
     *
     * @return array
     */
    public function provider_validNormaliser(): array
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
        ];
    }
}

<?php

/**
 * DSNTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\Test;

use DomainException;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;

/**
 * Class DSNTest
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint\Test
 */
class DSNTest extends TestCase
{
    /**
     * Data provider for testValidDSNDecode
     *
     * @return array
     */
    public function providerValidDSNDecode(): array
    {
        return [
            /* Batch XML only
             */
            'Valid DSN 1' => [
                'batchxml:key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h',
                [
                    'modules' => [
                        'batchxml' => [
                            'key' => '920zfddd-x9u7-gg654-glhf-d9j25nf9h72h',
                        ],
                    ]
                ]
            ],

            /* Batch XML and Staff Photos
             */
            'Valid DSN 2' =>[
                'batchxml:key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h,staffphoto:key=zka7ght5-pp91-k28dy-zm28g-e1janv9777',
                [
                    'modules' => [
                        'batchxml' => [
                            'key' => '920zfddd-x9u7-gg654-glhf-d9j25nf9h72h',
                        ],
                        'staffphoto' => [
                            'key' => 'zka7ght5-pp91-k28dy-zm28g-e1janv9777',
                        ],
                    ]
                ]
            ],

            /* Batch XML and Student Photos
             */
            'Valid DSN 3' => [
                'batchxml:key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h,studentphoto:key=aadg4551-bnn7-a0098c-ja52-zpp0ooqp554a',
                [
                    'modules' => [
                        'batchxml' => [
                            'key' => '920zfddd-x9u7-gg654-glhf-d9j25nf9h72h',
                        ],
                        'studentphoto' => [
                            'key' => 'aadg4551-bnn7-a0098c-ja52-zpp0ooqp554a'
                        ]
                    ]
                ]
            ],

            /* Batch XML, Staff Photos, Student Photos
             */
            'Valid DSN 4' => [
                'batchxml:key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h,staffphoto:key=zka7ght5-pp91-k28dy-zm28g-e1janv9777,studentphoto:key=aadg4551-bnn7-a0098c-ja52-zpp0ooqp554a',
                [
                    'modules' => [
                        'batchxml' => [
                            'key' => '920zfddd-x9u7-gg654-glhf-d9j25nf9h72h',
                        ],
                        'staffphoto' => [
                            'key' => 'zka7ght5-pp91-k28dy-zm28g-e1janv9777',
                        ],
                        'studentphoto' => [
                            'key' => 'aadg4551-bnn7-a0098c-ja52-zpp0ooqp554a'
                        ]
                    ]
                ]
            ],
        ];
    }


    /**
     * Test valid DSN strings
     *
     * @dataProvider providerValidDSNDecode
     * @param string $dsn
     * @param array  $expected
     * @throws DomainException
     */
    public function testValidDSNDecode(string $dsn, array $expected): void
    {
        $codec = new DSN();

        $this->assertEquals($expected, $codec->decode($dsn));
    }


    /**
     * Data provider for testValidDSNDecodeEncode
     *
     * @return array
     */
    public function providerValidDSNDecodeEncode(): array
    {
        return [
            'Valid DSN Re-encode 1' => [
                'batchxml:key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h,staffphoto:key=zka7ght5-pp91-k28dy-zm28g-e1janv9777,studentphoto:key=aadg4551-bnn7-a0098c-ja52-zpp0ooqp554a',
                'batchxml:key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h,staffphoto:key=zka7ght5-pp91-k28dy-zm28g-e1janv9777,studentphoto:key=aadg4551-bnn7-a0098c-ja52-zpp0ooqp554a'
            ],
        ];
    }


    /**
     * Test valid DSN strings
     *
     * @dataProvider providerValidDSNDecodeEncode
     * @param string $dsn
     * @param string $expected
     * @throws DomainException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function testValidDSNDecodeEncode(string $dsn, string $expected): void
    {
        $codec = new DSN();
        $encoded = $dsn;

        $decoded = $codec->decode($encoded);
        $encoded = $codec->encode($decoded);
        $decoded = $codec->decode($encoded);
        $encoded = $codec->encode($decoded);

        /* End up back where you started after multiple mutations
         */
        $this->assertEquals($expected, $encoded);
    }


    /**
     * Data provider for testInvalidDSNDecode
     *
     * @return array
     */
    public function providerInvalidDSNDecode(): array
    {
        return [
            'Invalid DSN 1 (empty dsn)' => [
                '', []
            ],

            'Invalid DSN 2 (whitespace dsn)' => [
                '     ', []
            ],
        ];
    }


    /**
     * Test invalid DSN strings
     *
     * @dataProvider providerInvalidDSNDecode
     * @param string $dsn
     * @param array  $expected
     * @throws DomainException
     */
    public function testInvalidDSNDecode(string $dsn, array $expected): void
    {
        $this->expectException(DomainException::class);

        $codec = new DSN();
        $codec->decode($dsn);
    }
}

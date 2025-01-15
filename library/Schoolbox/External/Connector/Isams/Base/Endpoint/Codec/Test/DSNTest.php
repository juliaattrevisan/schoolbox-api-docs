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
    public static function providerValidDSNDecode(): array
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
                    ],
                    'options' => []
                ]
            ],

            'Valid DSN 1b' => [
                'batchxml:key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h?dayNumberOffset=-3',
                [
                    'modules' => [
                        'batchxml' => [
                            'key' => '920zfddd-x9u7-gg654-glhf-d9j25nf9h72h',
                        ],
                    ],
                    'options' => [
                        'dayNumberOffset' => -3
                    ]
                ]
            ],


            /* Batch XML and Staff Photos
             */
            'Valid DSN 2' => [
                'batchxml:key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h,staffphoto:key=zka7ght5-pp91-k28dy-zm28g-e1janv9777',
                [
                    'modules' => [
                        'batchxml' => [
                            'key' => '920zfddd-x9u7-gg654-glhf-d9j25nf9h72h',
                        ],
                        'staffphoto' => [
                            'key' => 'zka7ght5-pp91-k28dy-zm28g-e1janv9777',
                        ],
                    ],
                    'options' => []
                ]
            ],

            'Valid DSN 2b' => [
                'batchxml:key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h,staffphoto:key=zka7ght5-pp91-k28dy-zm28g-e1janv9777?dayNumberOffset=-3',
                [
                    'modules' => [
                        'batchxml' => [
                            'key' => '920zfddd-x9u7-gg654-glhf-d9j25nf9h72h',
                        ],
                        'staffphoto' => [
                            'key' => 'zka7ght5-pp91-k28dy-zm28g-e1janv9777',
                        ],
                    ],
                    'options' => [
                        'dayNumberOffset' => -3
                    ]
                ]
            ],

            /* Batch XML and Student Photos
             */
            'Valid DSN 3' => [
                'batchxml:key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h,studentphoto:key=aadg4551-bnn7-a0098c-ja52-zpp0ooqp554a?dayNumberOffset=-3',
                [
                    'modules' => [
                        'batchxml' => [
                            'key' => '920zfddd-x9u7-gg654-glhf-d9j25nf9h72h',
                        ],
                        'studentphoto' => [
                            'key' => 'aadg4551-bnn7-a0098c-ja52-zpp0ooqp554a'
                        ]
                    ],
                    'options' => [
                        'dayNumberOffset' => -3
                    ]
                ]
            ],

            /* Batch XML, Staff Photos, Student Photos
             */
            'Valid DSN 4' => [
                'batchxml:key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h,staffphoto:key=zka7ght5-pp91-k28dy-zm28g-e1janv9777,studentphoto:key=aadg4551-bnn7-a0098c-ja52-zpp0ooqp554a?dayNumberOffset=-3',
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
                    ],
                    'options' => [
                        'dayNumberOffset' => -3
                    ]
                ]
            ],

            /* BatchXML, Staff Photos, Student Photos, REST API
             */
            'Valid DSN 5' => [
                'batchxml:key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h,staffphoto:key=zka7ght5-pp91-k28dy-zm28g-e1janv9777,studentphoto:key=aadg4551-bnn7-a0098c-ja52-zpp0ooqp554a,restapi:host=https://www.schoolbox.com.au;clientid=123456;clientsecret=abcd-1F2G3H-444555?dayNumberOffset=-3',
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
                        ],
                        'restapi' => [
                            'host' => 'https://www.schoolbox.com.au',
                            'clientid' => '123456',
                            'clientsecret' => 'abcd-1F2G3H-444555'
                        ]
                    ],
                    'options' => [
                        'dayNumberOffset' => -3
                    ]
                ]
            ],
        ];
    }


    /**
     * Test valid DSN strings
     *
     * @param string $dsn
     * @param array  $expected
     * @throws DomainException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidDSNDecode')]
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
    public static function providerValidDSNDecodeEncode(): array
    {
        return [
            'Valid DSN Re-encode 1' => [
                'batchxml:key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h,staffphoto:key=zka7ght5-pp91-k28dy-zm28g-e1janv9777,studentphoto:key=aadg4551-bnn7-a0098c-ja52-zpp0ooqp554a',
                'batchxml:key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h,staffphoto:key=zka7ght5-pp91-k28dy-zm28g-e1janv9777,studentphoto:key=aadg4551-bnn7-a0098c-ja52-zpp0ooqp554a'
            ],

            /* With optional restapi configs
             */
            'Valid DSN Re-encode 2' => [
                'batchxml:key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h,staffphoto:key=zka7ght5-pp91-k28dy-zm28g-e1janv9777,studentphoto:key=aadg4551-bnn7-a0098c-ja52-zpp0ooqp554a,restapi:host=https://www.schoolbox.com.au;clientid=123456;clientsecret=abcd-1F2G3H-444555?dayNumberOffset=-3',
                'batchxml:key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h,staffphoto:key=zka7ght5-pp91-k28dy-zm28g-e1janv9777,studentphoto:key=aadg4551-bnn7-a0098c-ja52-zpp0ooqp554a,restapi:host=https://www.schoolbox.com.au;clientid=123456;clientsecret=abcd-1F2G3H-444555?dayNumberOffset=-3'
            ],
        ];
    }


    /**
     * Test valid DSN strings
     *
     * @param string $dsn
     * @param string $expected
     * @throws DomainException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidDSNDecodeEncode')]
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
    public static function providerInvalidDSNDecode(): array
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
     * @param string $dsn
     * @param array  $expected
     * @throws DomainException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidDSNDecode')]
    public function testInvalidDSNDecode(string $dsn, array $expected): void
    {
        $this->expectException(DomainException::class);

        $codec = new DSN();
        $codec->decode($dsn);
    }
}

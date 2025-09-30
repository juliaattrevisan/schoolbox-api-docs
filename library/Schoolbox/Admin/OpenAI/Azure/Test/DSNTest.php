<?php

/**
 * DSNCodecTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\Azure\Test;

use DomainException;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\OpenAI\Azure\DSN;

/**
 * Class DSNCodecTest
 *
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
            'Valid DSN 1 (original order)' => [
                'textchat:key=123;resourcename=gpt-3o;deploymentid=9999,lessonplan:key=978987;resourcename=gpt-3o;deploymentid=228',
                [
                    'modules' => [
                        'textchat' => [
                            'key' => '123',
                            'resourcename' => 'gpt-3o',
                            'deploymentid' => '9999'
                        ],

                        'lessonplan' => [
                            'key' => '978987',
                            'resourcename' => 'gpt-3o',
                            'deploymentid' => '228',
                        ],
                    ],
                ]
            ],

            'Valid DSN 2 (random order of module credentials)' => [
                'lessonplan:key=98765;resourcename=gpt-turbo;deploymentid=thisisatest,textchat:key=951;resourcename=somethingelse;deploymentid=cannumbersexist',
                [
                    'modules' => [
                        'lessonplan' => [
                            'key' => '98765',
                            'resourcename' => 'gpt-turbo',
                            'deploymentid' => 'thisisatest'
                        ],
                        'textchat' => [
                            'key' => '951',
                            'resourcename' => 'somethingelse',
                            'deploymentid' => 'cannumbersexist'
                        ],
                    ],
                ]
            ],

            'Valid DSN 3' => [
                'lessonplan:key=98765aa;resourcename=lsdkfjhaa;deploymentid=testisthis,textchat:key=99995464684;resourcename=andanotherthing;deploymentid=idsexisthere',
                [
                    'modules' => [
                        'lessonplan' => [
                            'key' => '98765aa',
                            'resourcename' => 'lsdkfjhaa',
                            'deploymentid' => 'testisthis'
                        ],
                        'textchat' => [
                            'key' => '99995464684',
                            'resourcename' => 'andanotherthing',
                            'deploymentid' => 'idsexisthere'
                        ],
                    ],
                ]
            ],
            'Valid DNS 4, only text creds exists' => [
                'textchat:key=000;resourcename=gpt-4o-mini-test;deploymentid=78946,lessonplan:key=;resourcename=;deploymentid=',
                [
                    'modules' => [
                        'textchat' => [
                            'key' => '000',
                            'resourcename' => 'gpt-4o-mini-test',
                            'deploymentid' => '78946'
                        ],
                        'lessonplan' => [
                            'key' => '',
                            'resourcename' => '',
                            'deploymentid' => '',
                        ],
                    ],
                ]
            ],
            'Valid DNS 5, all empty' => [
                'textchat:key=;resourcename=;deploymentid=,lessonplan:key=;resourcename=;deploymentid=',
                [
                    'modules' => [
                        'textchat' => [
                            'key' => '',
                            'resourcename' => '',
                            'deploymentid' => ''
                        ],
                        'lessonplan' => [
                            'key' => '',
                            'resourcename' => '',
                            'deploymentid' => '',
                        ],
                    ],
                ]
            ]
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
    public function testValidDSNDecode(string $dsn, $expected): void
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
                'textchat:key=000;resourcename=gpt-4o-mini-test;deploymentid=78946,lessonplan:key=001;resourcename=gpt-40mini-test2;deploymentid=1123456',
                'textchat:key=000;resourcename=gpt-4o-mini-test;deploymentid=78946,lessonplan:key=001;resourcename=gpt-40mini-test2;deploymentid=1123456'
            ],

            'Valid DSN Re-encode 2 (modules out of order)' => [
                'lessonplan:key=123456789dsfafds;resourcename=gpt-5;deploymentid=somethingelse,textchat:key=keysgiveaccesstothings;resourcename=arandomresource;deploymentid=azuredeploymentidorsomthinglikeit',
                'lessonplan:key=123456789dsfafds;resourcename=gpt-5;deploymentid=somethingelse,textchat:key=keysgiveaccesstothings;resourcename=arandomresource;deploymentid=azuredeploymentidorsomthinglikeit'
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
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidDSNDecodeEncode')]
    public function testValidDSNDecodeEncode(string $dsn, $expected): void
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

            'Invalid DSN 3 (bad dsn)' => [
                'cat', []
            ],

            'Invalid DSN 4 (missing modules)' => [
                'lessonplan:', []
            ],

            'Invalid DSN 5 (missing modules)' => [
                'textchat:', []
            ],

            'Invalid DSN 6 (missing modules)' => [
                'assessment:', []
            ],

            'Invalid DSN 7 (no tuples)' => [
                'lessonplan:,textchat:,assessment:', []
            ],

            'Invalid DSN 8 (bad separation of module credentials)' => [
                'lessonplan:key=123456789dsfafdsresourcename=gpt-5deploymentid=somethingelsetextchat:key=keysgiveaccesstothingsresourcename=arandomresourcedeploymentid=azuredeploymentidorsomthinglikeit',
                []
            ],

            'Invalid DSN 9 (malformed tuples)' => [
                'lessonplan:key=resourcename=deploymentid=:textchat=key=resourcename=deploymentid=',
                []
            ],

            'Invalid DSN 12 (case sensitive)' => [
                'lessonplan:Key=123456789dsfafds;resourceName=gpt-5;deploymentID=somethingelse,textchat:key=keysgiveaccesstothings;ResourceName=arandomresource;deploymentid=azuredeploymentidorsomthinglikeit',
                []
            ],

            'Invalid DSN 12 (mixed case)' => [
                'lessonplan:KEY=123456789dsfafds;resourceName=gpt-5;deploymentID=somethingelse,textchat:key=keysgiveaccesstothings;RESOURCENAME=arandomresource;deploymentid=azuredeploymentidorsomthinglikeit',
                []
            ],
            'Invalid DSN 14 (bad tuple ordering)' => [
                'textchat:resourcename=gpt-4o-mini-test;key=000deploymentid=78946,lessonplan:deploymentid=1123456;resourcename=gpt-40mini-test2;key=001',
                []
            ],
        ];
    }


    /**
     * Test invalid DSN strings
     *
     * @param string $dsn
     * @param array  $expected
     * @throws DomainException
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidDSNDecode')]
    public function testInvalidDSNDecode(string $dsn, $expected): void
    {
        $this->expectException(DomainException::class);

        /* Invalid DSNs are the exact opposite of the one and only Valid DSN format which
         * requires you to provide the three Tass Product Modules and three credential tuples
         * per product. To exhaustively list all the invalid formats would require a lot more
         * effort than they're worth.
         */
        $codec = new DSN();
        $codec->decode($dsn);
    }
}

<?php
/**
 * DSNCodecTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Test;

use DomainException;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\DSNCodec;

/**
 * Class DSNCodecTest
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Test
 */
class DSNCodecTest extends TestCase
{

    /**
     * Data provider for testValidDSNDecode
     *
     * @return array
     */
    public function provider_validDSNDecode(): array
    {
        return [
            'Valid DSN 1 (original order)' => [
                'studentdetails:companycode=123;appcode=ADweh189;token=923nADjl35h0sd7725==,identitymanagement:companycode=456;appcode=xcjFh28;token=ckbj78fj1kdhg=,lmsintegration:companycode=789;appcode=Qvmdbn2764;token=CVWSEFklrsjdswwwidfnesfd',
                [
                    'studentdetails'=> [
                        'companycode' => 123,
                        'appcode' => 'ADweh189',
                        'token' => '923nADjl35h0sd7725=='
                    ],

                    'identitymanagement' => [
                        'companycode' => 456,
                        'appcode' => 'xcjFh28',
                        'token' => 'ckbj78fj1kdhg=',
                    ],

                    'lmsintegration' => [
                        'companycode' => 789,
                        'appcode' => 'Qvmdbn2764',
                        'token' => 'CVWSEFklrsjdswwwidfnesfd'
                    ]
                ]
            ],

            'Valid DSN 2 (random order of module credentials)' => [
                'lmsintegration:companycode=789;appcode=Qvmdbn2764;token=CVWSEFklrsjdswwwidfnesfd,identitymanagement:companycode=456;appcode=xcjFh28;token=ckbj78fj1kdhg=,studentdetails:companycode=123;appcode=ADweh189;token=923nADjl35h0sd7725==',
                [
                    'lmsintegration' => [
                        'companycode' => 789,
                        'appcode' => 'Qvmdbn2764',
                        'token' => 'CVWSEFklrsjdswwwidfnesfd'
                    ],
                    'identitymanagement' => [
                        'companycode' => 456,
                        'appcode' => 'xcjFh28',
                        'token' => 'ckbj78fj1kdhg=',
                    ],
                    'studentdetails'=> [
                        'companycode' => 123,
                        'appcode' => 'ADweh189',
                        'token' => '923nADjl35h0sd7725=='
                    ],
                ]
            ],
        ];
    }


    /**
     * Test valid DSN strings
     *
     * @dataProvider provider_validDSNDecode
     * @param string $dsn
     * @param array  $expected
     * @throws DomainException
     */
    public function testValidDSNDecode(string $dsn, $expected): void
    {
        $codec = new DSNCodec();

        $this->assertEquals($expected, $codec->decode($dsn));
    }


    /**
     * Data provider for testValidDSNDecodeEncode
     *
     * @return array
     */
    public function provider_validDSNDecodeEncode(): array
    {
        return [
            'Valid DSN Re-encode 1' => [
                'studentdetails:companycode=123;appcode=ADweh189;token=923nADjl35h0sd7725==,identitymanagement:companycode=456;appcode=xcjFh28;token=ckbj78fj1kdhg=,lmsintegration:companycode=789;appcode=Qvmdbn2764;token=CVWSEFklrsjdswwwidfnesfd',
                'studentdetails:companycode=123;appcode=ADweh189;token=923nADjl35h0sd7725==,identitymanagement:companycode=456;appcode=xcjFh28;token=ckbj78fj1kdhg=,lmsintegration:companycode=789;appcode=Qvmdbn2764;token=CVWSEFklrsjdswwwidfnesfd'
            ],
        ];
    }


    /**
     * Test valid DSN strings
     *
     * @dataProvider provider_validDSNDecodeEncode
     * @param string $dsn
     * @param array  $expected
     * @throws DomainException
     */
    public function testValidDSNDecodeEncode(string $dsn, $expected): void
    {
        $codec = new DSNCodec();
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
    public function provider_invalidDSNDecode(): array
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
                'studentdetails:', []
            ],

            'Invalid DSN 5 (missing modules)' => [
                'identitymanagement:', []
            ],

            'Invalid DSN 6 (missing modules)' => [
                'lmsintegration:', []
            ],

            'Invalid DSN 7 (no tuples)' => [
                'studentdetails:,identitymanagement:,lmsintegration:', []
            ],

            'Invalid DSN 8 (bad separation of module credentials)' => [
                'studentdetails:companycode=123appcode=ADweh189token=923nADjl35h0sd7725==identitymanagement:companycode=456appcode=xcjFh28token=ckbj78fj1kdhg=lmsintegration:companycode=789appcode=Qvmdbn2764token=CVWSEFklrsjdswwwidfnesfd',
                []
            ],

            'Invalid DSN 9 (malformed tuples)' => [
                'studentdetails:companycode=appcode=identitymanagement:companycode=appcode=lmsintegration:companycode=appcode=',
                []
            ],

            'Invalid DSN 10 (half-tuples)' => [
                'studentdetails:companycode=;appcode=;token=,identitymanagement:companycode=;appcode=;token=,lmsintegration:companycode=;appcode=;token=',
                []
            ],

            'Invalid DSN 11 (missing module credentials)' => [
                'studentdetails:companycode=123;appcode=ADweh189;token=923nADjl35h0sd7725==,identitymanagement:companycode=;appcode=;token=,lmsintegration:companycode=;appcode=;token=',
                []
            ],

            'Invalid DSN 11 (case sensitive)' => [
                'studentdetails:Appcode=ADweh189;Companycode=123;Token=923nADjl35h0sd7725==,identitymanagement:appcode=werklj2i9jsdf;companycode=234xv;;token=ol2j5ljsdf89,lmsintegration:token=923nADjl35h0sd7725==;companycode=werwer;appcode=bdefgdf',
                []
            ],

            'Invalid DSN 11 (bad tuple ordering)' => [
                'studentdetails:appcode=ADweh189;companycode=123;token=923nADjl35h0sd7725==,identitymanagement:appcode=werklj2i9jsdf;companycode=234xv;;token=ol2j5ljsdf89,lmsintegration:token=923nADjl35h0sd7725==;companycode=werwer;appcode=bdefgdf',
                []
            ],

            /* Add all possible permutations; there's probably more than 300 you could add
             */
        ];
    }


    /**
     * Test invalid DSN strings
     *
     * @dataProvider provider_invalidDSNDecode
     * @param string $dsn
     * @param array  $expected
     * @throws DomainException
     *
     * @expectedException DomainException
     */
    public function testInvalidDSNDecode(string $dsn, $expected): void
    {

        /* Invalid DSNs are the exact opposite of the one and only Valid DSN format which
         * requires you to provide the three Tass Product Modules and three credential tuples
         * per product. To exhaustively list all the invalid formats would require a lot more
         * effort than they're worth.
         */
        $codec = new DSNCodec();
        $codec->decode($dsn);
    }
}



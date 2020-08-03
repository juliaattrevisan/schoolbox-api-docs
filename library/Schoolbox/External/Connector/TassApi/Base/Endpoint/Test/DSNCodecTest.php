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
     * Data provider for testValidDSNDecode
     *
     * @return array
     */
    public function provider_validDSNDecode(): array
    {
        return [
            'Valid DSN 1' => [
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
        ];
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
}



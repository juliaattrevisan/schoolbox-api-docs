<?php

/**
 * DSNCodecTest
 *
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\Codec\Test;

use DomainException;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Civica\Base\Endpoint\Codec\DSN;

/**
 * Class DSNCodecTest
 *
 * @package Schoolbox\External\Connector\Civica\Base\Endpoint\Test
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
            'Valid DSN 1' => [
                'username=alaress.schoolbox.jurisdiction;password=P@s5w0rd!;schoolid=3216549153546811002;idp=https://google.com.au',
                [
                    'username' => 'alaress.schoolbox.jurisdiction',
                    'password' => 'P@s5w0rd!',
                    'schoolid' => '3216549153546811002',
                    'idp' => 'https://google.com.au'
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
                'username=alaress.schoolbox.jurisdiction;password=P@s5w0rd!;schoolid=3216549153546811002;idp=https://google.com.au',
                'username=alaress.schoolbox.jurisdiction;password=P@s5w0rd!;schoolid=3216549153546811002;idp=https://google.com.au',
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

            'Invalid DSN 3 (bad dsn)' => [
                'cat', []
            ],

            'Invalid DSN 4 (malformed tuples)' => [
                'idp=', []
            ],

            'Invalid DSN 5 (malformed tuples)' => [
                'idp=;username=;password=', []
            ],

            'Invalid DSN 6 (malformed tuples)' => [
                'password=;', []
            ],

            'Invalid DSN 7 (no tuples)' => [
                ';', []
            ],

            'Invalid DSN 8 (malformed tuples)' => [
                'idp=;username=;password=;schoolid=;', []
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
    public function testInvalidDSNDecode(string $dsn, $expected): void
    {
        $this->expectException(DomainException::class);

        $codec = new DSN();
        $codec->decode($dsn);
    }
}

<?php

/**
 * DSNCodecTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Wonde\Base\Endpoint\Codec\Test;

use DomainException;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Wonde\Base\Endpoint\Codec\DSN;

/**
 * Class DSNCodecTest
 *
 * @package Schoolbox\External\Connector\Wonde\Base\Endpoint\Test
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
                'school=A1234567890;token=67b9c3c0b1e92882e3e146f01de5054',
                [
                    'school' => 'A1234567890',
                    'token' => '67b9c3c0b1e92882e3e146f01de5054'
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
                'school=A1234567890;token=67b9c3c0b1e92882e3e146f01de5054',
                'school=A1234567890;token=67b9c3c0b1e92882e3e146f01de5054'
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
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
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
                'school=', []
            ],

            'Invalid DSN 5 (malformed tuples)' => [
                'school=;token=', []
            ],

            'Invalid DSN 6 (malformed tuples)' => [
                'school=;', []
            ],

            'Invalid DSN 7 (no tuples)' => [
                ';', []
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

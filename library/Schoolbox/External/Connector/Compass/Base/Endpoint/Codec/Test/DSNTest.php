<?php

/**
 * DSNCodecTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Codec\Test;

use DomainException;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Compass\Base\Endpoint\Codec\DSN;

/**
 * Class DSNCodecTest
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint\Test
 */
class DSNTest extends TestCase
{

    /**
     * Data provider for testValidDSNDecode
     *
     * @return array
     */
    public function provider_validDSNDecode(): array
    {
        return [
            'Valid DSN 1' => [
                'key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h;timetableowner=12345',
                [
                    'key' => '920zfddd-x9u7-gg654-glhf-d9j25nf9h72h',
                    'timetableowner' => '12345'
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
        $codec = new DSN();

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
                'key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h;timetableowner=12345',
                'key=920zfddd-x9u7-gg654-glhf-d9j25nf9h72h;timetableowner=12345'
            ],
        ];
    }


    /**
     * Test valid DSN strings
     *
     * @dataProvider provider_validDSNDecodeEncode
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

            'Invalid DSN 4 (malformed tuples)' => [
                'key=', []
            ],

            'Invalid DSN 5 (malformed tuples)' => [
                'key=;timetableowner=', []
            ],

            'Invalid DSN 6 (malformed tuples)' => [
                'key=;', []
            ],

            'Invalid DSN 7 (no tuples)' => [
                ';', []
            ],
        ];
    }


    /**
     * Test invalid DSN strings
     *
     * @dataProvider provider_invalidDSNDecode
     * @param string $dsn
     * @param array  $expected
     * @throws DomainException
     */
    public function testInvalidDSNDecode(string $dsn, $expected): void
    {
        $this->expectException(DomainException::class);

        $codec = new DSN();
        $codec->decode($dsn);
    }
}

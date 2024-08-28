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
    public static function providerValidDSNDecode(): array
    {
        return [
            'Valid DSN 1' => [
                '920zfddd-x9u7-gg654-glhf-d9j25nf9h72h',
                [
                    'key' => '920zfddd-x9u7-gg654-glhf-d9j25nf9h72h',
                ]
            ],
        ];
    }


    /**
     * Test valid DSN strings
     *
     * @param string $dsn
     * @param array  $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
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
                '920zfddd-x9u7-gg654-glhf-d9j25nf9h72h',
                '920zfddd-x9u7-gg654-glhf-d9j25nf9h72h'
            ],
        ];
    }


    /**
     * Test valid DSN strings
     *
     * @param string $dsn
     * @param string $expected
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
}

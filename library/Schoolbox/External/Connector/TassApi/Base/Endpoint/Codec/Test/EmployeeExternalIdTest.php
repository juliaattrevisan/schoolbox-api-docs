<?php

/**
 * CodeCodecTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\EmployeeExternalId;

/**
 * Class EmployeeExternalIdTest
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Test
 */
class EmployeeExternalIdTest extends TestCase
{

    /**
     * Data provider for testValidEncodeDecode
     *
     * @return array
     */
    public static function provider_validEncodeDecode(): array
    {
        return [
            'Re-encoded Employee 1' => [
                'E1000145',
                'E1000145',
            ],

            'Re-encoded Employee 2' => [
                'EA10293',
                'EA10293',
            ],

            'Re-encoded Employee 3' => [
                'D99001',
                'D99001',
            ],
        ];
    }


    /**
     * Test valid prefixed employee code re-encode
     *
     * @param string $input
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validEncodeDecode')]
    public function testValidEncodeDecode(string $input, string $expected): void
    {
        $codec = new EmployeeExternalId();

        $this->assertEquals($expected, $codec->decode($codec->encode($input)));
    }


    /**
     * Data provider for testValidEncode
     *
     * @return array
     */
    public function provider_validEncode(): array
    {
        return [
            'Encoded Employee 1' => [
                '1000145',
                'E1000145',
            ],

            'Encoded Employee 2' => [
                'EA10293',
                'EEA10293',
            ],

            'Encoded Employee 3' => [
                'D99001',
                'ED99001',
            ],
        ];
    }


    /**
     * Test valid prefixed employee code decode
     *
     * @param string $input
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validDecode')]
    public function testValidEncode(string $input, string $expected): void
    {
        $codec = new EmployeeExternalId();

        $this->assertEquals($expected, $codec->decode($input));
    }

    /**
     * Data provider for testValidDecode
     *
     * @return array
     */
    public static function provider_validDecode(): array
    {
        return [
            'Decoded Employee 1' => [
                'E1000145',
                '1000145'
            ],

            'Decoded Employee 2' => [
                'EEA10293',
                'EA10293'
            ],

            'Decoded Employee 3' => [
                'ED99001',
                'D99001'
            ],
        ];
    }


    /**
     * Test valid prefixed employee code decode
     *
     * @param string $input
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validDecode')]
    public function testValidDecode(string $input, string $expected): void
    {
        $codec = new EmployeeExternalId();

        $this->assertEquals($expected, $codec->decode($input));
    }
}

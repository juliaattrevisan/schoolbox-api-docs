<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\Codec\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Civica\Base\Endpoint\Codec\Metadata;

/**
 * Class MetadataTest
 *
 * @package Schoolbox\External\Connector\Civica\Base\Endpoint\Test
 */
class MetadataTest extends TestCase
{
    /**
     * Data provider for testValidMetadataDecode
     *
     * @return array
     */
    public function providerValidMetadataDecode(): array
    {
        return [
            'Valid Metadata 1' => [
                '{"links":{"attendance":"https:\/\/www.schoolbox.com.au\/thing\/attendance\/111111222222?attendance=true&type=1&session=777777666666"}}',
                [
                    'links' => [
                        'attendance' => 'https://www.schoolbox.com.au/thing/attendance/111111222222?attendance=true&type=1&session=777777666666'
                    ]
                ]
            ],
        ];
    }


    /**
     * Test valid metadata strings
     *
     * @dataProvider providerValidMetadataDecode
     * @param string $metadata
     * @param array  $expected
     * @throws \JsonException
     */
    public function testValidMetadataDecode(string $metadata, $expected): void
    {
        $codec = new Metadata();

        $this->assertEquals($expected, $codec->decode($metadata));
    }


    /**
     * Data provider for testValidMetadataDecodeEncode
     *
     * @return array
     */
    public function providerValidMetadataDecodeEncode(): array
    {
        return [
            'Valid Metadata Re-encode 1' => [
                '{"links":{"attendance":"https:\/\/www.schoolbox.com.au\/thing\/attendance\/111111222222?attendance=true&type=1&session=777777666666"}}',
                '{"links":{"attendance":"https:\/\/www.schoolbox.com.au\/thing\/attendance\/111111222222?attendance=true&type=1&session=777777666666"}}',
            ],
        ];
    }


    /**
     * Test valid metadata strings
     *
     * @dataProvider providerValidMetadataDecodeEncode
     * @param string $metadata
     * @param string $expected
     * @throws \JsonException
     */
    public function testValidMetadataDecodeEncode(string $metadata, string $expected): void
    {
        $codec = new Metadata();
        $encoded = $metadata;

        $decoded = $codec->decode($encoded);
        $encoded = $codec->encode($decoded);
        $decoded = $codec->decode($encoded);
        $encoded = $codec->encode($decoded);

        /* End up back where you started after multiple mutations
         */
        $this->assertEquals($expected, $encoded);
    }
}

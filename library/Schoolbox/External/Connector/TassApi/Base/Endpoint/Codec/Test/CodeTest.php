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
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\Code as CodeCodec;

/**
 * Class DSNCodecTest
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Test
 */
class CodeTest extends TestCase
{

    /**
     * Data provider for testValidCodeEncode
     *
     * @return array
     */
    public static function provider_validCodeEncode(): array
    {
        /* This code format doesn't enforce Tass to provide all three pieces
         * In fact, Tass might provide none, and we'd still have to proceed
         *
         * This codec ensures that whatever class codes we generate in the
         * business layer can (and should) remain consistent, when dealing
         * with Tass integrations. Until there's a use case for a decode()
         * this is currently one-way
         */
        return [
            'Valid Code 1 (all pieces)' => [
                '11G', '009B1', '3',
                '11G-009B1-3'
            ],

            'Valid Code 2 (no Year Group)' => [
                '', '009B1', '3',
                '-009B1-3'
            ],

            'Valid Code 3 (no Subject Code)' => [
                '11G', '', '3',
                '11G--3'
            ],

            'Valid Code 4 (no Class)' => [
                '11G', '009B1', '',
                '11G-009B1-'
            ],

            'Valid Code 5 (all empty)' => [
                '', '', '',
                '--'
            ],
        ];
    }


    /**
     * Test valid folder code strings
     *
     * @param string $yearGroup
     * @param string $subjectCode
     * @param string $class
     * @param string  $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validCodeEncode')]
    public function testValidCodeEncode(string $yearGroup, string $subjectCode, string $class, string $expected): void
    {
        $codec = new CodeCodec();

        $this->assertEquals($expected, $codec->encode($yearGroup, $subjectCode, $class));
    }
}

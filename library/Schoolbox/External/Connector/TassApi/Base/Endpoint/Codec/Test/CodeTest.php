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
    public static function providerValidCodeEncode(): array
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

            /* Added support for hyphenated year numbers
             */
            'Hyphenated 1' => [
                '-1', 'S117', '1',
                '-1-S117-1'
            ],

            'Hyphenated 2' => [
                '-2', 'S117', '2',
                '-2-S117-2'
            ],

            'Hyphenated 3' => [
                '-PP', 'S117', '3',
                '-PP-S117-3'
            ],

            'Hyphenated 4' => [
                '', 'S117', '4',
                '-S117-4'
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
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidCodeEncode')]
    public function testValidCodeEncode(string $yearGroup, string $subjectCode, string $class, string $expected): void
    {
        $codec = new CodeCodec();

        $this->assertEquals($expected, $codec->encode($yearGroup, $subjectCode, $class));
    }


    /**
     * Data provider for testValidCodeDecode
     *
     * @return array
     */
    public static function providerValidCodeDecode(): array
    {
        return [
            'Valid Code 1 (all pieces)' => [
                '11G-009B1-3',
                ['11G', '009B1', '3']
            ],

            'Valid Code 2 (no Year Group)' => [
                '-009B1-3',
                ['', '009B1', '3']
            ],

            'Valid Code 3 (no Subject Code)' => [
                '11G--3',
                ['11G', '', '3']
            ],

            'Valid Code 4 (no Class)' => [
                '11G-009B1-',
                ['11G', '009B1', '']
            ],

            'Valid Code 5 (all empty)' => [
                '--',
                ['', '', '']
            ],

            'Hyphenated 1' => [
                '-1-S117-1',
                ['-1', 'S117', '1']
            ],

            'Hyphenated 2' => [
                '-2-S117-2',
                ['-2', 'S117', '2']
            ],

            'Hyphenated 3' => [
                '-PP-S117-3',
                ['-PP', 'S117', '3']
            ],

            'Hyphenated 4' => [
                '-S117-4',
                ['', 'S117', '4']
            ],

            /* Particularly exotic usage: not sure if legit or not
             */
            'Hyphenated Oddball 1' => [
                '--S117-4',
                ['-', 'S117', '4']
            ],
        ];
    }


    /**
     * Test valid folder code strings
     *
     * @param string $code
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidCodeDecode')]
    public function testValidCodeDecode(string $code, array $expected): void
    {
        $codec = new CodeCodec();

        $this->assertEquals($expected, $codec->decode($code));
    }
}

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

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\Guid as GuidCodec;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Class GuidTest
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Test
 */
class GuidTest extends TestCase
{
    /**
     * Data provider for testValidGuidEncode
     *
     * @return array
     */
    public static function providerValidGuidEncode(): array
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
            /* An event starting at 2022-01-01 01:02:03
             * on ttid 55, Day 2, PeriodCode 7, class code 11G-009B1-3
             */
            'Valid Guid 1 (all pieces)' => [
                '2022-01-01 01:02:03', '55', '2', '7', '11G-009B1-3',
                '2022-01-01 01:02:03|55|2|7|11G-009B1-3'
            ],

            'Valid Guid 2 (no Year Group)' => [
                '2022-01-01 01:02:03', '55', '2', '7', '-009B1-3',
                '2022-01-01 01:02:03|55|2|7|-009B1-3'
            ],

            'Valid Guid 3 (no Subject Code)' => [
                '2022-01-01 01:02:03', '55', '2', '7', '11G--3',
                '2022-01-01 01:02:03|55|2|7|11G--3'
            ],

            'Valid Guid 4 (no Class)' => [
                '2022-01-01 01:02:03', '55', '2', '7', '11G-009B1-',
                '2022-01-01 01:02:03|55|2|7|11G-009B1-'
            ],
        ];
    }


    /**
     * Test valid guid pieces
     *
     * @param string $startAt
     * @param string $ttid
     * @param string $day
     * @param string $periodCode
     * @param string $classCode
     * @param string $expected
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidGuidEncode')]
    public function testValidGuidEncode(string $startAt, string $ttid, string $day, string $periodCode, string $classCode, string $expected): void
    {
        $codec = new GuidCodec();

        $this->assertEquals($expected, $codec->encode($startAt, $ttid, $day, $periodCode, $classCode));
    }

    /**
     * Data provider for testValidGuidEncodeDecode
     *
     * @return array
     */
    public static function providerValidGuidEncodeDecode(): array
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
            /* An event starting at 2022-01-01 01:02:03
             * on ttid 55, Day 2, PeriodCode 7, class code 11G-009B1-3
             */
            'Valid Guid 1 Reencode (all pieces)' => [
                ['2022-01-01 01:02:03', '55', '2', '7', '11G-009B1-3'],
                ['2022-01-01 01:02:03', '55', '2', '7', '11G-009B1-3']
            ],

            'Valid Guid 2 Reencode (no Year Group)' => [
                ['2022-01-01 01:02:03', '55', '2', '7', '-009B1-3'],
                ['2022-01-01 01:02:03', '55', '2', '7', '-009B1-3']
            ],

            'Valid Guid 3 Reencode (no Subject Code)' => [
                ['2022-01-01 01:02:03', '55', '2', '7', '11G--3'],
                ['2022-01-01 01:02:03', '55', '2', '7', '11G--3']
            ],

            'Valid Guid 4 Reencode (no Class)' => [
                ['2022-01-01 01:02:03', '55', '2', '7', '11G-009B1-'],
                ['2022-01-01 01:02:03', '55', '2', '7', '11G-009B1-']
            ],
        ];
    }


    /**
     * Test valid guid pieces when reencoded
     *
     * @param array  $input
     * @param array $expected
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidGuidEncodeDecode')]
    public function testValidGuidEncodeDecode(array $input, array $expected): void
    {
        $codec = new GuidCodec();

        /* Get the individual pieces
         */
        [$startAt, $ttid, $day, $periodCode, $classCode] = $input;

        /* Encode it
         */
        $encoded = $codec->encode($startAt, $ttid, $day, $periodCode, $classCode);

        /* Get the individual pieces back out
         */
        [$startAt, $ttid, $day, $periodCode, $classCode] = $codec->decode($encoded);

        /* Push it back in again
         */
        $encoded = $codec->encode($startAt, $ttid, $day, $periodCode, $classCode);

        /* Get it back out
         */
        $output = $codec->decode($encoded);

        /* Should be the same as how you started
         */
        $this->assertEquals($expected, $output);
    }
}

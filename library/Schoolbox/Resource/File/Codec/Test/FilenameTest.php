<?php

/**
 * FilenameTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Resource\File\Codec\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Resource\File\Codec\Filename;

/**
 * Validation test
 */
class FilenameTest extends TestCase
{
    /**
     * @param $filename
     * @param $basename
     * @param $maxlength
     * @param int $expected
     */
    #[DataProvider('providerValidFilename')]
    public function testValidFilename($filename, $basename, $maxlength, $expected)
    {
        $outcome = (new Filename())->generate($filename, $basename, $maxlength);

        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidFilename
     *
     * @return array
     */
    public static function providerValidFilename(): array
    {
        return [
            'Valid 1' => [
                'cat',
                'csv',
                10,
                'cat.csv'
            ],

            'Valid 2' => [
                'cat',
                'csv',
                1,
                'c.csv'
            ],

            'Valid 3' => [
                'abcdefghijklmnopqrstuvwxyz',
                'alphabet',
                26,
                'abcdefghijklmnopqrstuvwxyz.alphabet'
            ],

            'Valid 4' => [
                'ab c d e f ghijkl mnopqrs tuvwx yz',
                'alphabet',
                26,
                'ab c d e f ghijkl mnopqrs.alphabet'
            ],

            'Valid 5' => [
                "\n\t Massive Oblong              ",
                'txt',
                100,
                'Massive Oblong.txt'
            ],

            /* Multibyte chars
             */
            'Valid 6' => [
                "감사합니다",
                'sc2',
                2,
                "감사.sc2"
            ],

            'Valid 7' => [
                "추가적인 파일런을 건설해야 합니다",
                'lol',
                10,
                "추가적인 파일런을.lol"
            ],

            'Valid 8' => [
                "추가적인 파일런을 건설해야 합니다",
                'lol',
                100,
                "추가적인 파일런을 건설해야 합니다.lol"
            ]
        ];
    }


    /**
     * These inputs should induce an InvalidArgumentException as the filename generated is invalid for most
     * human usages (zero length basename, zero length extension, etc)
     *
     * @dataProvider getTestErrorRaisedData
     */
    #[DataProvider('getTestErrorRaisedData')]
    public function testErrorRaised($filename, $basename, $maxlength): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Filename())->generate($filename, $basename, $maxlength);
    }


    /**
     * Data provider for `testErrorRaised`.
     *
     * @return array
     */
    public static function getTestErrorRaisedData(): array
    {
        return [
            /* null basename and extensions
             */
            'Invalid 1' => [
                null,
                null,
                250,
            ],

            'Invalid 2' => [
                'cat',
                null,
                250,
            ],

            'Invalid 3' => [
                null,
                'csv',
                250,
            ],

            /* Whitespace char basename and extensions
             */
            'Invalid 4' => [
                '                           ',
                'txt',
                250,
            ],

            'Invalid 5' => [
                'cat',
                '                            ',
                250,
            ],

            'Invalid 6' => [
                '            ',
                '                            ',
                250,
            ],

            /* Control char basename and extensions
             */
            'Invalid 7' => [
                "\n\n\n\n\n\n",
                "csv",
                250,
            ],

            'Invalid 8' => [
                "\t\t\t\t\t\t\t\t\t\t\t\t",
                "csv",
                250,
            ],

            'Invalid 9' => [
                'cat',
                "\n\n\n\n\n\n",
                250,
            ],

            'Invalid 10' => [
                'cat',
                "\t\t\t\t\t\t\t\t\t\t\t\t",
                250,
            ],

            /* Zero maxlength
             */
            'Invalid 11' => [
                'cat',
                "csv",
                0,
            ],

            /* Negative maxlengths
             */
            'Invalid 12' => [
                'cat',
                "csv",
                -1,
            ],

            'Invalid 13' => [
                'cat',
                "csv",
                -11,
            ],

            /* Too big
             */
            'Invalid 15' => [
                'cat',
                "csv",
                10000000000000,
            ],
        ];
    }
}

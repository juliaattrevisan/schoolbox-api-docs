<?php

/**
 * FolderCodecTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace External\Canvas\Importer\Codec\Test;

use External\Canvas\Importer\Codec\FolderCode;
use PHPUnit\Framework\TestCase;

class FolderCodecTest extends TestCase
{
    /**
     * @param int         $currentYear
     * @param string      $canvasCourseCode
     * @param string|null $expected
     * @throws \DebugBar\DebugBarException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerFolderCode')]
    public function testValidEFolderCode(int $currentYear, string $canvasCourseCode, ?string $expected)
    {
        $this->assertEquals($expected, (new FolderCode())->decode($currentYear, $canvasCourseCode));
    }


    /**
     * Data provider for testValidEFolderCode
     *
     * @return array
     */
    public static function providerFolderCode(): array
    {
        return [

            /* Typical usages
             */
            'Valid 1' => [
                2016,
                '09CIVPEDWCO2016',
                '09CIVPEDWCO'
            ],
            'Valid 2' => [
                2015,
                '09CIVPEDAHA2015',
                '09CIVPEDAHA'
            ],
            'Valid 3' => [
                2021,
                '08TECTIM_X2021',
                '08TECTIM_X'
            ],
            'Valid 4' => [
                2021,
                'LINDFIELD_YEAR_6_2021',
                'LINDFIELD_YEAR_6_'
            ],

            /* This ended in a matching $currentYear suffix, though its meaning is questionable
             * and you'd have to ask the school what this might have meant (a code that circumstantially
             * matches the $currentYear suffix)
             */
            'Valid 5' => [
                2021,
                '202120212021',
                '20212021'
            ],

            /* These don't end with a year suffix corresponding to $currentYear
             * and will be disregarded
             */
            'null 1' => [
                2021,
                'BFG__10000',
                null
            ],
            'null 2' => [
                2021,
                '09PED2018_BP',
                null
            ],
            'null 3' => [
                2021,
                '09PEDPAS2018_BP',
                null
            ],
            'null 4' => [
                2021,
                '11DRATIB2022_AE',
                null
            ],
            'null 5' => [
                2021,
                '11DRADRALS2021_AE',
                null
            ],
            'null 6' => [
                2021,
                'sandpit_DVernon',
                null
            ],
            'null 7' => [
                2021,
                'Y11Spanishinitio',
                null
            ],
            'null 8' => [
                2021,
                'YR9B3ENGMG',
                null
            ],
            'null 9' => [
                2021,
                'Lindfield Year 6',
                null
            ],
            'null 10' => [
                2021,
                '',
                null
            ],
        ];
    }
}

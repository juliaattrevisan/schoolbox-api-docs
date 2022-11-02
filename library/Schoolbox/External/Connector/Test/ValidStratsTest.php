<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Test;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\ValidStrats;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Class ValidStratsTest
 *
 * Find meaningful values from a csv set.
 *
 * @package Schoolbox\External\Connector\Test
 */
class ValidStratsTest extends TestCase
{
    /**
     * @dataProvider providerValidWatchlistValue
     * @param string $cliArgs
     * @param array  $expected
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testValidStrats(string $cliArgs, array $expected)
    {
        $this->assertEquals($expected, (new ValidStrats())->filter($cliArgs));
    }


    /**
     * Data provider for testValidWatchlistValue
     *
     * @return array
     */
    public function providerValidWatchlistValue(): array
    {
        return [
            /* Empty strings, meaningless data
             */
            'No Strats 1' => [
                (string)null,
                []
            ],

            'No Strats 2' => [
                '',
                []
            ],

            'No Strats 3' => [
                ',,,,,,,,,,,,,,,,,,,',
                []
            ],

            'No Strats 4' => [
                '  , , , , , , ,, , ,,,,     ,, , ,,       ,           ',
                []
            ],

            'No Strats 5' => [
                '                          ',
                []
            ],

            /* Bad data (but this is a great song)
             */
            'Bad Data 1' => [
                'Flying,in,a,dream,,Stars,by,the,pocketful',
                []
            ],

            'Bad Data 2' => [
                ",,,,,,,,,,,,This,scene,feels,like,what,I,once,saw,on,a,screen,
I,searched,‘aurora,borealis,green’",
                []
            ],

            'Bad Data 3' => [
                's,t,a,f,f',
                []
            ],

            /* Mixed Case, but valid
             */
            'Mixed 1' => [
                'sTafF',
                ['staff']
            ],

            'Mixed 2' => [
                'STAFF',
                ['staff']
            ],

            'Mixed 3' => [
                'STAFF,stuDenT,    fAMILIes',
                ['staff', 'student', 'families']
            ],

            'Mixed 4' => [
                '    STaFF,stuDenT,    fAMILIes, tIMeTABLe, timetableX',
                ['staff', 'student', 'families', 'timetable']
            ],

            /* The chaos of the world
             */
            'Ultimate 1' => [
                '  , ,12% ,^0 ,STAFF ,(** , ,s,STaFF,stuDenT,    fAMILIes, tIMeTABLe, timetableXt,  23*&%* ,,,,,,,,,,,,This,scene,feels,like,what,I,once,saw,on,a,screen,
I,searched,‘aurora,borealis,green’,,,,,,,,,,,,,This,scene,feels,like,what,I,once,saw,on,a,screen,
I,searched,‘aurora,borealis,green’, STaFF,stuDenT, ---_+=-=   fAMILIesa,f,f, , ,sTafF,,,     ,, ,STAFF,stuDenT,    fAMILIes ,,       ,           ',
                ['staff', 'student', 'families', 'timetable']
            ]
        ];
    }
}
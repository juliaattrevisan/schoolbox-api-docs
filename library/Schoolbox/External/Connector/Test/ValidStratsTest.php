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
        $this->assertEquals($expected, (new ValidStrats($cliArgs))->filter($cliArgs));
    }


    /**
     * Data provider for testValidStrats
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


    /**
     * @dataProvider providerStratEnabled
     * @param string $method
     * @param string $cliArgs
     * @param bool   $expected
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testStratEnabled(string $method, string $cliArgs, bool $expected)
    {
        $validStrats = (new ValidStrats($cliArgs));

        $this->assertEquals($expected, $validStrats->$method());
    }


    /**
     * Data provider for testStratEnabled
     *
     * @return array
     */
    public function providerStratEnabled(): array
    {
        return [

            /* Staff check
             */
            'Staff enabled special case' => [
                'isStaffStratEnabled',
                '',
                true,
            ],

            'Staff enabled 1' => [
                'isStaffStratEnabled',
                'staff',
                true
            ],

            'Staff enabled 2' => [
                'isStaffStratEnabled',
                'families,student,staff,timetable',
                true
            ],

            'Staff not enabled 1' => [
                'isStaffStratEnabled',
                'families,student,timetable',
                false,
            ],

            /* Student check
             */
            'Student enabled special case' => [
                'isStudentStratEnabled',
                '',
                true,
            ],

            'Student enabled 1' => [
                'isStudentStratEnabled',
                'student',
                true
            ],

            'Student enabled 2' => [
                'isStudentStratEnabled',
                'families,student,staff,timetable',
                true
            ],

            'Student not enabled 1' => [
                'isStudentStratEnabled',
                'families,staff,timetable',
                false,
            ],

            /* Families check
             */
            'Families enabled special case' => [
                'isFamiliesStratEnabled',
                '',
                true,
            ],

            'Families enabled 1' => [
                'isFamiliesStratEnabled',
                'families',
                true
            ],

            'Families enabled 2' => [
                'isFamiliesStratEnabled',
                'families,student,staff,timetable',
                true
            ],

            'Families not enabled 1' => [
                'isFamiliesStratEnabled',
                'student,staff,timetable',
                false,
            ],

            /* Timetable check
             */
            'Timetable enabled special case' => [
                'isTimetableStratEnabled',
                '',
                true,
            ],

            'Timetable enabled 1' => [
                'isTimetableStratEnabled',
                'timetable',
                true
            ],

            'Timetable enabled 2' => [
                'isTimetableStratEnabled',
                'families,student,staff,timetable',
                true
            ],

            'Timetable not enabled 1' => [
                'isTimetableStratEnabled',
                'student,staff,families',
                false,
            ],

            /* Combo check
             */
            'Combo 1.1' => [
                'isStaffStratEnabled',
                'families,student,staff',
                true
            ],

            'Combo 1.2' => [
                'isStudentStratEnabled',
                'families,student,staff',
                true
            ],

            'Combo 1.3' => [
                'isFamiliesStratEnabled',
                'families,student,staff',
                true
            ],

            'Combo 1.4' => [
                'isTimetableStratEnabled',
                'families,student,staff',
                false
            ]
        ];
    }
}

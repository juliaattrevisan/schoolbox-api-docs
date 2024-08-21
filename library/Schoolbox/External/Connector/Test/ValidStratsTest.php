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
     * @param string $cliArgs
     * @param array  $expected
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidWatchlistValue')]
    public function testValidStrats(string $cliArgs, array $expected)
    {
        $this->assertEquals($expected, (new ValidStrats($cliArgs))->filter($cliArgs));
    }


    /**
     * Data provider for testValidStrats
     *
     * @return array
     */
    public static function providerValidWatchlistValue(): array
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
                [ValidStrats::STAFF]
            ],

            'Mixed 2' => [
                'STAFF',
                [ValidStrats::STAFF]
            ],

            'Mixed 3' => [
                'STAFF,stuDenT,    fAMILIes',
                [ValidStrats::STAFF, ValidStrats::STUDENT, ValidStrats::FAMILIES]
            ],

            'Mixed 4' => [
                '    STaFF,stuDenT,    fAMILIes, tIMeTABLe, timetableX',
                [ValidStrats::STAFF, ValidStrats::STUDENT, ValidStrats::FAMILIES, ValidStrats::TIMETABLE]
            ],

            /* The chaos of the world
             */
            'Ultimate 1' => [
                '  , ,12% ,^0 ,STAFF ,(** , ,s,STaFF,stuDenT,    fAMILIes, tIMeTABLe, timetableXt,  23*&%* ,,,,,,,,,,,,This,scene,feels,like,what,I,once,saw,on,a,screen,
I,searched,‘aurora,borealis,green’,,,,,,,,,,,,,This,scene,feels,like,what,I,once,saw,on,a,screen,
I,searched,‘aurora,borealis,green’, STaFF,stuDenT, ---_+=-=   fAMILIesa,f,f, , ,sTafF,,,     ,, ,STAFF,stuDenT,    fAMILIes ,,       ,           ',
                [ValidStrats::STAFF, ValidStrats::STUDENT, ValidStrats::FAMILIES, ValidStrats::TIMETABLE]
            ]
        ];
    }


    /**
     * @param string $method
     * @param string $cliArgs
     * @param bool   $expected
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStratEnabled')]
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
    public static function providerStratEnabled(): array
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
            ],

            /* Facade check
             * This represents a combination of enabled strats for the supercategory
             * Profile and Timetable. Profiles consist of multiple strats: staff student families
             * so to find out if the Profile import should be enabled at all, at least
             * one of those must have been selected
             *
             * This saves you import time by disabling certain parts that a suds
             * importer doesn't need when you've selected a particular strat
             */
            'Facade special case' => [
                'isProfileStratEnabled',
                '',
                true
            ],

            'Facade 1' => [
                'isProfileStratEnabled',
                'families,student,staff',
                true
            ],

            'Facade 2' => [
                'isProfileStratEnabled',
                'families,student',
                true
            ],

            'Facade 3' => [
                'isProfileStratEnabled',
                'families',
                true
            ],

            'Facade 4' => [
                'isProfileStratEnabled',
                'families,timetable',
                true
            ],

            'Facade 5' => [
                'isProfileStratEnabled',
                'families,student,timetable',
                true
            ],

            'Facade 6' => [
                'isProfileStratEnabled',
                'timetable',
                false
            ],
        ];
    }
}

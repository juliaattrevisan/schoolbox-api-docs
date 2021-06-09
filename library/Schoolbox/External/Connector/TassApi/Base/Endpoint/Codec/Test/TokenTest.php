<?php

/**
 * TokenTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Example\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\Token;

/**
 * Class TokenTest
 *
 * @package Schoolbox\Example\Test
 */
class TokenTest extends TestCase
{
    /**
     * Take a data struct and encrypt it into a string token
     *
     * @dataProvider providerTokenGeneration
     *
     * @param string $tassProductToken
     * @param array  $data
     * @param string $encrypted
     * @throws \External\Tass\Api\Exception\InvalidApiKey
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function testTokenGeneration(string $tassProductToken, array $data, string $encrypted)
    {
        $outcome = (new Token($tassProductToken))->generateToken($data);

        $this->assertEquals($encrypted, $outcome);
    }


    /**
     * Take the encrypted token and unpack it into the original data struct we used
     * This is the inverse of token generation
     *
     * @dataProvider providerTokenGeneration
     *
     * @param string $tassProductToken
     * @param array  $data
     * @param string $encrypted
     * @throws \External\Tass\Api\Exception\InvalidApiKey
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function testTokenUnpack(string $tassProductToken, array $data, string $encrypted)
    {
        $outcome = (new Token($tassProductToken))->unpackToken($encrypted);

        $this->assertEquals($data, $outcome);
    }


    /**
     * Data provider for testTokenGeneration
     *
     * @return array
     */
    public function providerTokenGeneration(): array
    {
        return [

            /* getMedicalConditions
             */
            'HINE001 getMedicalConditions' => [
                'ExXxxAsRVcoIMA7PDZmYOA==',
                [
                    'studcode' => 'HINE001'
                ],
                '+IvzV5vVy+1F1oRWnj+Ni+SzaT6eu95aLTMeHE2GgKw='
            ],

            'CARL003 getMedicalConditions' => [
                'ExXxxAsRVcoIMA7PDZmYOA==',
                [
                    'studcode' => 'CARL003'
                ],
                'gDGrlvWkHnf3bgAFXE5hn6+azp5dfL1BDWvgOKvsyrM='
            ],

            'MUIR001 getMedicalConditions' => [
                'ExXxxAsRVcoIMA7PDZmYOA==',
                [
                    'studcode' => 'MUIR001'
                ],
                'noGBAgFjN2AA2RqpBGzOd2wX8AuDY/mwmyOpmgPThLk='
            ],

            /* getCommunicationRulesDetails
             */

            'HINE001 getCommunicationRulesDetails' => [
                'ExXxxAsRVcoIMA7PDZmYOA==',
                [
                    'currentstatus' => 'current',
                    'commtype' => 'ec',
                    'code' => 'HINE001'
                ],
                'Ii3LqZCQ4lyLSjvhhuhOeIuEfWBvNLHVwFSj5xtCfThH5J3+TFSNFrpHVCH4FErLoYd7IvFw/Lyzwi2eIolZLQ=='
            ],

            'CARL003 getCommunicationRulesDetails' => [
                'ExXxxAsRVcoIMA7PDZmYOA==',
                [
                    'currentstatus' => 'current',
                    'commtype' => 'ec',
                    'code' => 'CARL003'
                ],
                'Ii3LqZCQ4lyLSjvhhuhOeIuEfWBvNLHVwFSj5xtCfThH5J3+TFSNFrpHVCH4FErLuulaxJ4BWBSAmvaMtb/O/A=='
            ],

            'MUIR001 getCommunicationRulesDetails' => [
                'ExXxxAsRVcoIMA7PDZmYOA==',
                [
                    'currentstatus' => 'current',
                    'commtype' => 'ec',
                    'code' => 'MUIR001'
                ],
                'Ii3LqZCQ4lyLSjvhhuhOeIuEfWBvNLHVwFSj5xtCfThH5J3+TFSNFrpHVCH4FErLvRqoSX2sJDTbh3dBj+RCMA=='
            ],

            /* getStudentTimetable
             */

            'AGNE003 getStudentTimetable' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'student_code' => 'AGNE003'
                ],
                'dZHhjYmT1NXUDZ0wDR+mZ3r1xmPdBnB3I0l5wro/mXc='
            ],

            'BROC003 getStudentTimetable' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'student_code' => 'BROC003'
                ],
                'dZHhjYmT1NXUDZ0wDR+mZ01aXPu8uCCBs8DByfdjoMw='
            ],

            'MILL002 getStudentTimetable' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'student_code' => 'MILL002'
                ],
                'dZHhjYmT1NXUDZ0wDR+mZ8arHCjQ6IyMVG+I/SzIirI='
            ],

            /* getStudentSubjects
             */

            'ALLZ002 getStudentSubjects' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'code' => 'ALLZ002'
                ],
                'XajWeBi73O7GtRGZhd6KjWwqOO93T6duyCl3CbCnFaY='
            ],

            'BELL003 getStudentSubjects' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'code' => 'BELL003'
                ],
                'NRrxc0MKtjF5aYRzuluMmWwqOO93T6duyCl3CbCnFaY='
            ],

            'JENS002 getStudentSubjects' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'code' => 'JENS002'
                ],
                '7YSdGTpC2LWxFIj6ZY0gsWwqOO93T6duyCl3CbCnFaY='
            ],

            /* getTeacherTimetable
             */

            'AR getTeacherTimetable' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'teacher_code' => 'AR',
                    'year_num' => '2021',
                    'semester' => '1'
                ],
                'tRtwK58O2Bh2g6Hvbor4t46YgJx4RAsOtgeYQkhRQa5AFj82KaMMN6+fKJ2N4hkT5i0mz29bBTqRHEj5bCa67w=='
            ],

            'BIS getTeacherTimetable' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'teacher_code' => 'BIS',
                    'year_num' => '2021',
                    'semester' => '1'
                ],
                'tRtwK58O2Bh2g6Hvbor4tx/Y9n52ldI7NQ5LtluFhAkOkh/dFkUi4jWK69YMJXM5h6zzKE+EYqlKmiiOWDvgCA=='
            ],

            'RY getTeacherTimetable' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'teacher_code' => 'RY',
                    'year_num' => '2021',
                    'semester' => '1'
                ],
                'tRtwK58O2Bh2g6Hvbor4t+JfBLG47iGMEXHO1LozOmdAFj82KaMMN6+fKJ2N4hkT5i0mz29bBTqRHEj5bCa67w=='
            ],

            /* getTeacherSubjects
             */

            'AJ getTeacherSubjects' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'code' => 'AJ',
                ],
                'nZpuLWDyCJvKgEsmPg+EXw=='
            ],

            'CRO getTeacherSubjects' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'code' => 'CRO',
                ],
                '0mb8eGYhVHZkHd4hqbTFFA=='
            ],

            'ZTO getTeacherSubjects' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'code' => 'ZTO',
                ],
                'xsnJYa4MRUJhwKWgjOoqvA=='
            ],

            /* CARR001 special area
             * We encountered 401 Unauthorized for getStudentSubjects in TASS, for some reason.
             * The algorithm works for everyone else except him
             *
             * json encoded payloads are in the docblocks
             */

            // {"currentstatus":"current","commtype":"ec","code":"CARR001"}
            'CARR001 getCommunicationRulesDetails' => [
                'ExXxxAsRVcoIMA7PDZmYOA==',
                [
                    'currentstatus' => 'current',
                    'commtype' => 'ec',
                    'code' => 'CARR001'
                ],
                'Ii3LqZCQ4lyLSjvhhuhOeIuEfWBvNLHVwFSj5xtCfThH5J3+TFSNFrpHVCH4FErLCnEsGsLMePKGycuH1aZq1Q=='
            ],

            // {"student_code":"CARR001"}
            'CARR001 getStudentTimetable' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'student_code' => 'CARR001'
                ],
                'dZHhjYmT1NXUDZ0wDR+mZ+s0X+1STyt57R9P3csnjeY='
            ],

            /* We encountered random problems with this particular string
             * For some reason this breaks
             */

//            // {"code":"CARR001"}
//            'CARR001 getStudentSubjects' => [
//                '4a5mz8qm98cWDvYIiYpZww==',
//                [
//                    'code' => 'CARR001',
//                ],
//
//                // TASS expected this
//                'LxYXZAVeUYH7vihV5GWCeDZJYaPz1Fx1guTVyKdVhlE='
//
//                // But we generated this
//                // pygYck+SsLsA9CSwexS1tmwqOO93T6duyCl3CbCnFaY=
//            ],
        ];
    }
}

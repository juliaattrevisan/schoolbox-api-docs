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

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\Test;

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
     *
     * @param string $tassProductToken
     * @param array  $data
     * @param string $encrypted
     * @throws \External\Tass\Api\Exception\InvalidApiKey
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTokenGeneration')]
    public function testTokenGeneration(string $tassProductToken, array $data, string $encrypted)
    {
        $outcome = (new Token($tassProductToken))->generateToken($data, true);

        $this->assertEquals($encrypted, $outcome);
    }


    /**
     * Take the encrypted token and unpack it into the original data struct we used
     * This is the inverse of token generation
     *
     *
     * @param string $tassProductToken
     * @param array  $data
     * @param string $encrypted
     * @throws \External\Tass\Api\Exception\InvalidApiKey
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTokenGeneration')]
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
    public static function providerTokenGeneration(): array
    {
        return [

            /* getMedicalConditions
             */
            'HINE001 getMedicalConditions' => [
                'ExXxxAsRVcoIMA7PDZmYOA==',
                [
                    'studcode' => 'HINE001'
                ],
                '5KApwYuhaS6yzBUMnIR7PxhZ49V8BccPlifkUNQ3RrU='
            ],

            'CARL003 getMedicalConditions' => [
                'ExXxxAsRVcoIMA7PDZmYOA==',
                [
                    'studcode' => 'CARL003'
                ],
                '5KApwYuhaS6yzBUMnIR7P3StFftXHT6UjHhhpR9dbHA='
            ],

            'MUIR001 getMedicalConditions' => [
                'ExXxxAsRVcoIMA7PDZmYOA==',
                [
                    'studcode' => 'MUIR001'
                ],
                '5KApwYuhaS6yzBUMnIR7P1RVNZ4IYFhYJlnG2NGfn5U='
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
                '4aco62aV3eLUGHiO7IsbVDBGhU7OtVM5g+x9LQbcSuDzvJg1VnqpzX0TmND58DUHPE4bnL3DziRjlWROoUiZ5/bb3RsmzlPyj3B5kt+CPDw='
            ],

            'CARL003 getCommunicationRulesDetails' => [
                'ExXxxAsRVcoIMA7PDZmYOA==',
                [
                    'currentstatus' => 'current',
                    'commtype' => 'ec',
                    'code' => 'CARL003'
                ],
                '4aco62aV3eLUGHiO7IsbVDBGhU7OtVM5g+x9LQbcSuDzvJg1VnqpzX0TmND58DUHPE4bnL3DziRjlWROoUiZ5xJXKU3YrLamZYJFbwlI0zw='
            ],

            'MUIR001 getCommunicationRulesDetails' => [
                'ExXxxAsRVcoIMA7PDZmYOA==',
                [
                    'currentstatus' => 'current',
                    'commtype' => 'ec',
                    'code' => 'MUIR001'
                ],
                '4aco62aV3eLUGHiO7IsbVDBGhU7OtVM5g+x9LQbcSuDzvJg1VnqpzX0TmND58DUHPE4bnL3DziRjlWROoUiZ50pMEcuS9X+oKhNd3e8Wcpg='
            ],

            /* getStudentTimetable
             */

            'AGNE003 getStudentTimetable' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'student_code' => 'AGNE003'
                ],
                'wjNWPE9+PjIPxaWPVDsUhhjXImcNsqI4insJnIId/C/0FWnuEiPBvLOYtpfczmUd'
            ],

            'BROC003 getStudentTimetable' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'student_code' => 'BROC003'
                ],
                'wjNWPE9+PjIPxaWPVDsUhjSNFkSNs4fipDYTDQ+r8vT0FWnuEiPBvLOYtpfczmUd'
            ],

            'MILL002 getStudentTimetable' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'student_code' => 'MILL002'
                ],
                'wjNWPE9+PjIPxaWPVDsUhpXmlzdBw6ZcHVlyg+AOdLb0FWnuEiPBvLOYtpfczmUd'
            ],

            /* getStudentSubjects
             */

            'ALLZ002 getStudentSubjects' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'code' => 'ALLZ002'
                ],
                'cJ8acCL+ZBvrQ7HWYIMDVKbeydQREXevtp/pWg2iE94='
            ],

            'BELL003 getStudentSubjects' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'code' => 'BELL003'
                ],
                'HATLZFto9wOr7gnTVUZgHqvvFtrgf4bYiu8s7RUwlRM='
            ],

            'JENS002 getStudentSubjects' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'code' => 'JENS002'
                ],
                'J01gPtQPn2Xrq+lZHH+QSvAwhq5+a+6SI9ZFIodQK/g='
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
                '+h/LoZqfIzF5WmRWy6cYYHvhIbbq/qoZImBzzgcAxYZ8Zg+AfqDABdqNW7QTAU9x4UV4CCRlMXgnAtsgokKLvnOHDP91FbawLt+jkxX3Qkw='
            ],

            'BIS getTeacherTimetable' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'teacher_code' => 'BIS',
                    'year_num' => '2021',
                    'semester' => '1'
                ],
                '+h/LoZqfIzF5WmRWy6cYYG/sWCRi+ewIeIvqy19tJL6W0kB4ZygL5jzljj1mGyDtKLVlUdjLYpW7FWSRMuXKyu1CPRy8v3dlCDraJX7AFgM='
            ],

            'RY getTeacherTimetable' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'teacher_code' => 'RY',
                    'year_num' => '2021',
                    'semester' => '1'
                ],
                '+h/LoZqfIzF5WmRWy6cYYKGeV1R/5QsEEdNHgBez5EJ8Zg+AfqDABdqNW7QTAU9x4UV4CCRlMXgnAtsgokKLvnOHDP91FbawLt+jkxX3Qkw='
            ],

            /* getTeacherSubjects
             */

            'AJ getTeacherSubjects' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'code' => 'AJ',
                ],
                'cJ8acCL+ZBvrQ7HWYIMDVKhWPRQfN63enWiTYViii7s='
            ],

            'CRO getTeacherSubjects' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'code' => 'CRO',
                ],
                'UJUcw2pLGyuGOaYYD/Jq/c5GdegftACk3a4WAMfmGLI='
            ],

            'ZTO getTeacherSubjects' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'code' => 'ZTO',
                ],
                '+oSVC42qXOSPW7sXflKJVirkMQfQ3qsUwlhcDVPtFcQ='
            ],

            /* CARR001 special area
             *
             * We encountered 401 Unauthorized for getStudentSubjects in TASS, for some reason.
             * The algorithm works for everyone else except him
             *
             * json encoded payloads are in the docblocks
             */
            'CARR001 getCommunicationRulesDetails' => [
                'ExXxxAsRVcoIMA7PDZmYOA==',
                [
                    'currentstatus' => 'current',
                    'commtype' => 'ec',
                    'code' => 'CARR001'
                ],
                '4aco62aV3eLUGHiO7IsbVDBGhU7OtVM5g+x9LQbcSuDzvJg1VnqpzX0TmND58DUHPE4bnL3DziRjlWROoUiZ5+OagEbQYmp5DjkNSlAJEuA='
            ],

            'CARR001 getStudentTimetable' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'student_code' => 'CARR001'
                ],
                'wjNWPE9+PjIPxaWPVDsUhgylmqlt1ymN/T07jChqrSv0FWnuEiPBvLOYtpfczmUd'
            ],

            /* We encountered random problems with this particular string
             * For some reason this breaks
             */
            'CARR001 getStudentSubjects' => [
                '1zG9pzJ6souPcbrYGTIMvw==',
                [
                    'code' => 'CARR001',
                ],
                'UJUcw2pLGyuGOaYYD/Jq/Xl+XuZASrd+HNIQAQ4LpHI='
            ],
        ];
    }
}

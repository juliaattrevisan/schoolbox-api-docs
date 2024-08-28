<?php

/**
 * ValidationTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\ProfessionalDevelopment\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\ProfessionalDevelopment\Model;
use Schoolbox\ProfessionalDevelopment\ValidProfessionalDevelopment;

class ValidationTest extends TestCase
{
    /**
     * Permitted Registration States
     *
     * @var array
     */
    private $states = [
        'NSW', 'VIC', 'QLD', 'NT', 'WA', 'SA', 'TAS', 'ACT'
    ];


    /**
     * @param Model $pd
     * @param int   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validCompleteDetails')]
    public function testValidCompleteDetails(Model $pd, $expected)
    {
        $validation = new ValidProfessionalDevelopment($this->states);

        $this->assertEquals($expected, $validation->isSatisfiedBy($pd));
    }

    /**
     * Data provider for testValidCompleteDetails
     *
     * @return array
     */
    public static function provider_validCompleteDetails(): array
    {
        return [
            'Valid Complete Details 1' => [
                (new Model())->setNumber(123)->setRegistrationState('NSW'),
                true
            ],
            'Valid Complete Details 2' => [
                (new Model())->setNumber('a123')->setRegistrationState('NSW'),
                true
            ],
            'Invalid Complete Details 1' => [
                (new Model())->setNumber('987 abc')->setRegistrationState('VIC'),
                false
            ],
            'Invalid Complete Details 2' => [
                (new Model())->setNumber(' a ')->setRegistrationState('ACT'),
                false
            ],
            'Invalid Complete Details 3' => [
                (new Model())->setNumber('aslbdf')->setRegistrationState('NSW '),
                false
            ],
            'Invalid Complete Details 4' => [
                (new Model())->setNumber('987 abc')->setRegistrationState('VICTORIA'),
                false
            ],
            'Invalid Complete Details 5' => [
                (new Model())->setNumber('  ')->setRegistrationState(' '),
                false
            ],
            'Invalid Complete Details 6' => [
                (new Model())->setNumber('987 abc')->setRegistrationState('    '),
                false
            ],
            'Invalid Complete Details 7' => [
                (new Model())->setNumber('  ')->setRegistrationState('      '),
                false
            ],
        ];
    }

    /**
     * @param Model $pd
     * @param int   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validState')]
    public function testValidState(Model $pd, $expected)
    {
        $validation = new ValidProfessionalDevelopment($this->states);

        $this->assertEquals($expected, $validation->isSatisfiedBy($pd));
    }

    /**
     * Data provider for testValidState
     *
     * @return array
     */
    public static function provider_validState(): array
    {
        return [
            'Valid State 1' => [
                (new Model())->setNumber(123)->setRegistrationState('VIC'),
                true
            ],
            'Invalid State 1' => [
                (new Model())->setNumber(123)->setRegistrationState(' VIC '),
                false
            ],
            'Invalid State 2' => [
                (new Model())->setNumber(123)->setRegistrationState('vic '),
                false
            ],
            'Invalid State 3' => [
                (new Model())->setNumber(123)->setRegistrationState(' vic'),
                false
            ],
            'Invalid State 4' => [
                (new Model())->setNumber(123)->setRegistrationState('MI'),
                false
            ],
            'Invalid State 5' => [
                (new Model())->setNumber(123)->setRegistrationState(' MI '),
                false
            ],
            'Invalid State 6' => [
                (new Model())->setNumber(123)->setRegistrationState('mi '),
                false
            ],
            'Invalid State 7' => [
                (new Model())->setNumber(123)->setRegistrationState(' mi'),
                false
            ],
            'Invalid State 8' => [
                (new Model())->setNumber(123)->setRegistrationState('mi'),
                false
            ],
        ];
    }

    /**
     * @param Model $pd
     * @param int   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validNumber')]
    public function testValidNumber(Model $pd, $expected)
    {
        $validation = new ValidProfessionalDevelopment($this->states);

        $this->assertEquals($expected, $validation->isSatisfiedBy($pd));
    }

    /**
     * Data provider for testValidNumber
     *
     * @return array
     */
    public static function provider_validNumber(): array
    {
        return [
            'Valid Number 1' => [
                (new Model())->setNumber(123)->setRegistrationState('VIC'),
                true
            ],
            'Valid Number 2' => [
                (new Model())->setNumber('a123a')->setRegistrationState('VIC'),
                true
            ],
            'Valid Number 3' => [
                (new Model())->setNumber('aaabbbbbcccc')->setRegistrationState('VIC'),
                true
            ],
            'Valid Number 4' => [
                (new Model())->setNumber('123')->setRegistrationState('VIC'),
                true
            ],
            'Invalid Number 1' => [
                (new Model())->setNumber('  123')->setRegistrationState('VIC'),
                false
            ],
            'Invalid Number 2' => [
                (new Model())->setNumber('  123  ')->setRegistrationState('VIC'),
                false
            ],
            'Invalid Number 3' => [
                (new Model())->setNumber('123  ')->setRegistrationState('VIC'),
                false
            ],
            'Invalid Number 4' => [
                (new Model())->setNumber('@(#&(*#!(*&!')->setRegistrationState('VIC'),
                false
            ],
            'Invalid Number 5' => [
                (new Model())->setNumber('')->setRegistrationState('VIC'),
                false
            ],
            'Invalid Number 6' => [
                (new Model())->setRegistrationState('VIC'),
                false
            ],
            'Invalid Number 7' => [
                (new Model())->setNumber('     ')->setRegistrationState('VIC'),
                false
            ],
        ];
    }
}

<?php

/**
 * ValidationTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\ProfessionalDevelopment\Test;

use Schoolbox\ProfessionalDevelopment\Model;
use Schoolbox\ProfessionalDevelopment\ValidProfessionalDevelopment;


class ValidationTest extends \PHPUnit_Framework_TestCase
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
     * @dataProvider provider_validCompleteDetails
     * @param Model $pd
     * @param int   $expected
     */
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
    public function provider_validCompleteDetails() {
        return [
            'Valid Complete Details 1' => [
                (new Model())->setNumber(123)->setRegistrationState('NSW'),
                true
            ],
            'Valid Complete Details 2' => [
                (new Model())->setNumber('987 abc')->setRegistrationState('VIC'),
                true
            ],
            'Valid Complete Details 3' => [
                (new Model())->setNumber(' a ')->setRegistrationState('ACT'),
                true
            ],
            'Invalid Complete Details 1' => [
                (new Model())->setNumber('aslbdf')->setRegistrationState('NSW '),
                false
            ],
            'Invalid Complete Details 2' => [
                (new Model())->setNumber('987 abc')->setRegistrationState('VICTORIA'),
                false
            ],
            'Invalid Complete Details 3' => [
                (new Model())->setNumber('  ')->setRegistrationState(' '),
                false
            ]
        ];
    }

    /**
     * @dataProvider provider_validState
     * @param Model $pd
     * @param int   $expected
     */
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
    public function provider_validState() {
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
                (new Model())->setNumber(123)->setRegistrationState('vic'),
                false
            ],
        ];
    }

    /**
     * @dataProvider provider_validNumber
     * @param Model $pd
     * @param int   $expected
     */
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
    public function provider_validNumber() {
        return [
            'Valid Number 1' => [
                (new Model())->setNumber(123)->setRegistrationState('VIC'),
                true
            ],
            'Valid Number 2' => [
                (new Model())->setNumber('123')->setRegistrationState('VIC'),
                true
            ],
            'Valid Number 3' => [
                (new Model())->setNumber('  123')->setRegistrationState('VIC'),
                true
            ],
            'Valid Number 4' => [
                (new Model())->setNumber('  123  ')->setRegistrationState('VIC'),
                true
            ],
            'Valid Number 4' => [
                (new Model())->setNumber('123  ')->setRegistrationState('VIC'),
                true
            ],
            'Valid Number 5' => [
                (new Model())->setNumber('a123a')->setRegistrationState('VIC'),
                true
            ],
            'Valid Number 6' => [
                (new Model())->setNumber('aaabbbbbcccc')->setRegistrationState('VIC'),
                true
            ],
            'Invalid Number 1' => [
                (new Model())->setNumber('@(#&(*#!(*&!')->setRegistrationState('VIC'),
                false
            ],
            'Invalid Number 2' => [
                (new Model())->setNumber('')->setRegistrationState('VIC'),
                false
            ],
            'Invalid Number 3' => [
                (new Model())->setRegistrationState('VIC'),
                false
            ],
            'Invalid Number 4' => [
                (new Model())->setNumber('     ')->setRegistrationState('VIC'),
                false
            ],
        ];
    }

}
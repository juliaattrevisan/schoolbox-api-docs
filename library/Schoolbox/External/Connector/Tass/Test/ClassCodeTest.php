<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Tass\Test;

use External_Tass_Class;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ClassCodeTest extends TestCase
{

    /**
     * @dataProvider providerForTestClassCode
     * @param string $classCode
     * @param string $expectedYear
     * @param string $expectedCode
     * @param string $expectedClass
     * @throws \ReflectionException
     */
    public function testClassCode(string $classCode, string $expectedYear, string $expectedCode, string $expectedClass)
    {
        $parseClassCode = (new ReflectionClass(External_Tass_Class::class))->getMethod('parseClassCode');
        $parseClassCode->setAccessible(true);

        [$actualYear, $actualCode, $actualClass] = $parseClassCode->invoke(null, $classCode);

        $this->assertEquals($actualYear, $expectedYear);
        $this->assertEquals($actualCode, $expectedCode);
        $this->assertEquals($actualClass, $expectedClass);
    }


    /**
     * Data provider for `testClassCode`.
     *
     * @return array
     */
    public function providerForTestClassCode()
    {
        return [
            '+ve year + code + class'                  => [
                'classCode'     => '12-6125-TC',
                'expectedYear'  => '12',
                'expectedCode'  => '6125',
                'expectedClass' => 'TC',
            ],
            '-ve year + code + class'                  => [
                'classCode'     => '-2-6125-TC',
                'expectedYear'  => '-2',
                'expectedCode'  => '6125',
                'expectedClass' => 'TC',
            ],
            'no year + code + class'                   => [
                'classCode'     => '-6125-TC',
                'expectedYear'  => '',
                'expectedCode'  => '6125',
                'expectedClass' => 'TC',
            ],
            'non-numeric year + code + class'          => [
                'classCode'     => 'P-6125-TC',
                'expectedYear'  => '',
                'expectedCode'  => '6125',
                'expectedClass' => 'TC',
            ],
            '+ve year + code + class w/ space'         => [
                'classCode'     => '12-ABCDEF-Term 2',
                'expectedYear'  => '12',
                'expectedCode'  => 'ABCDEF',
                'expectedClass' => 'Term 2',
            ],
            '-ve year + code + class w/ space'         => [
                'classCode'     => '-2-ABCDEF-Term 2',
                'expectedYear'  => '-2',
                'expectedCode'  => 'ABCDEF',
                'expectedClass' => 'Term 2',
            ],
            'no year + code + class w/ space'          => [
                'classCode'     => '-ABCDEF-Term 2',
                'expectedYear'  => '',
                'expectedCode'  => 'ABCDEF',
                'expectedClass' => 'Term 2',
            ],
            'non-numeric year + code + class w/ space' => [
                'classCode'     => 'P-ABCDEF-Term 2',
                'expectedYear'  => '',
                'expectedCode'  => 'ABCDEF',
                'expectedClass' => 'Term 2',
            ],
        ];
    }
}

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
     * @param bool   $expectWarning
     * @throws \ReflectionException
     */
    public function testClassCode(
        string $classCode,
        string $expectedYear,
        string $expectedCode,
        string $expectedClass,
        bool $expectWarning
    ): void {
        $parseClassCode = (new ReflectionClass(External_Tass_Class::class))->getMethod('parseClassCode');
        $parseClassCode->setAccessible(true);

        [$actualYear, $actualCode, $actualClass] = @$parseClassCode->invoke(null, $classCode);

        $this->assertSame($actualYear, $expectedYear);
        $this->assertSame($actualCode, $expectedCode);
        $this->assertSame($actualClass, $expectedClass);

        // Test the warning
        if ($expectWarning) {
            $this->expectWarning();
            $parseClassCode->invoke(null, $classCode);
        }
    }


    /**
     * Data provider for `testClassCode`.
     *
     * @return array
     */
    public function providerForTestClassCode(): array
    {
        return [
            '+ve year + code + class'                  => [
                'classCode'     => '12-6125-TC',
                'expectedYear'  => '12',
                'expectedCode'  => '6125',
                'expectedClass' => 'TC',
                'expectWarning' => false,
            ],
            '-ve year + code + class'                  => [
                'classCode'     => '-2-6125-TC',
                'expectedYear'  => '-2',
                'expectedCode'  => '6125',
                'expectedClass' => 'TC',
                'expectWarning' => false,
            ],
            'no year + code + class'                   => [
                'classCode'     => '-6125-TC',
                'expectedYear'  => '',
                'expectedCode'  => '6125',
                'expectedClass' => 'TC',
                'expectWarning' => false,
            ],
            'non-numeric year + code + class'          => [
                'classCode'     => 'P-6125-TC',
                'expectedYear'  => '',
                'expectedCode'  => '6125',
                'expectedClass' => 'TC',
                'expectWarning' => false,
            ],
            '+ve year + code + class w/ space'         => [
                'classCode'     => '12-ABCDEF-Term 2',
                'expectedYear'  => '12',
                'expectedCode'  => 'ABCDEF',
                'expectedClass' => 'Term 2',
                'expectWarning' => false,
            ],
            '-ve year + code + class w/ space'         => [
                'classCode'     => '-2-ABCDEF-Term 2',
                'expectedYear'  => '-2',
                'expectedCode'  => 'ABCDEF',
                'expectedClass' => 'Term 2',
                'expectWarning' => false,
            ],
            'no year + code + class w/ space'          => [
                'classCode'     => '-ABCDEF-Term 2',
                'expectedYear'  => '',
                'expectedCode'  => 'ABCDEF',
                'expectedClass' => 'Term 2',
                'expectWarning' => false,
            ],
            'non-numeric year + code + class w/ space' => [
                'classCode'     => 'P-ABCDEF-Term 2',
                'expectedYear'  => '',
                'expectedCode'  => 'ABCDEF',
                'expectedClass' => 'Term 2',
                'expectWarning' => false,
            ],
            // Error cases
            'empty' => [
                'classCode'     => '',
                'expectedYear'  => '',
                'expectedCode'  => '',
                'expectedClass' => '',
                'expectWarning' => false,
            ],
            'only one part' => [
                'classCode'     => '12',
                'expectedYear'  => '12',
                'expectedCode'  => '',
                'expectedClass' => '',
                'expectWarning' => false,
            ],
            // This cannot be tested, as a negative year is indistinguishable
            // from a year+code if the class code is malformed
//            'only one negative part' => [
//                'classCode'     => '-12',
//                'expectedYear'  => '-12',
//                'expectedCode'  => '',
//                'expectedClass' => '',
//                'expectWarning' => true,
//            ],
            'only one non-numeric part' => [
                'classCode'     => 'D',
                'expectedYear'  => '',
                'expectedCode'  => '',
                'expectedClass' => '',
                'expectWarning' => false,
            ],
            'only two parts, no year' => [
                'classCode'     => '-ABCDEF',
                'expectedYear'  => '',
                'expectedCode'  => 'ABCDEF',
                'expectedClass' => '',
                'expectWarning' => false,
            ],
            'only two parts, year' => [
                'classCode'     => '12-ABCDEF',
                'expectedYear'  => '12',
                'expectedCode'  => 'ABCDEF',
                'expectedClass' => '',
                'expectWarning' => false,
            ],
            // This cannot be tested, as a negative year is indistinguishable
            // from a year+code if the class code is malformed
//            'only two parts, negative year' => [
//                'classCode'     => '-12-ABCDEF',
//                'expectedYear'  => '12',
//                'expectedCode'  => 'ABCDEF',
//                'expectedClass' => '',
//                'expectWarning' => true,
//            ],
            'only two parts, non-numeric year' => [
                'classCode'     => 'B-ABCDEF',
                'expectedYear'  => '',
                'expectedCode'  => 'ABCDEF',
                'expectedClass' => '',
                'expectWarning' => false,
            ],
            'excess parts, no year' => [
                'classCode'     => '-ABCDEF-Term 2-excess',
                'expectedYear'  => '',
                'expectedCode'  => 'ABCDEF',
                'expectedClass' => 'Term 2',
                'expectWarning' => false,
            ],
            'excess parts, year' => [
                'classCode'     => '6-ABCDEF-Term 2-excess',
                'expectedYear'  => '6',
                'expectedCode'  => 'ABCDEF',
                'expectedClass' => 'Term 2',
                'expectWarning' => false,
            ],
            // This cannot be tested, as a negative year is indistinguishable
            // from a year+code if the class code is malformed
//            'excess parts, negative year' => [
//                'classCode'     => '-6-ABCDEF-Term 2-excess',
//                'expectedYear'  => '-6',
//                'expectedCode'  => 'ABCDEF',
//                'expectedClass' => 'Term 2',
//                'expectWarning' => true,
//            ],
            'excess parts, non-numeric year' => [
                'classCode'     => 'B-ABCDEF-Term 2-excess',
                'expectedYear'  => '',
                'expectedCode'  => 'ABCDEF',
                'expectedClass' => 'Term 2',
                'expectWarning' => false,
            ],
        ];
    }
}

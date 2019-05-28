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
     * @param string $year
     * @param string $code
     * @param string $class
     * @throws \ReflectionException
     */
    public function testClassCode(string $year, string $code, string $class)
    {
        $parseClassCode = (new ReflectionClass(External_Tass_Class::class))->getMethod('parseClassCode');
        $parseClassCode->setAccessible(true);

        list($actualYear, $actualCode, $actualClass) = $parseClassCode->invoke(null, implode('-', [$year, $code, $class]));

        $this->assertEquals($actualYear, $year);
        $this->assertEquals($actualCode, $code);
        $this->assertEquals($actualClass, $class);
    }


    /**
     * Data provider for `testClassCode`.
     *
     * @return array
     */
    public function providerForTestClassCode()
    {
        return [
            ['year' => '12', 'code' => '6125', 'class' => 'TC'],
            ['year' => '-2', 'code' => '6125', 'class' => 'TC'],
            ['year' => '', 'code' => '6125', 'class' => 'TC'],
            ['year' => '12', 'code' => 'ABCDEF', 'class' => 'Term 2'],
            ['year' => '-2', 'code' => 'ABCDEF', 'class' => 'Term 2'],
            ['year' => '', 'code' => 'ABCDEF', 'class' => 'Term 2'],
        ];
    }
}

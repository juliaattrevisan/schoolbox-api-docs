<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Tass\Test;

use External_Tass_Class;
use PHPUnit\Framework\TestCase;

class ClassCodeTest extends TestCase
{

    /**
     * @dataProvider providerForTestClassCode
     * @param string $year
     * @param string $code
     * @param string $class
     */
    public function testClassCode(string $year, string $code, string $class)
    {

        list($actualYear, $actualCode, $actualClass) = External_Tass_Class::parseClassCode(implode('-', [$year, $code, $class]));
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

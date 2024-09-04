<?php

/**
 * ClassCodeTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\External\Connector\Test;

use Schoolbox\External\Connector\ClassCode;
use PHPUnit\Framework\TestCase;

class ClassCodeTest extends TestCase
{
    /**
     * @dataProvider providerClassCode
     * @param mixed $input
     * @param string $expected
     */
    public function testClassCode($input, $expected)
    {
        $outcome = (new ClassCode(null))->generate($input);

        $this->assertEquals($expected, $outcome);
    }


    /**
     * Data provider for testClassCode
     *
     * @return array
     */
    public static function providerClassCode(): array
    {
        return [
            /* Meaningless class codes
             */
            'Valid Empty 1' => [
                '',
                ''
            ],

            'Valid Empty 2' => [
                '             ',
                ''
            ],

            'Valid Empty 3' => [
                null,
                ''
            ],

            'Valid Empty 4' => [
                "     \r\n\t ",
                ''
            ],

            /* Generic specimens
             */
            'Valid 1' => [
                '12-ENG-A',
                '12-ENG-A',
            ],

            'Valid 2' => [
                '9MATHC',
                '9MATHC',
            ],

            'Valid 3' => [
                '9MATHC ABCDE',
                '9MATHC ABCDE',
            ],

            'Valid 4' => [
                "   9MATHC ABCDE    \r\n\t",
                '9MATHC ABCDE',
            ],

            /* Truncated versions
             */
            'Truncated 1' => [
                'Incursion Meeting: Finalization The Great Exploring Science Clothing Swap',
                'Incursion Meeting: Finalization The Great Exploring Science Clot'
            ],

            'Truncated 2' => [
                '    Instead of a class code like 12-ENG-A, we get something like Incursion Meeting: Finalization The Great Exploring Science Clothing Swap instead. It’s manufactured from some other data that edval knows about: like an assessment title, or in this example, some random excursion activity.',
                'Instead of a class code like 12-ENG-A, we get something like Inc'
            ],
        ];
    }
}
